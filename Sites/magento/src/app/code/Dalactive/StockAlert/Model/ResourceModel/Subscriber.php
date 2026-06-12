<?php

namespace Dalactive\StockAlert\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Subscriber extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('dalactive_stockalert_subscriber', 'subscriber_id');
    }
}
