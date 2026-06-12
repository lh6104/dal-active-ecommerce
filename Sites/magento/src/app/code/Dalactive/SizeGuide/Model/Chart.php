<?php

namespace Dalactive\SizeGuide\Model;

use Magento\Framework\Model\AbstractModel;

class Chart extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Dalactive\SizeGuide\Model\ResourceModel\Chart::class);
    }
}
