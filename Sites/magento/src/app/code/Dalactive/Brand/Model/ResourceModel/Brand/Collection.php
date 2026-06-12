<?php

namespace Dalactive\Brand\Model\ResourceModel\Brand;

use Dalactive\Brand\Model\Brand;
use Dalactive\Brand\Model\ResourceModel\Brand as BrandResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Brand::class, BrandResource::class);
    }
}
