<?php

namespace Dalactive\StoreLocator\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Store extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('dalactive_storelocator_store', 'entity_id');
    }
}
