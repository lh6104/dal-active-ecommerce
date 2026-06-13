<?php

declare(strict_types=1);

namespace Dalactive\GhnShipping\Plugin\Quote;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Api\AttributeInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\EstimateAddressInterface;
use Magento\Quote\Model\ShippingMethodManagement;

class ShippingAddressData
{
    private const GHN_FIELDS = [
        'ghn_province_id',
        'ghn_district_id',
        'ghn_ward_code',
        'ghn_province_name',
        'ghn_district_name',
        'ghn_ward_name',
    ];

    public function __construct(
        private readonly CartRepositoryInterface $quoteRepository
    ) {
    }

    public function beforeEstimateByExtendedAddress(
        ShippingMethodManagement $subject,
        $cartId,
        AddressInterface $address
    ): array {
        $this->applyToQuoteShippingAddress((int)$cartId, $address);

        return [$cartId, $address];
    }

    public function beforeEstimateByAddress(
        ShippingMethodManagement $subject,
        $cartId,
        EstimateAddressInterface $address
    ): array {
        $this->applyToQuoteShippingAddress((int)$cartId, $address);

        return [$cartId, $address];
    }

    public function beforeSaveAddressInformation(
        ShippingInformationManagement $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        $address = $addressInformation->getShippingAddress();
        if ($address) {
            $this->applyData($address, $this->readData($address));
        }

        return [$cartId, $addressInformation];
    }

    private function applyToQuoteShippingAddress(int $cartId, object $source): void
    {
        $data = $this->readData($source);
        if (!$data) {
            return;
        }

        $quote = $this->quoteRepository->getActive($cartId);
        $this->applyData($quote->getShippingAddress(), $data);
    }

    private function readData(object $address): array
    {
        $data = [];

        if (method_exists($address, 'getExtensionAttributes')) {
            $extensionAttributes = $address->getExtensionAttributes();
            if ($extensionAttributes) {
                foreach (self::GHN_FIELDS as $field) {
                    $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
                    if (method_exists($extensionAttributes, $method)) {
                        $value = $extensionAttributes->{$method}();
                        if ($value !== null && $value !== '') {
                            $data[$field] = $value;
                        }
                    }
                }
            }
        }

        if (method_exists($address, 'getCustomAttributes')) {
            foreach ((array)$address->getCustomAttributes() as $attributeCode => $attribute) {
                if ($attribute instanceof AttributeInterface) {
                    $attributeCode = $attribute->getAttributeCode();
                    $value = $attribute->getValue();
                } elseif (is_array($attribute)) {
                    $attributeCode = (string)($attribute['attribute_code'] ?? $attributeCode);
                    $value = $attribute['value'] ?? null;
                } else {
                    $value = $attribute;
                }

                if (in_array($attributeCode, self::GHN_FIELDS, true) && $value !== null && $value !== '') {
                    $data[$attributeCode] = $value;
                }
            }
        }

        if (method_exists($address, 'getData')) {
            foreach (self::GHN_FIELDS as $field) {
                $value = $address->getData($field);
                if ($value !== null && $value !== '') {
                    $data[$field] = $value;
                }
            }
        }

        return $data;
    }

    private function applyData(object $address, array $data): void
    {
        if (!$data || !method_exists($address, 'setData')) {
            return;
        }

        foreach ($data as $field => $value) {
            if (str_ends_with($field, '_id')) {
                $value = (int)$value;
            }
            $address->setData($field, $value);
        }

        if (!empty($data['ghn_province_name'])) {
            $address->setData('country_id', 'VN');
            $address->setData('region_id', null);
            $address->setData('region', (string)$data['ghn_province_name']);
        }

        if (!empty($data['ghn_district_name'])) {
            $address->setData('city', (string)$data['ghn_district_name']);
        }

        if (!empty($data['ghn_ward_code'])) {
            $address->setData('postcode', (string)$data['ghn_ward_code']);
        }
    }
}
