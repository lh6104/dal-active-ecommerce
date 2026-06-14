<?php

namespace Dalactive\GhnShipping\Model\Address;

use Dalactive\GhnShipping\Logger\Logger;
use Dalactive\GhnShipping\Model\Api\GhnClient;
use Dalactive\GhnShipping\Model\Config;
use Magento\Quote\Model\Quote\Address\RateRequest;

class GhnAddressResolver
{
    public function __construct(
        private readonly GhnClient $client,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function resolveFromRateRequest(RateRequest $request, ?int $storeId): array
    {
        $destination = $this->extractAddressData($request);
        $reason = 'address_fields';

        if (empty($destination['ghn_district_id']) || empty($destination['ghn_ward_code'])) {
            $mapped = $this->resolveFromNames($destination, $storeId);
            if ($mapped) {
                $destination = array_replace($destination, $mapped);
                $reason = 'name_resolver';
            }
        }

        $this->logger->info('GHN destination resolved', [
            'reason' => $reason,
            'province_id' => $destination['ghn_province_id'] ?? null,
            'district_id' => $destination['ghn_district_id'] ?? null,
            'ward_code' => $destination['ghn_ward_code'] ?? null,
            'province_name' => $destination['ghn_province_name'] ?? $destination['province_name'] ?? null,
            'district_name' => $destination['ghn_district_name'] ?? $destination['district_name'] ?? null,
            'ward_name' => $destination['ghn_ward_name'] ?? $destination['ward_name'] ?? null,
        ]);

        return $destination;
    }

    private function extractAddressData(RateRequest $request): array
    {
        $street = $request->getDestStreet();
        if (is_array($street)) {
            $street = implode(' ', $street);
        }

        $data = [
            'ghn_province_id' => $this->extractInt($request, 'ghn_province_id'),
            'ghn_district_id' => $this->extractInt($request, 'ghn_district_id'),
            'ghn_ward_code' => $this->extractString($request, 'ghn_ward_code'),
            'ghn_province_name' => $this->extractString($request, 'ghn_province_name'),
            'ghn_district_name' => $this->extractString($request, 'ghn_district_name'),
            'ghn_ward_name' => $this->extractString($request, 'ghn_ward_name'),
            'province_name' => (string)($request->getDestRegion() ?: ''),
            'district_name' => (string)($request->getDestCity() ?: ''),
            'street' => (string)($street ?: ''),
            'postcode' => (string)($request->getDestPostcode() ?: ''),
            'country_id' => (string)($request->getDestCountryId() ?: ''),
        ];

        foreach (['latitude', 'longitude', 'origin_store_code'] as $key) {
            $value = $request->getData($key);
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    private function resolveFromNames(array $destination, ?int $storeId): array
    {
        if (!$this->config->isAutoMappingEnabled($storeId) || !$this->config->isNameResolverEnabled($storeId)) {
            return [];
        }

        try {
            $province = $this->findProvince($destination, $storeId);
            if (!$province) {
                return [];
            }

            $district = $this->findDistrict($province, $destination, $storeId);
            if (!$district) {
                return [];
            }

            $ward = $this->findWard($district, $destination, $storeId);
            if (!$ward) {
                return [];
            }

            return [
                'ghn_province_id' => (int)$province['ProvinceID'],
                'ghn_province_name' => (string)$province['ProvinceName'],
                'ghn_district_id' => (int)$district['DistrictID'],
                'ghn_district_name' => (string)$district['DistrictName'],
                'ghn_ward_code' => (string)$ward['WardCode'],
                'ghn_ward_name' => (string)$ward['WardName'],
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning('GHN name resolver failed', [
                'message' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    private function findProvince(array $destination, ?int $storeId): ?array
    {
        $provinceId = (int)($destination['ghn_province_id'] ?? 0);
        $candidates = [
            $destination['ghn_province_name'] ?? '',
            $destination['province_name'] ?? '',
            $destination['district_name'] ?? '',
        ];

        $response = $this->client->getProvinces($storeId);
        foreach (($response['data'] ?? []) as $province) {
            if ($provinceId && (int)($province['ProvinceID'] ?? 0) === $provinceId) {
                return $province;
            }

            if ($this->matchesAny((string)($province['ProvinceName'] ?? ''), $candidates)) {
                return $province;
            }
        }

        return null;
    }

    private function findDistrict(array $province, array $destination, ?int $storeId): ?array
    {
        $districtId = (int)($destination['ghn_district_id'] ?? 0);
        $candidates = [
            $destination['ghn_district_name'] ?? '',
            $destination['district_name'] ?? '',
            $destination['province_name'] ?? '',
            $destination['street'] ?? '',
        ];

        $response = $this->client->getDistricts((int)$province['ProvinceID'], $storeId);
        foreach (($response['data'] ?? []) as $district) {
            if ($districtId && (int)($district['DistrictID'] ?? 0) === $districtId) {
                return $district;
            }

            if ($this->matchesAny((string)($district['DistrictName'] ?? ''), $candidates, true)) {
                return $district;
            }
        }

        return null;
    }

    private function findWard(array $district, array $destination, ?int $storeId): ?array
    {
        $wardCode = trim((string)($destination['ghn_ward_code'] ?? ''));
        $streetWard = $this->extractWardName((string)($destination['street'] ?? ''));
        $candidates = [
            $destination['ghn_ward_name'] ?? '',
            $destination['street'] ?? '',
            $streetWard,
        ];

        $response = $this->client->getWards((int)$district['DistrictID'], $storeId);
        foreach (($response['data'] ?? []) as $ward) {
            if ($wardCode !== '' && (string)($ward['WardCode'] ?? '') === $wardCode) {
                return $ward;
            }

            if ($this->matchesAny((string)($ward['WardName'] ?? ''), $candidates, true)) {
                return $ward;
            }
        }

        return null;
    }

    private function extractInt(RateRequest $request, string $key): int
    {
        return (int)$this->extractString($request, $key);
    }

    private function extractString(RateRequest $request, string $key): string
    {
        $value = $request->getData($key);
        if ($value !== null && $value !== '') {
            return trim((string)$value);
        }

        $ext = $request->getExtensionAttributes();
        $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
        if ($ext && method_exists($ext, $method)) {
            return trim((string)$ext->{$method}());
        }

        return '';
    }

    private function matchesAny(string $needle, array $candidates, bool $contains = false): bool
    {
        $needle = $this->normalize($needle);
        if ($needle === '') {
            return false;
        }

        foreach ($candidates as $candidate) {
            $candidate = $this->normalize((string)$candidate);
            if ($candidate === '') {
                continue;
            }

            if ($candidate === $needle || $contains && (str_contains($candidate, $needle) || str_contains($needle, $candidate))) {
                return true;
            }
        }

        return false;
    }

    private function extractWardName(string $street): string
    {
        if (preg_match('/(?:phuong|phường|xa|xã|thi tran|thị trấn)\s+([^,]+)/iu', $street, $matches)) {
            return trim($matches[0]);
        }

        return '';
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
