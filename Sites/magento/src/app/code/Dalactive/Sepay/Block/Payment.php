<?php

namespace Dalactive\Sepay\Block;

use Dalactive\Sepay\Model\SepayConfig;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

class Payment extends Template
{
    private ?OrderInterface $order = null;

    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SepayConfig $sepayConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        if ($this->order === null) {
            $order = $this->registry->registry('current_order');
            if ($order instanceof OrderInterface) {
                $this->order = $order;
            }
        }

        return $this->order;
    }

    public function getQrUrl(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }

        return $this->sepayConfig->getQrUrl(
            (float) $order->getGrandTotal(),
            $this->getMemo(),
            (int) $order->getStoreId()
        );
    }

    public function getMemo(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }

        return $this->sepayConfig->getMemo((string) $order->getIncrementId(), (int) $order->getStoreId());
    }

    public function getTimeout(): int
    {
        $order = $this->getOrder();

        return $this->sepayConfig->getTimeout($order ? (int) $order->getStoreId() : null);
    }

    public function getStatusUrl(): string
    {
        $order = $this->getOrder();

        return $this->getUrl('sepay/payment/status', ['order_id' => $order ? $order->getEntityId() : 0]);
    }

    public function getFailUrl(): string
    {
        $order = $this->getOrder();

        return $this->getUrl('sepay/payment/fail', ['order_id' => $order ? $order->getEntityId() : 0]);
    }

    public function getSuccessUrl(): string
    {
        return $this->getUrl('checkout/onepage/success');
    }

    public function getBankDisplayName(): string
    {
        $order = $this->getOrder();

        return trim((string) $this->sepayConfig->getValue('bank_display_name', $order ? (int) $order->getStoreId() : null));
    }

    public function getBankCode(): string
    {
        $order = $this->getOrder();

        return trim((string) $this->sepayConfig->getValue('bank_code', $order ? (int) $order->getStoreId() : null));
    }

    public function getAccountNo(): string
    {
        $order = $this->getOrder();

        return trim((string) $this->sepayConfig->getValue('account_no', $order ? (int) $order->getStoreId() : null));
    }

    public function getAccountName(): string
    {
        $order = $this->getOrder();

        return trim((string) $this->sepayConfig->getValue('account_name', $order ? (int) $order->getStoreId() : null));
    }

    public function getContinueShoppingUrl(): string
    {
        return $this->getUrl('');
    }

    public function getChangePaymentUrl(): string
    {
        return $this->getUrl('checkout/cart');
    }
}
