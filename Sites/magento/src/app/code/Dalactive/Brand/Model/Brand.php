<?php

namespace Dalactive\Brand\Model;

use Magento\Framework\Model\AbstractModel;

class Brand extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Dalactive\Brand\Model\ResourceModel\Brand::class);
    }
}
