<?php

namespace Dalactive\GhnShipping\Model\Carrier;

use Dalactive\GhnShipping\Logger\Logger;
use Dalactive\GhnShipping\Model\Api\GhnClient;
use Dalactive\GhnShipping\Model\Address\GhnAddressResolver;
use Dalactive\GhnShipping\Model\Config as GhnConfig;
use Dalactive\GhnShipping\Model\OriginStoreProvider;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Psr\Log\LoggerInterface;

class Ghn extends AbstractCarrier implements CarrierInterface
{
    protected $_code = GhnConfig::CARRIER_CODE;
    protected $_isFixed = false;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly GhnConfig $ghnConfig,
        private readonly GhnClient $client,
        private readonly OriginStoreProvider $originStoreProvider,
        private readonly GhnAddressResolver $addressResolver,
        private readonly Logger $ghnLogger,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $storeId = $request->getStoreId() ? (int)$request->getStoreId() : null;
        $freeThreshold = $this->ghnConfig->getFloat('free_shipping_threshold', 0.0, $storeId);
        $packageValue = (float)$request->getPackageValueWithDiscount();

        if ($freeThreshold > 0 && $packageValue >= $freeThreshold) {
            return $this->buildRate(0.0);
        }

        try {
            $price = $this->resolveApiFee($request, $storeId);
        } catch (\Throwable $exception) {
            $price = $this->getFallbackFee($storeId);
            $this->ghnLogger->warning('GHN fallback fee used', [
                'message' => $exception->getMessage(),
                'fallback_fee' => $price,
            ]);
        }

        return $this->buildRate($price);
    }

    public function getAllowedMethods(): array
    {
        return ['express' => (string)$this->getConfigData('name')];
    }

    private function buildRate(float $price): Result
    {
        $result = $this->rateResultFactory->create();
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle((string)$this->getConfigData('title'));
        $method->setMethod('express');
        $method->setMethodTitle((string)$this->getConfigData('name'));
        $method->setPrice($price);
        $method->setCost($price);
        $result->append($method);

        return $result;
    }

    private function resolveApiFee(RateRequest $request, ?int $storeId): float
    {
        $destination = $this->addressResolver->resolveFromRateRequest($request, $storeId);
        $origin = $this->originStoreProvider->resolve($storeId, $destination);

        $fromDistrict = (int)($origin['ghn_district_id'] ?? 0);
        $fromWard = (string)($origin['ghn_ward_code'] ?? '');
        $toDistrict = (int)($destination['ghn_district_id'] ?? 0);
        $toWard = (string)($destination['ghn_ward_code'] ?? '');
        $shopId = $this->ghnConfig->getInt('shop_id', 0, $storeId);

        if (!$fromDistrict || !$fromWard || !$toDistrict || !$toWard || !$shopId) {
            throw new \RuntimeException('GHN origin/destination mapping is incomplete.');
        }

        $serviceId = $this->ghnConfig->getInt('default_service_id', 0, $storeId);
        $serviceTypeId = $this->ghnConfig->getInt('default_service_type_id', 2, $storeId);

        if (!$serviceId) {
            $services = $this->client->getAvailableServices([
                'shop_id' => $shopId,
                'from_district' => $fromDistrict,
                'to_district' => $toDistrict,
            ], $storeId);
            $service = $this->pickService($services['data'] ?? [], $serviceTypeId);
            $serviceId = (int)($service['service_id'] ?? 0);
            $serviceTypeId = (int)($service['service_type_id'] ?? $serviceTypeId);
        }

        $dimensions = $this->getDefaultDimensions($storeId);
        $payload = [
            'from_district_id' => $fromDistrict,
            'from_ward_code' => $fromWard,
            'to_district_id' => $toDistrict,
            'to_ward_code' => $toWard,
            'height' => $dimensions['height'],
            'length' => $dimensions['length'],
            'weight' => $this->getPackageWeightGrams($request, $storeId),
            'width' => $dimensions['width'],
            'insurance_value' => max(0, (int)round((float)$request->getPackageValueWithDiscount())),
            'items' => $this->buildItems($request, $storeId),
        ];

        if ($serviceId) {
            $payload['service_id'] = $serviceId;
        } else {
            $payload['service_type_id'] = $serviceTypeId;
        }

        $response = $this->client->calculateFee($payload, $storeId);
        $data = $response['data'] ?? [];
        $fee = $data['total'] ?? $data['service_fee'] ?? null;

        if ($fee === null) {
            throw new \RuntimeException('GHN fee response did not include total/service_fee.');
        }

        $this->ghnLogger->info('GHN fee resolved', [
            'origin_code' => $origin['code'] ?? null,
            'from_district_id' => $fromDistrict,
            'from_ward_code' => $fromWard,
            'to_district_id' => $toDistrict,
            'to_ward_code' => $toWard,
            'service_id' => $serviceId,
            'service_type_id' => $serviceTypeId,
            'fee' => $fee,
        ]);

        return (float)$fee;
    }

    private function pickService(array $services, int $preferredServiceTypeId): array
    {
        foreach ($services as $service) {
            if ((int)($service['service_type_id'] ?? 0) === $preferredServiceTypeId) {
                return $service;
            }
        }

        foreach ($services as $service) {
            if ((int)($service['service_type_id'] ?? 0) === 2) {
                return $service;
            }
        }

        return $services[0] ?? [];
    }

    private function getPackageWeightGrams(RateRequest $request, ?int $storeId): int
    {
        $defaultWeight = $this->ghnConfig->getInt('default_weight', 500, $storeId);
        $weight = 0;

        foreach ((array)$request->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $itemWeight = (float)($item->getWeight() ?: 0);
            $weight += (int)max($defaultWeight, round($itemWeight > 0 ? $itemWeight * 1000 : $defaultWeight)) * (int)$item->getQty();
        }

        return max($defaultWeight, $weight);
    }

    private function buildItems(RateRequest $request, ?int $storeId): array
    {
        $items = [];
        $dimensions = $this->getDefaultDimensions($storeId);
        $defaultWeight = $this->ghnConfig->getInt('default_weight', 500, $storeId);

        foreach ((array)$request->getAllItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            $weight = (float)($item->getWeight() ?: 0);
            $items[] = [
                'name' => mb_substr((string)$item->getName(), 0, 120),
                'quantity' => (int)$item->getQty(),
                'weight' => max($defaultWeight, (int)round($weight > 0 ? $weight * 1000 : $defaultWeight)),
                'height' => $dimensions['height'],
                'length' => $dimensions['length'],
                'width' => $dimensions['width'],
            ];
        }

        return $items;
    }

    private function getDefaultDimensions(?int $storeId): array
    {
        return [
            'length' => max(1, $this->ghnConfig->getInt('default_length', 30, $storeId)),
            'width' => max(1, $this->ghnConfig->getInt('default_width', 20, $storeId)),
            'height' => max(1, $this->ghnConfig->getInt('default_height', 12, $storeId)),
        ];
    }

    private function getFallbackFee(?int $storeId): float
    {
        return max(0, $this->ghnConfig->getFloat('fallback_fee', 35000.0, $storeId));
    }

}
