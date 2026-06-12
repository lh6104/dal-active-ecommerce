<?php

namespace Dalactive\PhotoReview\Model\ResourceModel\PhotoReview;

use Dalactive\PhotoReview\Model\PhotoReview;
use Dalactive\PhotoReview\Model\ResourceModel\PhotoReview as PhotoReviewResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(PhotoReview::class, PhotoReviewResource::class);
    }
}
