<?php

namespace Dalactive\StoreLocator\Model\ResourceModel\Store;

use Dalactive\StoreLocator\Model\ResourceModel\Store as StoreResource;
use Dalactive\StoreLocator\Model\Store;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Store::class, StoreResource::class);
    }
}
