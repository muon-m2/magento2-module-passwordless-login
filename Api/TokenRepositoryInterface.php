<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Api;

use Muon\PasswordlessLogin\Api\Data\TokenInterface;

/**
 * @api
 */
interface TokenRepositoryInterface
{
    /**
     * Save a token entity.
     *
     * @param \Muon\PasswordlessLogin\Api\Data\TokenInterface $token
     * @return \Muon\PasswordlessLogin\Api\Data\TokenInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(TokenInterface $token): TokenInterface;

    /**
     * Load a token by its SHA-256 hash.
     *
     * @param string $tokenHash
     * @return \Muon\PasswordlessLogin\Api\Data\TokenInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByToken(string $tokenHash): TokenInterface;

    /**
     * Count tokens created for a customer on or after the given datetime string (rate limiting).
     *
     * @param int    $customerId
     * @param string $since      MySQL datetime string (Y-m-d H:i:s)
     * @return int
     */
    public function countRecentByCustomerId(int $customerId, string $since): int;

    /**
     * Delete all expired tokens and all consumed tokens.
     *
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteExpired(): void;
}
