<?php
declare(strict_types=1);

namespace Muon\PasswordlessLogin\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED        = 'muon_passwordlesslogin/general/enabled';
    private const XML_PATH_TOKEN_LIFETIME = 'muon_passwordlesslogin/general/token_lifetime';
    private const XML_PATH_MAX_ATTEMPTS   = 'muon_passwordlesslogin/general/max_attempts';

    private const DEFAULT_TOKEN_LIFETIME = 15;
    private const DEFAULT_MAX_ATTEMPTS   = 5;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Check whether the passwordless login feature is enabled.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return bool
     */
    public function isEnabled(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): bool {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, $scopeType, $scopeCode);
    }

    /**
     * Get the magic link token lifetime in minutes.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return int
     */
    public function getTokenLifetime(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): int {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_TOKEN_LIFETIME, $scopeType, $scopeCode);
        return $value > 0 ? $value : self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Get the maximum number of magic link requests allowed per email per hour.
     *
     * @param string      $scopeType
     * @param string|null $scopeCode
     * @return int
     */
    public function getMaxAttempts(
        string $scopeType = ScopeInterface::SCOPE_STORE,
        ?string $scopeCode = null
    ): int {
        $value = (int) $this->scopeConfig->getValue(self::XML_PATH_MAX_ATTEMPTS, $scopeType, $scopeCode);
        return $value > 0 ? $value : self::DEFAULT_MAX_ATTEMPTS;
    }
}
