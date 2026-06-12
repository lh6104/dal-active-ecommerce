<?php

namespace Dalactive\PhotoReview\Model;

use Magento\Framework\Model\AbstractModel;

class PhotoReview extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Dalactive\PhotoReview\Model\ResourceModel\PhotoReview::class);
    }
}
