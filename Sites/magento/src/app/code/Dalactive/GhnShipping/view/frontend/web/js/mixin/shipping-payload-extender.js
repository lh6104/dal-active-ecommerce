define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'uiRegistry'
], function (wrapper, quote, registry) {
    'use strict';

    var fields = [
        'ghn_province_id',
        'ghn_district_id',
        'ghn_ward_code',
        'ghn_province_name',
        'ghn_district_name',
        'ghn_ward_name'
    ];

    function getValue(code) {
        var address = quote.shippingAddress(),
            provider = registry.get('checkoutProvider'),
            attributes,
            value;

        if (provider) {
            value = provider.get('shippingAddress.custom_attributes.' + code);
            if (value !== undefined && value !== null && value !== '') {
                return value;
            }
        }

        if (address && address.customAttributes) {
            attributes = address.customAttributes;
            if (attributes[code]) {
                return attributes[code].value !== undefined ? attributes[code].value : attributes[code];
            }
        }

        return '';
    }

    return function (target) {
        return wrapper.wrap(target, function (originalAction, payload) {
            var shippingAddress = payload.addressInformation.shipping_address;

            shippingAddress.extension_attributes = shippingAddress.extension_attributes || {};
            shippingAddress.custom_attributes = shippingAddress.custom_attributes || {};

            fields.forEach(function (field) {
                var value = getValue(field);
                if (value === '') {
                    return;
                }

                shippingAddress.extension_attributes[field] = value;
                shippingAddress.custom_attributes[field] = {
                    attribute_code: field,
                    value: value
                };
            });

            shippingAddress.country_id = shippingAddress.country_id || 'VN';
            shippingAddress.region_id = '';
            shippingAddress.region = getValue('ghn_province_name') || shippingAddress.region || '';
            shippingAddress.city = getValue('ghn_district_name') || shippingAddress.city || '';
            shippingAddress.postcode = getValue('ghn_ward_code') || shippingAddress.postcode || '';

            return originalAction(payload);
        });
    };
});
