<?php

namespace Dalactive\PhotoReview\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PhotoReview extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('dalactive_photoreview_image', 'image_id');
    }
}
