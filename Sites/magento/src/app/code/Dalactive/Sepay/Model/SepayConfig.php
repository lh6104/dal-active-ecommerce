<?php

namespace Dalactive\Sepay\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class SepayConfig
{
    private const PATH_PREFIX = 'payment/sepay/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function getValue(string $field, ?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::PATH_PREFIX . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value !== null ? (string) $value : null;
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_PREFIX . 'active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getWebhookSecret(?int $storeId = null): string
    {
        $secret = (string) $this->getValue('webhook_secret', $storeId);

        return $secret !== '' ? (string) $this->encryptor->decrypt($secret) : '';
    }

    public function getMemo(string $incrementId, ?int $storeId = null): string
    {
        $prefix = trim((string) ($this->getValue('memo_prefix', $storeId) ?: 'DH'));

        return $prefix . $incrementId;
    }

    public function getTimeout(?int $storeId = null): int
    {
        $timeout = (int) $this->getValue('payment_timeout', $storeId);

        return $timeout > 0 ? $timeout : 900;
    }

    public function getQrUrl(float $amount, string $memo, ?int $storeId = null): string
    {
        $baseUrl = $this->getValue('qr_base_url', $storeId) ?: 'https://qr.sepay.vn/img';
        $query = http_build_query([
            'acc' => $this->getValue('account_no', $storeId),
            'bank' => $this->getValue('bank_code', $storeId),
            'amount' => (int) round($amount),
            'des' => $memo,
        ]);

        return rtrim($baseUrl, '?') . '?' . $query;
    }

    public function isConfigured(?int $storeId = null): bool
    {
        return trim((string) $this->getValue('account_no', $storeId)) !== ''
            && trim((string) $this->getValue('bank_code', $storeId)) !== '';
    }
}
