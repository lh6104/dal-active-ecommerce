define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/recollect-shipping-rates',
    'Dalactive_GhnShipping/js/model/ghn-address-state'
], function (Select, registry, quote, recollectShippingRates, state) {
    'use strict';

    return Select.extend({
        initialize: function () {
            this._super();
            this.initGhnOptions();

            return this;
        },

        getGhnLevel: function () {
            return this.ghnLevel || (this.config && this.config.ghnLevel) || '';
        },

        initGhnOptions: function () {
            var level = this.getGhnLevel();

            if (level === 'province') {
                this.setOptions(state.provinces());
                state.provinces.subscribe(this.setOptions.bind(this));
                state.loadProvinces();
            }

            if (level === 'district') {
                this.setOptions(state.districts());
                state.districts.subscribe(this.setOptions.bind(this));
            }

            if (level === 'ward') {
                this.setOptions(state.wards());
                state.wards.subscribe(this.setOptions.bind(this));
            }
        },

        onUpdate: function (value) {
            var level = this.getGhnLevel(),
                name = '';

            this._super(value);

            if (level === 'province') {
                state.selectedProvince(value || '');
                state.selectedDistrict('');
                state.selectedWard('');
                name = state.maps.province[String(value)] || '';
                this.setCustomAttribute('ghn_province_id', value || '');
                this.setCustomAttribute('ghn_province_name', name);
                this.setProviderValue('country_id', 'VN');
                this.setProviderValue('region_id', '');
                this.setProviderValue('region', name);
                this.setProviderValue('city', '');
                this.setProviderValue('postcode', '');
                this.setCustomAttribute('ghn_district_id', '');
                this.setCustomAttribute('ghn_district_name', '');
                this.setCustomAttribute('ghn_ward_code', '');
                this.setCustomAttribute('ghn_ward_name', '');
                state.loadDistricts(value || '');
            }

            if (level === 'district') {
                state.selectedDistrict(value || '');
                state.selectedWard('');
                name = state.maps.district[String(value)] || '';
                this.setCustomAttribute('ghn_district_id', value || '');
                this.setCustomAttribute('ghn_district_name', name);
                this.setProviderValue('city', name);
                this.setProviderValue('postcode', '');
                this.setCustomAttribute('ghn_ward_code', '');
                this.setCustomAttribute('ghn_ward_name', '');
                state.loadWards(value || '');
            }

            if (level === 'ward') {
                state.selectedWard(value || '');
                name = state.maps.ward[String(value)] || '';
                this.setCustomAttribute('ghn_ward_code', value || '');
                this.setCustomAttribute('ghn_ward_name', name);
                this.setProviderValue('postcode', value || '');
            }

            this.updateQuoteAddress(level, value, name);
            recollectShippingRates();
        },

        setCustomAttribute: function (code, value) {
            if (this.source) {
                this.source.set('shippingAddress.custom_attributes.' + code, value || '');
            }
        },

        setProviderValue: function (code, value) {
            if (this.source) {
                this.source.set('shippingAddress.' + code, value || '');
            }
        },

        updateQuoteAddress: function (level, value, label) {
            var address = quote.shippingAddress(),
                codeMap = {
                    province: 'ghn_province_id',
                    district: 'ghn_district_id',
                    ward: 'ghn_ward_code'
                },
                code = codeMap[level];

            if (!address || !code) {
                return;
            }

            address.customAttributes = address.customAttributes || {};
            address.customAttributes[code] = {
                attribute_code: code,
                value: value || ''
            };
            address.customAttributes[code.replace(/_(id|code)$/, '_name')] = {
                attribute_code: code.replace(/_(id|code)$/, '_name'),
                value: label || ''
            };
        }
    });
});
