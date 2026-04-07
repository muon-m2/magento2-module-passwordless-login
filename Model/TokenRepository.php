<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Muon\PasswordlessLogin\Api\Data\TokenInterface;
use Muon\PasswordlessLogin\Api\TokenRepositoryInterface;
use Muon\PasswordlessLogin\Model\ResourceModel\Token as TokenResource;
use Muon\PasswordlessLogin\Model\ResourceModel\Token\CollectionFactory;

class TokenRepository implements TokenRepositoryInterface
{
    /**
     * @param \Muon\PasswordlessLogin\Model\ResourceModel\Token                   $resource
     * @param \Muon\PasswordlessLogin\Model\TokenFactory                          $tokenFactory
     * @param \Muon\PasswordlessLogin\Model\ResourceModel\Token\CollectionFactory $collectionFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime                         $dateTime
     */
    public function __construct(
        private readonly TokenResource $resource,
        private readonly TokenFactory $tokenFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * Persist a token entity.
     *
     * @param \Muon\PasswordlessLogin\Api\Data\TokenInterface $token
     * @return \Muon\PasswordlessLogin\Api\Data\TokenInterface
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(TokenInterface $token): TokenInterface
    {
        try {
            $this->resource->save($token);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save token: %1', $e->getMessage()), $e);
        }

        return $token;
    }

    /**
     * Load a token by its SHA-256 hash.
     *
     * @param string $tokenHash
     * @return \Muon\PasswordlessLogin\Api\Data\TokenInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getByToken(string $tokenHash): TokenInterface
    {
        /** @var \Muon\PasswordlessLogin\Model\Token $token */
        $token = $this->tokenFactory->create();
        $this->resource->load($token, $tokenHash, TokenInterface::TOKEN);

        if (!$token->getTokenId()) {
            throw new NoSuchEntityException(__('Token not found.'));
        }

        return $token;
    }

    /**
     * Count tokens created for a customer on or after the given datetime string.
     *
     * @param int    $customerId
     * @param string $since      MySQL datetime string (Y-m-d H:i:s)
     * @return int
     */
    public function countRecentByCustomerId(int $customerId, string $since): int
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(TokenInterface::CUSTOMER_ID, $customerId);
        $collection->addFieldToFilter(TokenInterface::CREATED_AT, ['gteq' => $since]);

        return $collection->getSize();
    }

    /**
     * Atomically mark a token as used if it has not yet been consumed.
     *
     * @param string $tokenHash SHA-256 hash of the raw token
     * @param string $usedAt    MySQL datetime string (Y-m-d H:i:s)
     * @return bool
     */
    public function consumeToken(string $tokenHash, string $usedAt): bool
    {
        $connection = $this->resource->getConnection();
        $tableName  = $this->resource->getMainTable();

        $affectedRows = $connection->update(
            $tableName,
            [TokenInterface::USED_AT => $usedAt],
            [
                TokenInterface::TOKEN . ' = ?' => $tokenHash,
                'used_at IS NULL',
            ]
        );

        return $affectedRows > 0;
    }

    /**
     * Delete all unused (not yet consumed) tokens for the given customer.
     *
     * @param int $customerId
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteUnusedByCustomerId(int $customerId): void
    {
        try {
            $connection = $this->resource->getConnection();
            $tableName  = $this->resource->getMainTable();

            $connection->delete(
                $tableName,
                [
                    TokenInterface::CUSTOMER_ID . ' = ?' => $customerId,
                    'used_at IS NULL',
                ]
            );
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete unused tokens: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Delete all expired tokens and all consumed tokens.
     *
     * @return void
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteExpired(): void
    {
        try {
            $connection = $this->resource->getConnection();
            $tableName  = $this->resource->getMainTable();
            $now        = $this->dateTime->gmtDate('Y-m-d H:i:s');

            $connection->delete(
                $tableName,
                '(expires_at < ' . $connection->quote($now) . ' OR used_at IS NOT NULL)'
            );
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete expired tokens: %1', $e->getMessage()), $e);
        }
    }
}
