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
        $secureBaseUrl = trim((string)$this->scopeConfig->getValue(
            'web/secure/base_url',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $unsecureBaseUrl = trim((string)$this->scopeConfig->getValue(
            'web/unsecure/base_url',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        $baseUrl = $configured !== ''
            ? $configured
            : ($env !== '' ? $env : ($secureBaseUrl !== '' ? $secureBaseUrl : $unsecureBaseUrl));

        return rtrim($baseUrl, '/') . '/';
    }

    public function getRouteUrl(string $path, ?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . ltrim($path, '/');
    }
}
