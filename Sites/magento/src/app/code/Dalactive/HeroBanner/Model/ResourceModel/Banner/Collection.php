<?php

namespace Dalactive\HeroBanner\Model\ResourceModel\Banner;

use Dalactive\HeroBanner\Model\Banner;
use Dalactive\HeroBanner\Model\ResourceModel\Banner as BannerResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Banner::class, BannerResource::class);
    }
}
