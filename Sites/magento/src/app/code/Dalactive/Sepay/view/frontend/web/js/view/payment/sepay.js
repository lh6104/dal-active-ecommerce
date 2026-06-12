define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'sepay',
        component: 'Dalactive_Sepay/js/view/payment/method-renderer/sepay-method'
    });

    return Component.extend({});
});
