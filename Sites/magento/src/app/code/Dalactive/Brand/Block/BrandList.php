<?php

namespace Dalactive\Brand\Block;

use Dalactive\Brand\Model\ResourceModel\Brand\Collection;
use Dalactive\Brand\Model\ResourceModel\Brand\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class BrandList extends Template
{
    private ScopeConfigInterface $scopeConfig;
    private CollectionFactory $collectionFactory;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('dalactive_brand/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getBrands(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', 1);
        $collection->setOrder('name', 'ASC');
        return $collection;
    }
}
