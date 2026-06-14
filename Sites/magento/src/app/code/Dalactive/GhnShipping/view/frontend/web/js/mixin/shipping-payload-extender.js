define([
    'mage/utils/wrapper',
    'Dalactive_GhnShipping/js/model/ghn-address-payload'
], function (wrapper, ghnAddressPayload) {
    'use strict';

    return function (target) {
        return wrapper.wrap(target, function (originalAction, payload) {
            var shippingAddress = payload.addressInformation.shipping_address;

            ghnAddressPayload.applyGhnFields(shippingAddress, shippingAddress);

            return originalAction(payload);
        });
    };
});
