<?php

namespace Dalactive\HeroBanner\Model;

use Magento\Framework\Model\AbstractModel;

class Banner extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Dalactive\HeroBanner\Model\ResourceModel\Banner::class);
    }
}
