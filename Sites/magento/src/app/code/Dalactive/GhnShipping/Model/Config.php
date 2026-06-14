<?php

namespace Dalactive\GhnShipping\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const CARRIER_CODE = 'ghn';
    private const PATH = 'carriers/ghn/';

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

    public function isAutoMappingEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enable_auto_mapping', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isNearestOriginEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enable_nearest_origin', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isNameResolverEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enable_name_resolver', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isCheckoutDropdownsEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enable_checkout_dropdowns', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function useFallbackOnApiFailure(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'use_fallback_on_api_failure', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getToken(?int $storeId = null): string
    {
        $value = trim((string)$this->get('token', $storeId));

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
        $environment = $this->get('environment', $storeId) ?: 'sandbox';
        $field = $environment === 'production' ? 'production_base_url' : 'sandbox_base_url';

        return $this->normalizeBaseUrl((string)$this->get($field, $storeId));
    }

    public function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        return preg_replace('#/v2$#', '', $baseUrl) ?: $baseUrl;
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

    public function getCacheTtl(?int $storeId = null): int
    {
        return max(300, $this->getInt('address_cache_ttl', 86400, $storeId));
    }
}
