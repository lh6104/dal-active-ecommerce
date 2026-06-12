<?php

namespace Dalactive\HeroBanner\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Banner extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('dalactive_herobanner_slide', 'slide_id');
    }
}
