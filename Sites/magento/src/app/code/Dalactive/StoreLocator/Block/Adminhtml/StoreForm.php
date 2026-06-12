<?php

namespace Dalactive\StoreLocator\Block\Adminhtml;

use Dalactive\StoreLocator\Model\Store;
use Dalactive\StoreLocator\Model\StoreFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class StoreForm extends Template
{
    private StoreFactory $storeFactory;
    private ?Store $store = null;

    public function __construct(
        Context $context,
        StoreFactory $storeFactory,
        array $data = []
    ) {
        $this->storeFactory = $storeFactory;
        parent::__construct($context, $data);
    }

    public function getStore(): Store
    {
        if ($this->store === null) {
            $this->store = $this->storeFactory->create();
            $entityId = (int) $this->getRequest()->getParam('entity_id');
            if ($entityId > 0) {
                $this->store->load($entityId);
            }
        }

        return $this->store;
    }
}
