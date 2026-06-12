<?php

namespace Dalactive\StoreLocator\Block\Adminhtml;

use Dalactive\StoreLocator\Model\ResourceModel\Store\Collection;
use Dalactive\StoreLocator\Model\ResourceModel\Store\CollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class StoreList extends Template
{
    private CollectionFactory $collectionFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    public function getStores(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('entity_id', 'DESC');
        return $collection;
    }
}
