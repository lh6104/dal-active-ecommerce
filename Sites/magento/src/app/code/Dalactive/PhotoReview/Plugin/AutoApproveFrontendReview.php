<?php

declare(strict_types=1);

namespace Dalactive\PhotoReview\Plugin;

use Magento\Review\Model\Review;

class AutoApproveFrontendReview
{
    public function beforeSave(Review $subject): void
    {
        if (!$subject->getId() && (int) $subject->getStatusId() === Review::STATUS_PENDING) {
            $subject->setStatusId(Review::STATUS_APPROVED);
        }
    }
}
