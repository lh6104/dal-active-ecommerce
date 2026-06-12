<?php

namespace Dalactive\StockAlert\Block\Product;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class Subscribe extends Template
{
    private ScopeConfigInterface $scopeConfig;
    private Registry $registry;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        Registry $registry,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    public function canShow(): bool
    {
        $product = $this->getProduct();
        return $this->scopeConfig->isSetFlag('dalactive_stockalert/general/enabled', ScopeInterface::SCOPE_STORE)
            && $product instanceof Product
            && !$product->isSaleable();
    }

    public function getProduct(): ?Product
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    public function getPostUrl(): string
    {
        return $this->getUrl('stock-alert/subscribe/save');
    }
}
