<?php

namespace Dalactive\StoreLocator\Model;

use Magento\Framework\Model\AbstractModel;

class Store extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Dalactive\StoreLocator\Model\ResourceModel\Store::class);
    }
}
