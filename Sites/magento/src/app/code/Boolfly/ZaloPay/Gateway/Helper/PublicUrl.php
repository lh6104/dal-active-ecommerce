<?php
/************************************************************
 * Copyright © Boolfly. All rights reserved.
 * See COPYING.txt for license details.
 ************************************************************/

namespace Boolfly\ZaloPay\Gateway\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class PublicUrl
{
    private const CONFIG_PATH = 'payment/zalopay/public_base_url';
    private const FALLBACK_PUBLIC_URL = 'https://cobalt-mulch-update.ngrok-free.dev/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        $configured = trim((string)$this->scopeConfig->getValue(
            self::CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        $env = trim((string)getenv('PAYMENT_PUBLIC_BASE_URL'));
        $baseUrl = $configured !== '' ? $configured : ($env !== '' ? $env : self::FALLBACK_PUBLIC_URL);

        return rtrim($baseUrl, '/') . '/';
    }

    public function getRouteUrl(string $path, ?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . ltrim($path, '/');
    }
}
