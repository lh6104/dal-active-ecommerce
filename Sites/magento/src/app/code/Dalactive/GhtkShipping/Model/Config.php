<?php

namespace Dalactive\GhtkShipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const CARRIER_CODE = 'ghtk';
    private const PATH = 'carriers/ghtk/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function get(string $field, ?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(self::PATH . $field, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === null ? null : (string)$value;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'active', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isDebug(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'debug', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function shouldCreateOrder(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'create_order_after_place', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function shouldHideUnsupported(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'hide_when_unsupported', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getToken(?int $storeId = null): string
    {
        return $this->getSecret('token', $storeId);
    }

    public function getWebhookHash(?int $storeId = null): string
    {
        return $this->getSecret('webhook_hash', $storeId);
    }

    public function getSecret(string $field, ?int $storeId = null): string
    {
        $value = trim((string)$this->get($field, $storeId));
        if ($value === '') {
            return '';
        }

        try {
            $decrypted = $this->encryptor->decrypt($value);
            return trim($decrypted ?: $value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        $environment = $this->get('environment', $storeId) ?: 'staging';
        $field = $environment === 'production' ? 'production_base_url' : 'staging_base_url';

        return rtrim((string)$this->get($field, $storeId), '/');
    }

    public function getInt(string $field, int $default = 0, ?int $storeId = null): int
    {
        $value = $this->get($field, $storeId);
        return $value === null || $value === '' ? $default : (int)$value;
    }

    public function getFloat(string $field, float $default = 0.0, ?int $storeId = null): float
    {
        $value = $this->get($field, $storeId);
        return $value === null || $value === '' ? $default : (float)$value;
    }
}
