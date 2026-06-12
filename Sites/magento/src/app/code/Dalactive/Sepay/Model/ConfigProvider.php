<?php

namespace Dalactive\Sepay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly SepayConfig $config,
        private readonly CheckoutSession $checkoutSession,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function getConfig(): array
    {
        $quote = $this->checkoutSession->getQuote();
        $storeId = (int) $quote->getStoreId();
        $amount = (float) $quote->getGrandTotal();
        $memo = 'DH' . ($quote->getReservedOrderId() ?: 'ORDER');

        return [
            'payment' => [
                'sepay' => [
                    'bankCode' => $this->config->getValue('bank_code', $storeId),
                    'bankDisplayName' => $this->config->getValue('bank_display_name', $storeId),
                    'accountNo' => $this->config->getValue('account_no', $storeId),
                    'accountName' => $this->config->getValue('account_name', $storeId),
                    'configured' => $this->config->isConfigured($storeId),
                    'previewQrUrl' => $this->config->isConfigured($storeId)
                        ? $this->config->getQrUrl($amount, $memo, $storeId)
                        : '',
                    'payUrl' => $this->urlBuilder->getUrl('sepay/payment/pay'),
                ],
            ],
        ];
    }
}
