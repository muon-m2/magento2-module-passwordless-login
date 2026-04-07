<?php

declare(strict_types=1);

namespace Muon\PasswordlessLogin\Model;

use DateTimeImmutable;
use DateTimeZone;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AuthenticationInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Muon\PasswordlessLogin\Api\MagicLinkServiceInterface;
use Muon\PasswordlessLogin\Api\TokenRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * Coordination service — coupling and constructor size are inherent to orchestrating token creation, email dispatch,
 * and authentication.
 */
class MagicLinkService implements MagicLinkServiceInterface
{
    private const EMAIL_TEMPLATE_ID = 'muon_passwordlesslogin_magic_link';

    /** Number of seconds in the rate-limit sliding window (1 hour). */
    private const RATE_LIMIT_WINDOW_SECONDS = 3600;

    /**
     * @param \Magento\Customer\Api\CustomerRepositoryInterface    $customerRepository
     * @param \Muon\PasswordlessLogin\Api\TokenRepositoryInterface $tokenRepository
     * @param \Muon\PasswordlessLogin\Model\TokenFactory           $tokenFactory
     * @param \Muon\PasswordlessLogin\Model\Config                 $config
     * @param \Magento\Framework\Mail\Template\TransportBuilder    $transportBuilder
     * @param \Magento\Store\Model\StoreManagerInterface           $storeManager
     * @param \Magento\Framework\UrlInterface                      $url
     * @param \Psr\Log\LoggerInterface                             $logger
     * @param \Magento\Framework\Stdlib\DateTime\DateTime          $dateTime
     * @param \Magento\Customer\Api\AccountManagementInterface     $accountManagement
     * @param \Magento\Customer\Model\AuthenticationInterface      $authentication
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly TokenRepositoryInterface $tokenRepository,
        private readonly TokenFactory $tokenFactory,
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url,
        private readonly LoggerInterface $logger,
        private readonly DateTime $dateTime,
        private readonly AccountManagementInterface $accountManagement,
        private readonly AuthenticationInterface $authentication,
    ) {
    }

    /**
     * Generate a single-use login token and send it to the given email address.
     *
     * @param string $email
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function sendLink(string $email): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $customer = $this->customerRepository->get($email);
        } catch (NoSuchEntityException) {
            // Silent fail — prevents email enumeration
            return;
        }

        $windowStart = $this->dateTime->gmtDate(
            'Y-m-d H:i:s',
            time() - self::RATE_LIMIT_WINDOW_SECONDS
        );
        $recentCount = $this->tokenRepository->countRecentByCustomerId((int)$customer->getId(), $windowStart);

        if ($recentCount >= $this->config->getMaxAttempts()) {
            // Silent fail — do not reveal rate limit status
            return;
        }

        // Invalidate any outstanding unused tokens before issuing a new one, so that
        // previously delivered links cannot be replayed after this request.
        $this->tokenRepository->deleteUnusedByCustomerId((int)$customer->getId());

        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);
        $lifetime = $this->config->getTokenLifetime();
        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify("+{$lifetime} minutes")
            ->format('Y-m-d H:i:s');

        $token = $this->tokenFactory->create();
        $token->setCustomerId((int)$customer->getId());
        $token->setToken($hashedToken);
        $token->setExpiresAt($expiresAt);
        $this->tokenRepository->save($token);

        $magicLink = $this->url->getUrl(
            'passwordlesslogin/login/authenticate',
            ['token' => $rawToken, '_nosid' => true],
        );

        $this->dispatchEmail($email, $customer->getFirstname(), $magicLink, $lifetime);
    }

    /**
     * Validate a raw token, verify account status, consume the token, and return the customer.
     *
     * @param string $rawToken
     *
     * @return \Magento\Customer\Api\Data\CustomerInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function authenticate(string $rawToken): CustomerInterface
    {
        $hashedToken = hash('sha256', $rawToken);

        try {
            $token = $this->tokenRepository->getByToken($hashedToken);
        } catch (NoSuchEntityException) {
            throw new LocalizedException(
                __('The login link is invalid or has expired. Please request a new one.'),
            );
        }

        $now = $this->dateTime->gmtDate('Y-m-d H:i:s');

        // Fast-fail on expiry or already-consumed without a DB write.
        if ($token->getExpiresAt() < $now || $token->getUsedAt() !== null) {
            throw new LocalizedException(
                __('The login link is invalid or has expired. Please request a new one.'),
            );
        }

        // Atomically mark the token as consumed. A false return means another concurrent
        // request consumed it between our load and this UPDATE (TOCTOU race condition).
        if (!$this->tokenRepository->consumeToken($hashedToken, $now)) {
            throw new LocalizedException(
                __('The login link is invalid or has expired. Please request a new one.'),
            );
        }

        $customerId = (int)$token->getCustomerId();
        $customer = $this->customerRepository->getById($customerId);

        $confirmationStatus = $this->accountManagement->getConfirmationStatus($customerId);
        if ($confirmationStatus === AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED) {
            throw new LocalizedException(
                __('This account has not been confirmed yet. Please check your email for a confirmation link.'),
            );
        }

        if ($this->authentication->isLocked($customerId)) {
            throw new LocalizedException(
                __('This account is temporarily locked. Please try again later.'),
            );
        }

        return $customer;
    }

    /**
     * Build and send the magic link email to the customer.
     *
     * @param string $email
     * @param string $customerName
     * @param string $magicLink
     * @param int    $lifetime
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function dispatchEmail(string $email, string $customerName, string $magicLink, int $lifetime): void
    {
        try {
            $store = $this->storeManager->getStore();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ID)
                ->setTemplateOptions([
                    'area'  => Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'customer_name'  => $customerName,
                    'magic_link'     => $magicLink,
                    'token_lifetime' => $lifetime,
                    'store'          => $store,
                ])
                ->setFromByScope('general', $store->getId())
                ->addTo($email, $customerName)
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->logger->error(
                'Muon_PasswordlessLogin: failed to send magic link email.',
                ['exception' => $e, 'email_hash' => hash('sha256', $email)],
            );
            throw new LocalizedException(__('Unable to send the login link. Please try again later.'), $e);
        }
    }
}
