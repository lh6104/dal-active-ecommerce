define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/payment/additional-validators',
    'mage/url'
], function (Component, additionalValidators, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Dalactive_Sepay/payment/sepay'
        },

        redirectAfterPlaceOrder: false,

        getCode: function () {
            return 'sepay';
        },

        getData: function () {
            return {
                method: this.item.method,
                additional_data: null
            };
        },

        getSepayConfig: function () {
            return window.checkoutConfig.payment.sepay || {};
        },

        isConfigured: function () {
            return !!this.getSepayConfig().configured;
        },

        getBankDisplayName: function () {
            return this.getSepayConfig().bankDisplayName || this.getSepayConfig().bankCode || '';
        },

        getAccountNo: function () {
            return this.getSepayConfig().accountNo || '';
        },

        getAccountName: function () {
            return this.getSepayConfig().accountName || '';
        },

        getPreviewQrUrl: function () {
            return this.getSepayConfig().previewQrUrl || '';
        },

        afterPlaceOrder: function (orderId) {
            var payUrl = this.getSepayConfig().payUrl || urlBuilder.build('sepay/payment/pay');

            if (orderId) {
                payUrl += (payUrl.indexOf('?') === -1 ? '?' : '&') + 'order_id=' + encodeURIComponent(orderId);
            }

            window.location.href = payUrl;
        },

        placeOrder: function (data, event) {
            var self = this;

            if (event) {
                event.preventDefault();
            }

            if (!this.isConfigured()) {
                this.messageContainer.addErrorMessage({
                    message: 'QR chuyển khoản chưa được cấu hình. Vui lòng chọn phương thức khác.'
                });
                return false;
            }

            if (
                this.validate() &&
                additionalValidators.validate() &&
                this.isPlaceOrderActionAllowed() === true
            ) {
                this.isPlaceOrderActionAllowed(false);

                this.getPlaceOrderDeferredObject()
                    .done(function (orderId) {
                        self.afterPlaceOrder(orderId);
                    })
                    .always(function () {
                        self.isPlaceOrderActionAllowed(true);
                    });

                return true;
            }

            return false;
        }
    });
});
