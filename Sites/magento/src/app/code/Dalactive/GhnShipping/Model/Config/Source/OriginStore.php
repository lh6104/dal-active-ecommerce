<?php

namespace Dalactive\GhnShipping\Model\Config\Source;

use Dalactive\StoreLocator\Model\ResourceModel\Store\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

class OriginStore implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('First active store')]];
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('name', 'ASC');

        foreach ($collection as $store) {
            $options[] = [
                'value' => (string)$store->getData('identifier'),
                'label' => sprintf('%s (%s)', (string)$store->getData('name'), (string)$store->getData('identifier')),
            ];
        }

        return $options;
    }
}
