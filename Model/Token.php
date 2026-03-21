<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Model;

use Magento\Framework\Model\AbstractModel;
use Muon\PasswordlessLogin\Api\Data\TokenInterface;
use Muon\PasswordlessLogin\Model\ResourceModel\Token as TokenResource;

/**
 * Magic link token model.
 */
class Token extends AbstractModel implements TokenInterface
{
    /**
     * Initialize resource model.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TokenResource::class);
    }

    /**
     * Get internal token ID.
     *
     * @return int|null
     */
    public function getTokenId(): ?int
    {
        $value = $this->getData(self::TOKEN_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * Set internal token ID.
     *
     * @param int $tokenId
     * @return $this
     */
    public function setTokenId(int $tokenId): self
    {
        return $this->setData(self::TOKEN_ID, $tokenId);
    }

    /**
     * Get the owning customer ID.
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    /**
     * Set the owning customer ID.
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * Get the SHA-256 hashed token value.
     *
     * @return string
     */
    public function getToken(): string
    {
        return (string) $this->getData(self::TOKEN);
    }

    /**
     * Set the SHA-256 hashed token value.
     *
     * @param string $token
     * @return $this
     */
    public function setToken(string $token): self
    {
        return $this->setData(self::TOKEN, $token);
    }

    /**
     * Get the token expiry datetime string.
     *
     * @return string
     */
    public function getExpiresAt(): string
    {
        return (string) $this->getData(self::EXPIRES_AT);
    }

    /**
     * Set the token expiry datetime string.
     *
     * @param string $expiresAt
     * @return $this
     */
    public function setExpiresAt(string $expiresAt): self
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    /**
     * Get the datetime when this token was consumed; null if unused.
     *
     * @return string|null
     */
    public function getUsedAt(): ?string
    {
        $value = $this->getData(self::USED_AT);
        return $value !== null ? (string) $value : null;
    }

    /**
     * Set the datetime when this token was consumed.
     *
     * @param string|null $usedAt
     * @return $this
     */
    public function setUsedAt(?string $usedAt): self
    {
        return $this->setData(self::USED_AT, $usedAt);
    }

    /**
     * Get the token creation datetime string.
     *
     * @return string
     */
    public function getCreatedAt(): string
    {
        return (string) $this->getData(self::CREATED_AT);
    }

    /**
     * Set the token creation datetime string.
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
