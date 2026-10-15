<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Test\Unit\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Muon\PasswordlessLogin\Api\Data\TokenInterface;
use Muon\PasswordlessLogin\Model\ResourceModel\Token as TokenResource;
use Muon\PasswordlessLogin\Model\ResourceModel\Token\Collection;
use Muon\PasswordlessLogin\Model\ResourceModel\Token\CollectionFactory;
use Muon\PasswordlessLogin\Model\Token;
use Muon\PasswordlessLogin\Model\TokenFactory;
use Muon\PasswordlessLogin\Model\TokenRepository;
use Exception;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TokenRepositoryTest extends TestCase
{
    /** @var \Muon\PasswordlessLogin\Model\TokenRepository */
    private TokenRepository $subject;

    // phpcs:disable Magento2.Commenting.ClassPropertyPHPDocFormatting.Missing
    // Intersection types (Type&MockObject) are not supported by the PHPCS sniff.
    /** @var \Muon\PasswordlessLogin\Model\ResourceModel\Token */
    private TokenResource&MockObject $resource;

    /** @var \Muon\PasswordlessLogin\Model\TokenFactory */
    private TokenFactory&MockObject $tokenFactory;

    /** @var \Muon\PasswordlessLogin\Model\ResourceModel\Token\CollectionFactory */
    private CollectionFactory&MockObject $collectionFactory;

    /** @var \Magento\Framework\Stdlib\DateTime\DateTime */
    private DateTime&MockObject $dateTime;
    // phpcs:enable Magento2.Commenting.ClassPropertyPHPDocFormatting.Missing

    protected function setUp(): void
    {
        $this->resource          = $this->createMock(TokenResource::class);
        $this->tokenFactory      = $this->createMock(TokenFactory::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->dateTime          = $this->createMock(DateTime::class);

        $this->subject = new TokenRepository(
            $this->resource,
            $this->tokenFactory,
            $this->collectionFactory,
            $this->dateTime
        );
    }

    public function testSaveCallsResourceSave(): void
    {
        $token = $this->createMock(Token::class);
        $this->resource->expects($this->once())->method('save')->with($token);

        $result = $this->subject->save($token);

        self::assertSame($token, $result);
    }

    public function testSaveThrowsCouldNotSaveExceptionOnFailure(): void
    {
        $this->expectException(CouldNotSaveException::class);

        $token = $this->createMock(Token::class);
        $this->resource->method('save')->willThrowException(new Exception('DB error'));

        $this->subject->save($token);
    }

    public function testGetByTokenReturnsTokenWhenFound(): void
    {
        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(1);
        $this->tokenFactory->method('create')->willReturn($token);
        $this->resource->expects($this->once())
            ->method('load')
            ->with($token, 'abc123hash', TokenInterface::TOKEN);

        $result = $this->subject->getByToken('abc123hash');

        self::assertSame($token, $result);
    }

    public function testGetByTokenThrowsNoSuchEntityExceptionWhenNotFound(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $token = $this->createMock(Token::class);
        $token->method('getTokenId')->willReturn(null);
        $this->tokenFactory->method('create')->willReturn($token);

        $this->subject->getByToken('nonexistent');
    }

    public function testCountRecentByCustomerIdReturnsCollectionSize(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('getSize')->willReturn(3);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->subject->countRecentByCustomerId(42, '2026-03-13 10:00:00');

        self::assertSame(3, $result);
    }

    public function testConsumeTokenReturnsTrueWhenRowUpdated(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('update')->willReturn(1);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getMainTable')->willReturn('muon_passwordless_login_token');

        $result = $this->subject->consumeToken('abc123hash', '2026-03-13 10:00:00');

        self::assertTrue($result);
    }

    public function testConsumeTokenReturnsFalseWhenNoRowsAffected(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->method('update')->willReturn(0);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getMainTable')->willReturn('muon_passwordless_login_token');

        $result = $this->subject->consumeToken('abc123hash', '2026-03-13 10:00:00');

        self::assertFalse($result);
    }

    public function testDeleteUnusedByCustomerIdCallsConnectionDelete(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->expects($this->once())->method('delete');
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getMainTable')->willReturn('muon_passwordless_login_token');

        $this->subject->deleteUnusedByCustomerId(42);
    }

    public function testMarkSupersededByCustomerIdStampsUsedAtOnUnusedRows(): void
    {
        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->expects($this->once())
            ->method('update')
            ->with(
                'muon_passwordless_login_token',
                self::callback(static fn (array $bind): bool => ($bind['used_at'] ?? null) === '2026-05-29 10:00:00'),
                self::callback(static fn (array $cond): bool => in_array('used_at IS NULL', $cond, true))
            )
            ->willReturn(2);
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getMainTable')->willReturn('muon_passwordless_login_token');
        $this->dateTime->method('gmtDate')->willReturn('2026-05-29 10:00:00');

        $this->subject->markSupersededByCustomerId(42);
    }

    /**
     * Cleanup ran hourly and deleted every expired or consumed row, including rows the per-hour rate
     * limit was still counting, so the limit reset on each run. Only rows older than the window may go.
     */
    public function testRegressionDeleteExpiredKeepsRowsTheRateLimitStillCounts(): void
    {
        $windowInput = null;
        $this->dateTime->method('gmtDate')->willReturnCallback(
            static function (string $format, ?int $input = null) use (&$windowInput): string {
                self::assertSame('Y-m-d H:i:s', $format);
                if ($input === null) {
                    return '2026-10-15 12:00:00';
                }
                $windowInput = $input;
                return '2026-10-15 11:00:00';
            }
        );

        $connection = $this->createMock(\Magento\Framework\DB\Adapter\AdapterInterface::class);
        $connection->expects($this->once())
            ->method('delete')
            ->with(
                'muon_passwordless_login_token',
                [
                    'created_at < ?' => '2026-10-15 11:00:00',
                    'expires_at < ? OR used_at IS NOT NULL' => '2026-10-15 12:00:00',
                ]
            );
        $this->resource->method('getConnection')->willReturn($connection);
        $this->resource->method('getMainTable')->willReturn('muon_passwordless_login_token');

        $this->subject->deleteExpired();

        // A literal hour, not Config::RATE_LIMIT_WINDOW_SECONDS: this pins the window itself.
        self::assertEqualsWithDelta(time() - 3600, $windowInput, 5);
    }
}
