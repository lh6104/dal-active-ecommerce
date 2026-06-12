<?php

namespace Dalactive\SizeGuide\Model\ResourceModel\Chart;

use Dalactive\SizeGuide\Model\Chart;
use Dalactive\SizeGuide\Model\ResourceModel\Chart as ChartResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Chart::class, ChartResource::class);
    }
}
