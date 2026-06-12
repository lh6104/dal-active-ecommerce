<?php

namespace Dalactive\StockAlert\Model;

use Magento\Framework\Model\AbstractModel;

class Subscriber extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Dalactive\StockAlert\Model\ResourceModel\Subscriber::class);
    }
}
