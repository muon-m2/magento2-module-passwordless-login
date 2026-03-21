<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Test\Unit\Model;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Muon\PasswordlessLogin\Api\TokenRepositoryInterface;
use Muon\PasswordlessLogin\Model\Config;
use Muon\PasswordlessLogin\Model\MagicLinkService;
use Muon\PasswordlessLogin\Model\Token;
use Muon\PasswordlessLogin\Model\TokenFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MagicLinkServiceTest extends TestCase
{
    /** @var MagicLinkService */
    private MagicLinkService $subject;

    // phpcs:disable Magento2.Commenting.ClassPropertyPHPDocFormatting.Missing
    // Intersection types (Type&MockObject) are not supported by the PHPCS sniff.
    /** @var \Magento\Customer\Api\CustomerRepositoryInterface */
    private CustomerRepositoryInterface&MockObject $customerRepository;

    /** @var \Muon\PasswordlessLogin\Api\TokenRepositoryInterface */
    private TokenRepositoryInterface&MockObject $tokenRepository;

    /** @var \Muon\PasswordlessLogin\Model\TokenFactory */
    private TokenFactory&MockObject $tokenFactory;

    /** @var \Muon\PasswordlessLogin\Model\Config */
    private Config&MockObject $config;

    /** @var \Magento\Framework\Mail\Template\TransportBuilder */
    private TransportBuilder&MockObject $transportBuilder;

    /** @var \Magento\Store\Model\StoreManagerInterface */
    private StoreManagerInterface&MockObject $storeManager;

    /** @var \Magento\Framework\UrlInterface */
    private UrlInterface&MockObject $url;

    /** @var \Psr\Log\LoggerInterface */
    private LoggerInterface&MockObject $logger;

    /** @var \Magento\Framework\Stdlib\DateTime\DateTime */
    private DateTime&MockObject $dateTime;

    /** @var \Magento\Customer\Api\AccountManagementInterface */
    private AccountManagementInterface&MockObject $accountManagement;

    /** @var \Magento\Customer\Model\AuthenticationInterface */
    private AuthenticationInterface&MockObject $authentication;
    // phpcs:enable Magento2.Commenting.ClassPropertyPHPDocFormatting.Missing

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->tokenRepository    = $this->createMock(TokenRepositoryInterface::class);
        $this->tokenFactory       = $this->createMock(TokenFactory::class);
        $this->config             = $this->createMock(Config::class);
        $this->transportBuilder   = $this->createMock(TransportBuilder::class);
        $this->storeManager       = $this->createMock(StoreManagerInterface::class);
        $this->url                = $this->createMock(UrlInterface::class);
        $this->logger             = $this->createMock(LoggerInterface::class);
        $this->dateTime           = $this->createMock(DateTime::class);
        $this->accountManagement  = $this->createMock(AccountManagementInterface::class);
        $this->authentication     = $this->createMock(AuthenticationInterface::class);

        $this->subject = new MagicLinkService(
            $this->customerRepository,
            $this->tokenRepository,
            $this->tokenFactory,
            $this->config,
            $this->transportBuilder,
            $this->storeManager,
            $this->url,
            $this->logger,
            $this->dateTime,
            $this->accountManagement,
            $this->authentication
        );
    }

    public function testSendLinkDoesNothingWhenModuleDisabled(): void
    {
        $this->config->expects($this->once())->method('isEnabled')->willReturn(false);
        $this->customerRepository->expects($this->never())->method('get');

        $this->subject->sendLink('customer@example.com');
    }

    public function testSendLinkSilentlySucceedsForUnknownEmail(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->customerRepository
            ->expects($this->once())
            ->method('get')
            ->with('unknown@example.com')
            ->willThrowException(new NoSuchEntityException());

        $this->tokenRepository->expects($this->never())->method('save');

        $this->subject->sendLink('unknown@example.com');
    }

    public function testSendLinkSilentlySucceedsWhenRateLimitReached(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getMaxAttempts')->willReturn(5);
        $this->dateTime->method('gmtDate')->willReturn('2026-03-13 10:00:00');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn('42');
        $this->customerRepository->method('get')->willReturn($customer);

        $this->tokenRepository
            ->expects($this->once())
            ->method('countRecentByCustomerId')
            ->willReturn(5);

        $this->tokenRepository->expects($this->never())->method('save');

        $this->subject->sendLink('customer@example.com');
    }

    public function testSendLinkGeneratesTokenAndSendsEmail(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('getMaxAttempts')->willReturn(5);
        $this->config->method('getTokenLifetime')->willReturn(15);
        $this->dateTime->method('gmtDate')->willReturn('2026-03-13 10:00:00');

        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn('42');
        $customer->method('getFirstname')->willReturn('Jane');
        $this->customerRepository->method('get')->willReturn($customer);

        $this->tokenRepository->method('countRecentByCustomerId')->willReturn(0);

        $token = $this->createMock(Token::class);
        $this->tokenFactory->method('create')->willReturn($token);
        $token->expects($this->once())->method('setCustomerId')->with(42)->willReturnSelf();
        $token->expects($this->once())->method('setToken')->willReturnSelf();
        $token->expects($this->once())->method('setExpiresAt')->willReturnSelf();
        $this->tokenRepository->expects($this->once())->method('save')->with($token);

        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);
        $this->url->method('getUrl')->willReturn('https://example.com/passwordlesslogin/login/authenticate?token=abc');

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->subject->sendLink('customer@example.com');
    }

    public function testAuthenticateThrowsOnInvalidToken(): void
    {
        $this->tokenRepository
            ->method('getByToken')
            ->willThrowException(new NoSuchEntityException());

        $this->expectException(LocalizedException::class);
        $this->subject->authenticate('invalid-raw-token');
    }

    public function testAuthenticateThrowsOnExpiredToken(): void
    {
        $this->dateTime->method('gmtDate')->willReturn('2026-03-13 12:00:00');

        $token = $this->createMock(Token::class);
        $token->method('getExpiresAt')->willReturn('2026-03-13 11:00:00');
        $token->method('getUsedAt')->willReturn(null);
        $this->tokenRepository->method('getByToken')->willReturn($token);

        $this->expectException(LocalizedException::class);
        $this->subject->authenticate('some-raw-token');
    }

    public function testAuthenticateThrowsOnLockedAccount(): void
    {
        $this->dateTime->method('gmtDate')->willReturn('2026-03-13 10:00:00');

        $token = $this->createMock(Token::class);
        $token->method('getExpiresAt')->willReturn('2026-03-13 11:00:00');
        $token->method('getUsedAt')->willReturn(null);
        $token->method('getCustomerId')->willReturn(42);
        $this->tokenRepository->method('getByToken')->willReturn($token);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->accountManagement
            ->method('getConfirmationStatus')
            ->willReturn(AccountManagementInterface::ACCOUNT_CONFIRMED);
        $this->authentication->method('isLocked')->willReturn(true);

        $this->expectException(LocalizedException::class);
        $this->subject->authenticate('some-raw-token');
    }

    public function testAuthenticateConsumesTokenAndReturnsCustomer(): void
    {
        $this->dateTime->method('gmtDate')->willReturn('2026-03-13 10:00:00');

        $token = $this->createMock(Token::class);
        $token->method('getExpiresAt')->willReturn('2026-03-13 11:00:00');
        $token->method('getUsedAt')->willReturn(null);
        $token->method('getCustomerId')->willReturn(42);
        $token->expects($this->once())->method('setUsedAt')->with('2026-03-13 10:00:00')->willReturnSelf();
        $this->tokenRepository->method('getByToken')->willReturn($token);
        $this->tokenRepository->expects($this->once())->method('save')->with($token);

        $customer = $this->createMock(CustomerInterface::class);
        $this->customerRepository->method('getById')->willReturn($customer);

        $this->accountManagement
            ->method('getConfirmationStatus')
            ->willReturn(AccountManagementInterface::ACCOUNT_CONFIRMED);
        $this->authentication->method('isLocked')->willReturn(false);

        $result = $this->subject->authenticate('some-raw-token');

        self::assertSame($customer, $result);
    }
}
