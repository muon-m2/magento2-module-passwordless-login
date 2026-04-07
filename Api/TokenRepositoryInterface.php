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
     * Atomically mark a token as used if it has not yet been consumed.
     *
     * Returns true when exactly one row was updated (the token was successfully consumed).
     * Returns false when the token was already consumed or does not exist (race condition).
     *
     * @param string $tokenHash SHA-256 hash of the raw token
     * @param string $usedAt    MySQL datetime string (Y-m-d H:i:s) to record as consumption time
     * @return bool
     */
    public function consumeToken(string $tokenHash, string $usedAt): bool;

    /**
     * Delete all unused (not yet consumed) tokens for the given customer.
     *
     * Called before issuing a new token so that previously issued, still-valid links
     * cannot be replayed after the customer requests a replacement.
     *
     * @param int $customerId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteUnusedByCustomerId(int $customerId): void;

    /**
     * Delete all expired tokens and all consumed tokens.
     *
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteExpired(): void;
}
