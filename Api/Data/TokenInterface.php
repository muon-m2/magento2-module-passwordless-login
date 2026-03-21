<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Api\Data;

/**
 * Magic link token DTO.
 *
 * Note: this interface intentionally does not extend ExtensibleDataInterface.
 * Tokens are an internal security infrastructure entity — they are not intended
 * to be extended by third-party modules via extension attributes.
 *
 * @api
 */
interface TokenInterface
{
    public const TOKEN_ID   = 'token_id';
    public const CUSTOMER_ID = 'customer_id';
    public const TOKEN      = 'token';
    public const EXPIRES_AT = 'expires_at';
    public const USED_AT    = 'used_at';
    public const CREATED_AT = 'created_at';

    /**
     * Get internal token ID.
     *
     * @return int|null
     */
    public function getTokenId(): ?int;

    /**
     * Set internal token ID.
     *
     * @param int $tokenId
     * @return $this
     */
    public function setTokenId(int $tokenId): self;

    /**
     * Get the owning customer ID.
     *
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * Set the owning customer ID.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self;

    /**
     * Get the SHA-256 hashed token value.
     *
     * @return string
     */
    public function getToken(): string;

    /**
     * Set the SHA-256 hashed token value.
     *
     * @param string $token
     * @return $this
     */
    public function setToken(string $token): self;

    /**
     * Get the token expiry datetime string.
     *
     * @return string
     */
    public function getExpiresAt(): string;

    /**
     * Set the token expiry datetime string.
     *
     * @param string $expiresAt
     * @return $this
     */
    public function setExpiresAt(string $expiresAt): self;

    /**
     * Get the datetime when this token was consumed; null if unused.
     *
     * @return string|null
     */
    public function getUsedAt(): ?string;

    /**
     * Set the datetime when this token was consumed.
     *
     * @param string|null $usedAt
     * @return $this
     */
    public function setUsedAt(?string $usedAt): self;

    /**
     * Get the token creation datetime string.
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set the token creation datetime string.
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;
}
