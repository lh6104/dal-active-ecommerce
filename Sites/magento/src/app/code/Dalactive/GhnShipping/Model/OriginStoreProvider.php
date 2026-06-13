<?php

namespace Dalactive\GhnShipping\Model;

use Dalactive\GhnShipping\Logger\Logger;
use Dalactive\StoreLocator\Model\ResourceModel\Store\CollectionFactory;

class OriginStoreProvider
{
    public function __construct(
        private readonly CollectionFactory $storeCollectionFactory,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function resolve(?int $storeId, array $destination = []): array
    {
        $stores = $this->getStores();
        $selected = null;
        $reason = 'legacy_config';

        if ($stores) {
            $requestedCode = trim((string)($destination['origin_store_code'] ?? ''));
            if ($requestedCode !== '') {
                $selected = $this->findByIdentifier($stores, $requestedCode);
                $reason = 'requested_origin_store';
            }

            if (!$selected && $this->config->isNearestOriginEnabled($storeId)) {
                $selected = $this->findNearestByCoordinates($stores, $destination);
                $reason = $selected ? 'nearest_coordinates' : $reason;
            }

            if (!$selected) {
                $selected = $this->findByDestinationArea($stores, $destination);
                $reason = $selected ? 'destination_area_match' : $reason;
            }

            if (!$selected) {
                $defaultCode = trim((string)$this->config->get('default_origin_store_code', $storeId));
                $selected = $defaultCode !== '' ? $this->findByIdentifier($stores, $defaultCode) : null;
                $reason = $selected ? 'default_origin_store' : $reason;
            }

            if (!$selected) {
                $selected = reset($stores) ?: null;
                $reason = $selected ? 'first_active_store' : $reason;
            }
        }

        if ($selected) {
            $origin = $this->normalizeStore($selected);
        } else {
            $origin = [
                'code' => 'legacy_config',
                'name' => 'Legacy GHN Origin',
                'address' => '',
                'city' => '',
                'region' => '',
                'ward' => '',
                'latitude' => null,
                'longitude' => null,
                'ghn_province_id' => 0,
                'ghn_district_id' => $this->config->getInt('from_district_id', 0, $storeId),
                'ghn_ward_code' => (string)$this->config->get('from_ward_code', $storeId),
            ];
        }

        $this->logger->info('GHN origin selected', [
            'reason' => $reason,
            'origin_code' => $origin['code'],
            'origin_name' => $origin['name'],
            'from_district_id' => $origin['ghn_district_id'],
            'from_ward_code' => $origin['ghn_ward_code'],
        ]);

        return $origin;
    }

    private function getStores(): array
    {
        $collection = $this->storeCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->setOrder('sort_order', 'ASC');
        $collection->setOrder('name', 'ASC');

        return $collection->getItems();
    }

    private function normalizeStore($store): array
    {
        return [
            'code' => (string)$store->getData('identifier'),
            'name' => (string)$store->getData('name'),
            'address' => (string)$store->getData('address'),
            'city' => (string)$store->getData('city'),
            'region' => (string)$store->getData('region'),
            'ward' => (string)$store->getData('ward'),
            'latitude' => $store->getData('latitude') !== null ? (float)$store->getData('latitude') : null,
            'longitude' => $store->getData('longitude') !== null ? (float)$store->getData('longitude') : null,
            'ghn_province_id' => (int)$store->getData('ghn_province_id'),
            'ghn_district_id' => (int)$store->getData('ghn_district_id'),
            'ghn_ward_code' => (string)$store->getData('ghn_ward_code'),
        ];
    }

    private function findByIdentifier(array $stores, string $identifier)
    {
        foreach ($stores as $store) {
            if ((string)$store->getData('identifier') === $identifier) {
                return $store;
            }
        }

        return null;
    }

    private function findByDestinationArea(array $stores, array $destination)
    {
        $provinceId = (int)($destination['ghn_province_id'] ?? 0);
        $districtId = (int)($destination['ghn_district_id'] ?? 0);
        $provinceName = $this->normalize((string)($destination['ghn_province_name'] ?? $destination['province_name'] ?? ''));
        $districtName = $this->normalize((string)($destination['ghn_district_name'] ?? $destination['district_name'] ?? ''));

        foreach ($stores as $store) {
            if ($districtId && (int)$store->getData('ghn_district_id') === $districtId) {
                return $store;
            }

            if ($provinceId && (int)$store->getData('ghn_province_id') === $provinceId) {
                return $store;
            }

            $storeCity = $this->normalize((string)$store->getData('city'));
            $storeRegion = $this->normalize((string)$store->getData('region'));
            if ($provinceName !== '' && $storeCity !== '' && $provinceName === $storeCity) {
                return $store;
            }

            if ($districtName !== '' && $storeRegion !== '' && $districtName === $storeRegion) {
                return $store;
            }
        }

        return null;
    }

    private function findNearestByCoordinates(array $stores, array $destination)
    {
        $lat = isset($destination['latitude']) ? (float)$destination['latitude'] : null;
        $lng = isset($destination['longitude']) ? (float)$destination['longitude'] : null;
        if (!$lat || !$lng) {
            return null;
        }

        $nearest = null;
        $nearestDistance = null;
        foreach ($stores as $store) {
            $storeLat = $store->getData('latitude') !== null ? (float)$store->getData('latitude') : null;
            $storeLng = $store->getData('longitude') !== null ? (float)$store->getData('longitude') : null;
            if (!$storeLat || !$storeLng) {
                continue;
            }

            $distance = $this->haversine($lat, $lng, $storeLat, $storeLng);
            if ($nearestDistance === null || $distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $store;
            }
        }

        return $nearest;
    }

    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii !== false ? $ascii : $value;
        $value = preg_replace('/\b(thanh pho|tp|tinh|quan|huyen|thi xa|phuong|xa|thi tran)\b/u', '', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?: '';

        return $value;
    }
}
