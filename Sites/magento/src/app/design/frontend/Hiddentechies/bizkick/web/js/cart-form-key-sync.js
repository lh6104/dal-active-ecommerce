(function () {
    'use strict';

    var cookieName = 'form_key',
        formKeySelector = 'input[name="form_key"]',
        cartFormSelector = 'form[data-role="tocart-form"], #product_addtocart_form, .form.map.checkout',
        postTriggerSelector = '[data-post], .autoRelatedProductsAddToCart';

    function getCookie(name) {
        var cookies = document.cookie ? document.cookie.split(';') : [],
            prefix = name + '=',
            i,
            cookie;

        for (i = 0; i < cookies.length; i++) {
            cookie = cookies[i].trim();

            if (cookie.indexOf(prefix) === 0) {
                return decodeURIComponent(cookie.substring(prefix.length));
            }
        }

        return '';
    }

    function setCookie(name, value) {
        var expires = new Date(Date.now() + 86400000).toUTCString(),
            secure = window.location.protocol === 'https:' ? '; secure' : '';

        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/' + secure + '; samesite=lax';
    }

    function generateFormKey() {
        var chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            key = '',
            i;

        for (i = 0; i < 16; i++) {
            key += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        return key;
    }

    function getVisibleFormKey() {
        var input = document.querySelector(formKeySelector);

        return input ? input.value : '';
    }

    function getFormKey() {
        var key = getCookie(cookieName) || getVisibleFormKey() || generateFormKey();

        setCookie(cookieName, key);

        return key;
    }

    function syncInputs(scope, key) {
        var inputs = (scope || document).querySelectorAll(formKeySelector),
            i;

        for (i = 0; i < inputs.length; i++) {
            inputs[i].value = key;
            inputs[i].setAttribute('value', key);
        }
    }

    function syncDataPost(element, key) {
        var dataPost = element.getAttribute('data-post'),
            postData;

        if (!dataPost) {
            return;
        }

        try {
            postData = JSON.parse(dataPost);
        } catch (e) {
            return;
        }

        postData.data = postData.data || {};
        postData.data.form_key = key;
        element.setAttribute('data-post', JSON.stringify(postData));
    }

    function syncElement(element) {
        var key = getFormKey(),
            form = element && element.closest ? element.closest(cartFormSelector) : null;

        syncInputs(form || document, key);

        if (element && element.matches && element.matches('[data-post]')) {
            syncDataPost(element, key);
        }

        if (element && element.matches && element.matches('.autoRelatedProductsAddToCart')) {
            element.setAttribute('data-form-key', key);
        }
    }

    function reloadCartSections() {
        if (!window.require) {
            return;
        }

        window.require(['Magento_Customer/js/customer-data'], function (customerData) {
            customerData.invalidate(['cart', 'messages']);
            customerData.reload(['cart', 'messages'], true);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncInputs(document, getFormKey());
    });

    document.addEventListener('submit', function (event) {
        if (event.target && event.target.matches && event.target.matches(cartFormSelector)) {
            syncElement(event.target);
        }
    }, true);

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest ? event.target.closest(postTriggerSelector) : null;

        if (trigger) {
            syncElement(trigger);
        }
    }, true);

    if (window.require) {
        window.require(['jquery'], function ($) {
            $(document).on('ajaxComplete', function (event, xhr, settings) {
                if (settings && settings.url && settings.url.indexOf('/checkout/cart/add') !== -1 && xhr.status < 400) {
                    reloadCartSections();
                }
            });
        });
    }
}());
