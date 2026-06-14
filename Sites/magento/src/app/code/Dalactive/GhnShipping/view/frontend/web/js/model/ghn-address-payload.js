define([
    'Magento_Checkout/js/model/quote',
    'uiRegistry'
], function (quote, registry) {
    'use strict';

    var fields = [
        'ghn_province_id',
        'ghn_district_id',
        'ghn_ward_code',
        'ghn_province_name',
        'ghn_district_name',
        'ghn_ward_name'
    ];

    function getAttributeValue(attributes, code) {
        if (!attributes) {
            return '';
        }

        if (attributes[code]) {
            return attributes[code].value !== undefined ? attributes[code].value : attributes[code];
        }

        if (Array.isArray(attributes)) {
            var found = attributes.find(function (attribute) {
                return attribute.attribute_code === code || attribute.attributeCode === code;
            });

            return found ? found.value : '';
        }

        return '';
    }

    function getValue(address, code) {
        var provider = registry.get('checkoutProvider'),
            shippingAddress = quote.shippingAddress(),
            value;

        if (provider) {
            value = provider.get('shippingAddress.custom_attributes.' + code);
            if (value !== undefined && value !== null && value !== '') {
                return value;
            }
        }

        value = getAttributeValue(address && address.customAttributes, code);
        if (value !== '') {
            return value;
        }

        value = getAttributeValue(address && address.custom_attributes, code);
        if (value !== '') {
            return value;
        }

        return getAttributeValue(shippingAddress && shippingAddress.customAttributes, code);
    }

    function setCustomAttribute(payloadAddress, code, value) {
        payloadAddress.custom_attributes = payloadAddress.custom_attributes || {};
        payloadAddress.custom_attributes[code] = {
            attribute_code: code,
            value: value
        };
    }

    function applyGhnFields(payloadAddress, sourceAddress) {
        var hasGhnData = false;

        payloadAddress.custom_attributes = payloadAddress.custom_attributes || {};
        payloadAddress.extension_attributes = payloadAddress.extension_attributes || {};

        fields.forEach(function (field) {
            var value = getValue(sourceAddress, field);

            if (value === undefined || value === null || value === '') {
                return;
            }

            hasGhnData = true;
            payloadAddress.extension_attributes[field] = value;
            setCustomAttribute(payloadAddress, field, value);
        });

        if (hasGhnData) {
            payloadAddress.country_id = payloadAddress.country_id || 'VN';
            payloadAddress.region_id = '';
            payloadAddress.region = getValue(sourceAddress, 'ghn_province_name') || payloadAddress.region || '';
            payloadAddress.city = getValue(sourceAddress, 'ghn_district_name') || payloadAddress.city || '';
            payloadAddress.postcode = getValue(sourceAddress, 'ghn_ward_code') || payloadAddress.postcode || '';
        }

        return hasGhnData;
    }

    function buildAddressPayload(address) {
        var payloadAddress = {
            street: address.street,
            city: address.city,
            region_id: address.regionId,
            region: address.region,
            country_id: address.countryId,
            postcode: address.postcode,
            email: address.email,
            customer_id: address.customerId,
            firstname: address.firstname,
            lastname: address.lastname,
            middlename: address.middlename,
            prefix: address.prefix,
            suffix: address.suffix,
            vat_id: address.vatId,
            company: address.company,
            telephone: address.telephone,
            fax: address.fax,
            custom_attributes: address.customAttributes,
            save_in_address_book: address.saveInAddressBook
        };

        applyGhnFields(payloadAddress, address);

        return payloadAddress;
    }

    return {
        applyGhnFields: applyGhnFields,
        buildAddressPayload: buildAddressPayload
    };
});
