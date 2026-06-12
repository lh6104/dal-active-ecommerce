<?php

namespace Dalactive\StoreLocator\Block;

use Dalactive\StoreLocator\Model\ResourceModel\Store\Collection;
use Dalactive\StoreLocator\Model\ResourceModel\Store\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class StoreList extends Template
{
    private ScopeConfigInterface $scopeConfig;
    private CollectionFactory $collectionFactory;
    private Json $json;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $collectionFactory,
        Json $json,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->collectionFactory = $collectionFactory;
        $this->json = $json;
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag('dalactive_storelocator/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getStores(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('name', 'ASC');
        return $collection;
    }

    public function getStoresJson(): string
    {
        $stores = [];
        foreach ($this->getStores() as $store) {
            $latitude = $store->getData('latitude') !== null && $store->getData('latitude') !== ''
                ? (float) $store->getData('latitude')
                : null;
            $longitude = $store->getData('longitude') !== null && $store->getData('longitude') !== ''
                ? (float) $store->getData('longitude')
                : null;
            $directionsUrl = (string) $store->getData('google_maps_url');
            if ($directionsUrl === '' && $latitude !== null && $longitude !== null) {
                $directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $latitude . ',' . $longitude;
            }

            $stores[] = [
                'id' => (int) $store->getId(),
                'name' => (string) $store->getData('name'),
                'identifier' => (string) $store->getData('identifier'),
                'address' => (string) $store->getData('address'),
                'city' => (string) $store->getData('city'),
                'region' => (string) $store->getData('region'),
                'country' => (string) $store->getData('country'),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'directionsUrl' => $directionsUrl,
                'phone' => (string) $store->getData('phone'),
                'email' => (string) $store->getData('email'),
                'openingHours' => (string) $store->getData('opening_hours'),
            ];
        }

        return $this->json->serialize($stores);
    }
}
