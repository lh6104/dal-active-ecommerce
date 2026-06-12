<?php

namespace Dalactive\StockAlert\Model\ResourceModel\Subscriber;

use Dalactive\StockAlert\Model\ResourceModel\Subscriber as SubscriberResource;
use Dalactive\StockAlert\Model\Subscriber;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Subscriber::class, SubscriberResource::class);
    }
}
