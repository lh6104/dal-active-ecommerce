<?php

declare(strict_types=1);

namespace Dalactive\GhnShipping\Plugin\Checkout;

use Dalactive\GhnShipping\Model\Config;
use Magento\Checkout\Block\Checkout\LayoutProcessor as MagentoLayoutProcessor;
use Magento\Store\Model\StoreManagerInterface;

class LayoutProcessor
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function afterProcess(MagentoLayoutProcessor $subject, array $jsLayout): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId) || !$this->config->isCheckoutDropdownsEnabled($storeId)) {
            return $jsLayout;
        }

        $fieldsetPath = [
            'components',
            'checkout',
            'children',
            'steps',
            'children',
            'shipping-step',
            'children',
            'shippingAddress',
            'children',
            'shipping-address-fieldset',
            'children',
        ];

        $fieldset = &$jsLayout;
        foreach ($fieldsetPath as $segment) {
            if (!isset($fieldset[$segment]) || !is_array($fieldset[$segment])) {
                return $jsLayout;
            }
            $fieldset = &$fieldset[$segment];
        }

        $fieldset['ghn_province_id'] = $this->buildSelectField(
            'ghn_province_id',
            'Tỉnh/Thành phố',
            'province',
            72
        );
        $fieldset['ghn_district_id'] = $this->buildSelectField(
            'ghn_district_id',
            'Quận/Huyện',
            'district',
            73
        );
        $fieldset['ghn_ward_code'] = $this->buildSelectField(
            'ghn_ward_code',
            'Phường/Xã',
            'ward',
            74
        );

        foreach (['ghn_province_name', 'ghn_district_name', 'ghn_ward_name'] as $code) {
            $fieldset[$code] = [
                'component' => 'Magento_Ui/js/form/element/abstract',
                'config' => [
                    'customScope' => 'shippingAddress',
                    'template' => 'ui/form/field',
                    'elementTmpl' => 'ui/form/element/input',
                ],
                'dataScope' => 'shippingAddress.custom_attributes.' . $code,
                'provider' => 'checkoutProvider',
                'visible' => false,
                'sortOrder' => 199,
            ];
        }

        $this->hideDuplicatedMagentoAddressFields($fieldset);

        return $jsLayout;
    }

    private function buildSelectField(string $code, string $label, string $level, int $sortOrder): array
    {
        return [
            'component' => 'Dalactive_GhnShipping/js/form/element/ghn-select',
            'ghnLevel' => $level,
            'config' => [
                'customScope' => 'shippingAddress',
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/select',
                'id' => $code,
            ],
            'dataScope' => 'shippingAddress.custom_attributes.' . $code,
            'label' => __($label),
            'provider' => 'checkoutProvider',
            'sortOrder' => $sortOrder,
            'visible' => true,
            'validation' => [
                'required-entry' => false,
            ],
            'options' => [
                [
                    'value' => '',
                    'label' => __('Chọn %1', mb_strtolower($label)),
                ],
            ],
        ];
    }

    private function hideDuplicatedMagentoAddressFields(array &$fieldset): void
    {
        foreach (['country_id', 'region_id', 'region', 'city', 'postcode'] as $code) {
            if (!isset($fieldset[$code]) || !is_array($fieldset[$code])) {
                continue;
            }

            $fieldset[$code]['visible'] = false;
            $fieldset[$code]['validation'] = [];
            $fieldset[$code]['sortOrder'] = 190;
        }

        if (isset($fieldset['country_id']) && is_array($fieldset['country_id'])) {
            $fieldset['country_id']['value'] = 'VN';
        }
    }
}
