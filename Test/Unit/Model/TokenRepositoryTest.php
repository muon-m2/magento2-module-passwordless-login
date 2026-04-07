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
}
