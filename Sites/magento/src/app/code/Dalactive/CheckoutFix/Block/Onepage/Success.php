<?php

namespace Dalactive\CheckoutFix\Block\Onepage;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Checkout\Block\Onepage\Success as MagentoSuccess;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Context;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Item as OrderItem;
use OutOfBoundsException;

class Success extends MagentoSuccess
{
    public function __construct(
        TemplateContext $context,
        CheckoutSession $checkoutSession,
        OrderConfig $orderConfig,
        HttpContext $httpContext,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
    }

    public function getOrder(): ?Order
    {
        $order = $this->_checkoutSession->getLastRealOrder();

        return $order && $order->getEntityId() ? $order : null;
    }

    public function getAdditionalInfoHtml()
    {
        try {
            return parent::getAdditionalInfoHtml();
        } catch (OutOfBoundsException) {
            return '';
        }
    }

    public function canViewCurrentOrder(): bool
    {
        $order = $this->getOrder();

        return $order
            && $this->httpContext->getValue(Context::CONTEXT_AUTH)
            && !in_array($order->getStatus(), $this->_orderConfig->getInvisibleOnFrontStatuses(), true);
    }

    public function formatOrderPrice(float $amount): string
    {
        $order = $this->getOrder();

        return $order ? trim(strip_tags((string) $order->formatPrice($amount))) : '';
    }

    public function getPaymentTitle(): string
    {
        $order = $this->getOrder();
        $payment = $order ? $order->getPayment() : null;

        return $payment && $payment->getMethodInstance()
            ? (string) $payment->getMethodInstance()->getTitle()
            : '';
    }

    public function getShippingDescription(): string
    {
        $order = $this->getOrder();

        return $order ? (string) $order->getShippingDescription() : '';
    }

    /**
     * @return OrderItem[]
     */
    public function getVisibleItems(): array
    {
        $order = $this->getOrder();

        return $order ? $order->getAllVisibleItems() : [];
    }

    public function getItemImageUrl(OrderItem $item): string
    {
        try {
            $product = $this->productRepository->getById((int) $item->getProductId());

            return $this->imageHelper->init($product, 'product_thumbnail_image')->getUrl();
        } catch (NoSuchEntityException) {
            return $this->getViewFileUrl('Magento_Catalog::images/product/placeholder/thumbnail.jpg');
        }
    }

    public function getItemOptionsText(OrderItem $item): string
    {
        $options = [];
        $orderOptions = $item->getProductOptions();

        foreach ((array) ($orderOptions['attributes_info'] ?? []) as $option) {
            if (!empty($option['label']) && isset($option['value'])) {
                $options[] = trim((string) $option['label']) . ': ' . trim((string) $option['value']);
            }
        }

        return implode(', ', $options);
    }

    public function getCustomerDisplayName(): string
    {
        $order = $this->getOrder();

        return $order ? trim((string) $order->getCustomerName()) : '';
    }

    public function getSupportEmail(): string
    {
        return (string) $this->_scopeConfig->getValue('trans_email/ident_support/email');
    }

    public function getSupportPhone(): string
    {
        return (string) $this->_scopeConfig->getValue('general/store_information/phone');
    }
}
