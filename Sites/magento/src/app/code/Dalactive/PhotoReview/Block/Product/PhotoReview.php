<?php

namespace Dalactive\PhotoReview\Block\Product;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class PhotoReview extends Template
{
    private ScopeConfigInterface $scopeConfig;

    public function __construct(Template\Context $context, ScopeConfigInterface $scopeConfig, array $data = [])
    {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('dalactive_photoreview/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getMaxImages(): int
    {
        return max(1, (int) ($this->scopeConfig->getValue('dalactive_photoreview/general/max_images', ScopeInterface::SCOPE_STORE) ?: 3));
    }

    public function getMaxImageSize(): int
    {
        return max(128, (int) ($this->scopeConfig->getValue('dalactive_photoreview/general/max_image_size', ScopeInterface::SCOPE_STORE) ?: 2048));
    }
}
