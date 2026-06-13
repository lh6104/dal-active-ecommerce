<?php

namespace Dalactive\GhtkShipping\Model\Carrier;

use Dalactive\GhtkShipping\Logger\Logger;
use Dalactive\GhtkShipping\Model\Api\GhtkClient;
use Dalactive\GhtkShipping\Model\Config as GhtkConfig;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Psr\Log\LoggerInterface;

class Ghtk extends AbstractCarrier implements CarrierInterface
{
    protected $_code = GhtkConfig::CARRIER_CODE;
    protected $_isFixed = false;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly GhtkConfig $ghtkConfig,
        private readonly GhtkClient $client,
        private readonly Logger $ghtkLogger,
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
        $freeThreshold = $this->ghtkConfig->getFloat('free_shipping_threshold', 0.0, $storeId);
        $packageValue = (float)$request->getPackageValueWithDiscount();

        if ($freeThreshold > 0 && $packageValue >= $freeThreshold) {
            return $this->buildRate(0.0);
        }

        try {
            $price = $this->resolveApiFee($request, $storeId);
            if ($price === null && $this->ghtkConfig->shouldHideUnsupported($storeId)) {
                return false;
            }
            $price ??= $this->getFallbackFee($storeId);
        } catch (\Throwable $exception) {
            $price = $this->getFallbackFee($storeId);
            $this->ghtkLogger->warning('GHTK fallback fee used', [
                'message' => $exception->getMessage(),
                'fallback_fee' => $price,
            ]);
        }

        return $this->buildRate((float)$price);
    }

    public function getAllowedMethods(): array
    {
        return ['standard' => (string)$this->getConfigData('name')];
    }

    private function buildRate(float $price): Result
    {
        $result = $this->rateResultFactory->create();
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle((string)$this->getConfigData('title'));
        $method->setMethod('standard');
        $method->setMethodTitle((string)$this->getConfigData('name'));
        $method->setPrice($price);
        $method->setCost($price);
        $result->append($method);

        return $result;
    }

    private function resolveApiFee(RateRequest $request, ?int $storeId): ?float
    {
        $query = [
            'pick_province' => (string)$this->ghtkConfig->get('pick_province', $storeId),
            'pick_district' => (string)$this->ghtkConfig->get('pick_district', $storeId),
            'pick_ward' => (string)$this->ghtkConfig->get('pick_ward', $storeId),
            'address' => $this->getDestinationAddress($request, $storeId),
            'province' => $request->getDestRegion() ?: $this->ghtkConfig->get('demo_to_province', $storeId),
            'district' => $this->extractAddressValue($request, 'district') ?: $this->ghtkConfig->get('demo_to_district', $storeId),
            'ward' => $this->extractAddressValue($request, 'ward') ?: $this->ghtkConfig->get('demo_to_ward', $storeId),
            'weight' => $this->getPackageWeightGrams($request, $storeId),
            'value' => max(0, (int)round((float)$request->getPackageValueWithDiscount())),
            'transport' => $this->ghtkConfig->get('transport', $storeId) ?: 'road',
        ];

        if (!$query['pick_province'] || !$query['pick_district'] || !$query['province'] || !$query['district'] || !$query['address']) {
            throw new \RuntimeException('GHTK pickup/destination mapping is incomplete.');
        }

        $response = $this->client->calculateFee($query, $storeId);
        $fee = $response['fee'] ?? [];

        if (array_key_exists('delivery', $fee) && !$fee['delivery']) {
            return null;
        }

        if (!isset($fee['fee'])) {
            throw new \RuntimeException('GHTK fee response did not include fee.fee.');
        }

        return (float)$fee['fee'];
    }

    private function getDestinationAddress(RateRequest $request, ?int $storeId): string
    {
        $street = $request->getDestStreet();
        if (is_array($street)) {
            $street = implode(', ', array_filter($street));
        }

        $street = trim((string)$street);

        return $street !== '' ? $street : (string)$this->ghtkConfig->get('demo_to_address', $storeId);
    }

    private function getPackageWeightGrams(RateRequest $request, ?int $storeId): int
    {
        $defaultWeight = $this->ghtkConfig->getInt('default_weight', 500, $storeId);
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

    private function getFallbackFee(?int $storeId): float
    {
        return max(0, $this->ghtkConfig->getFloat('fallback_fee', 30000.0, $storeId));
    }

    private function extractAddressValue(RateRequest $request, string $key): string
    {
        $value = $request->getData($key);
        if ($value) {
            return (string)$value;
        }

        $customAttributes = $request->getData('custom_attributes');
        if (is_array($customAttributes) && isset($customAttributes[$key])) {
            return is_array($customAttributes[$key])
                ? (string)($customAttributes[$key]['value'] ?? '')
                : (string)$customAttributes[$key];
        }

        return '';
    }
}
