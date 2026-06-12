<?php

namespace Dalactive\HeroBanner\Block\Adminhtml;

use Dalactive\HeroBanner\Model\ResourceModel\Banner\Collection;
use Dalactive\HeroBanner\Model\ResourceModel\Banner\CollectionFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class BannerList extends Template
{
    private CollectionFactory $collectionFactory;

    public function __construct(Context $context, CollectionFactory $collectionFactory, array $data = [])
    {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    public function getBanners(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('sort_order', 'ASC');
        return $collection;
    }
}
