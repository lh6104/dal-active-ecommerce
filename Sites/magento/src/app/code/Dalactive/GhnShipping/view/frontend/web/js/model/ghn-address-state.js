define([
    'ko',
    'mage/storage',
    'mage/url'
], function (ko, storage, urlBuilder) {
    'use strict';

    var state = {
        provinces: ko.observableArray([]),
        districts: ko.observableArray([]),
        wards: ko.observableArray([]),
        selectedProvince: ko.observable(''),
        selectedDistrict: ko.observable(''),
        selectedWard: ko.observable(''),
        maps: {
            province: {},
            district: {},
            ward: {}
        }
    };

    function options(items, valueKey, labelKey) {
        var map = {},
            result = [{
                value: '',
                label: 'Chọn một tùy chọn...'
            }];

        (items || []).forEach(function (item) {
            var value = String(item[valueKey] || ''),
                label = item[labelKey] || '';

            if (!value || !label) {
                return;
            }

            map[value] = label;
            result.push({
                value: value,
                label: label
            });
        });

        return {
            map: map,
            options: result
        };
    }

    state.loadProvinces = function () {
        return storage.get(urlBuilder.build('ghn/address/provinces')).done(function (response) {
            var parsed = options(response.items || [], 'id', 'name');
            state.maps.province = parsed.map;
            state.provinces(parsed.options);
        });
    };

    state.loadDistricts = function (provinceId) {
        state.districts([{
            value: '',
            label: 'Chọn một tùy chọn...'
        }]);
        state.wards([{
            value: '',
            label: 'Chọn một tùy chọn...'
        }]);
        state.maps.district = {};
        state.maps.ward = {};

        if (!provinceId) {
            return;
        }

        return storage.get(urlBuilder.build('ghn/address/districts') + '?province_id=' + encodeURIComponent(provinceId))
            .done(function (response) {
                var parsed = options(response.items || [], 'id', 'name');
                state.maps.district = parsed.map;
                state.districts(parsed.options);
            });
    };

    state.loadWards = function (districtId) {
        state.wards([{
            value: '',
            label: 'Chọn một tùy chọn...'
        }]);
        state.maps.ward = {};

        if (!districtId) {
            return;
        }

        return storage.get(urlBuilder.build('ghn/address/wards') + '?district_id=' + encodeURIComponent(districtId))
            .done(function (response) {
                var parsed = options(response.items || [], 'code', 'name');
                state.maps.ward = parsed.map;
                state.wards(parsed.options);
            });
    };

    return state;
});
