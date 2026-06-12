<?php

namespace Dalactive\HeroBanner\Block;

use Dalactive\HeroBanner\Model\ResourceModel\Banner\Collection;
use Dalactive\HeroBanner\Model\ResourceModel\Banner\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Hero extends Template
{
    private ScopeConfigInterface $scopeConfig;
    private CollectionFactory $collectionFactory;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('dalactive_herobanner/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getSlides(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', 1);
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('slide_id', 'ASC');
        return $collection;
    }

    public function getMediaUrl(string $path): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/')
            . '/' . ltrim($path, '/');
    }

    public function getSettingsJson(): string
    {
        $settings = [
            'autoplay' => $this->scopeConfig->isSetFlag('dalactive_herobanner/general/autoplay', ScopeInterface::SCOPE_STORE),
            'interval' => max(1000, (int) ($this->scopeConfig->getValue('dalactive_herobanner/general/interval', ScopeInterface::SCOPE_STORE) ?: 6000)),
            'showArrows' => $this->scopeConfig->isSetFlag('dalactive_herobanner/general/show_arrows', ScopeInterface::SCOPE_STORE),
            'showDots' => $this->scopeConfig->isSetFlag('dalactive_herobanner/general/show_dots', ScopeInterface::SCOPE_STORE),
            'pauseButton' => $this->scopeConfig->isSetFlag('dalactive_herobanner/general/pause_button', ScopeInterface::SCOPE_STORE),
        ];

        return (string) json_encode($settings);
    }
}
