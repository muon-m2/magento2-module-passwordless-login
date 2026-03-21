<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Api;

/**
 * Generates and delivers a one-time magic login link to a customer's email address.
 *
 * @api
 */
interface MagicLinkServiceInterface
{
    /**
     * Generate a single-use login token and send it to the given email address.
     *
     * Silently succeeds if the email is not registered or rate limit is reached
     * to prevent email enumeration and brute-force attacks.
     *
     * @param string $email
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException On mail transport failure.
     */
    public function sendLink(string $email): void;

    /**
     * Validate a token, verify account status, consume it, and return the authenticated customer.
     *
     * @param string $rawToken
     * @return \Magento\Customer\Api\Data\CustomerInterface
     * @throws \Magento\Framework\Exception\LocalizedException On invalid/expired token or inactive account.
     */
    public function authenticate(string $rawToken): \Magento\Customer\Api\Data\CustomerInterface;
}
