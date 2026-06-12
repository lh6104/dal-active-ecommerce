<?php

namespace Dalactive\Brand\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Brand extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('dalactive_brand', 'brand_id');
    }
}
