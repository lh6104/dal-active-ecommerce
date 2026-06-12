<?php

namespace Dalactive\SizeGuide\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Chart extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('dalactive_sizeguide_chart', 'chart_id');
    }
}
