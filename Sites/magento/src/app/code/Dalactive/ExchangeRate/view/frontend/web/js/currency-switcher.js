define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var storageKey = config.storageKey || 'dalactive_display_currency';
        var cookieName = config.cookieName || storageKey;
        var defaultCurrency = config.defaultCurrency || 'VND';
        var rates = config.rates || {VND: 1};
        var available = config.available || {VND: true};
        var currencies = config.currencies || {VND: 'Vietnamese Dong'};
        var currentCurrency = readCurrency();
        var observerTimer = null;

        function readCurrency() {
            var value = null;

            try {
                value = window.localStorage.getItem(storageKey);
            } catch (exception) {
                value = null;
            }

            if (!value) {
                value = readCookie(cookieName);
            }

            value = normaliseCurrency(value);

            return isCurrencyUsable(value) ? value : defaultCurrency;
        }

        function readCookie(name) {
            var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
            return match ? decodeURIComponent(match[1]) : null;
        }

        function saveCurrency(currency) {
            try {
                window.localStorage.setItem(storageKey, currency);
            } catch (exception) {
                // Browser storage can be disabled. Cookie persistence remains enough for this UI.
            }

            document.cookie = cookieName + '=' + encodeURIComponent(currency) + '; path=/; max-age=31536000; SameSite=Lax';
        }

        function normaliseCurrency(value) {
            return (value || defaultCurrency).toString().toUpperCase();
        }

        function isCurrencyUsable(currency) {
            return currency === 'VND' || (currencies[currency] && available[currency] && rates[currency]);
        }

        function setCurrency(currency) {
            currency = normaliseCurrency(currency);
            if (!isCurrencyUsable(currency)) {
                currency = defaultCurrency;
            }

            currentCurrency = currency;
            saveCurrency(currency);
            updateSwitcherState();
            updatePrices();
            $root.removeClass('is-open');
            $root.find('[data-dalactive-currency-toggle]').attr('aria-expanded', 'false');
        }

        function updateSwitcherState() {
            $('[data-dalactive-currency-current]').text(currentCurrency);
            $('[data-dalactive-currency-option]').each(function () {
                var $option = $(this);
                $option.toggleClass('is-active', normaliseCurrency($option.data('dalactive-currency-option')) === currentCurrency);
            });
        }

        function parseVnd(text) {
            var cleaned = (text || '').replace(/[^\d,.-]/g, '');
            var lastComma = cleaned.lastIndexOf(',');
            var lastDot = cleaned.lastIndexOf('.');

            if (!cleaned) {
                return null;
            }

            if (lastComma > lastDot) {
                cleaned = cleaned.replace(/\./g, '').replace(',', '.');
            } else {
                cleaned = cleaned.replace(/,/g, '');
            }

            var value = parseFloat(cleaned);
            return isNaN(value) || value <= 0 ? null : value;
        }

        function formatConverted(value, currency) {
            var converted = value / rates[currency];
            var locale = currency === 'JPY' || currency === 'KRW' ? 'en-US' : 'en-US';
            var digits = currency === 'JPY' || currency === 'KRW' ? 0 : 2;

            return new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            }).format(converted);
        }

        function priceSelector() {
            return [
                '.catalog-category-view .product-item .price-box .price',
                '.catalogsearch-result-index .product-item .price-box .price',
                '.cms-index-index .product-item .price-box .price',
                '.catalog-product-view .product-info-main .price-box .price',
                '.catalog-product-view .product-options-bottom .price-box .price'
            ].join(',');
        }

        function shouldSkipPrice($price) {
            return $price.closest('.minicart-wrapper, .block-minicart, .cart-summary, .checkout-container, .opc-wrapper').length > 0;
        }

        function updatePrices() {
            $(priceSelector()).each(function () {
                var $price = $(this);
                var originalText = $price.attr('data-dalactive-original-text');
                var originalValue = parseFloat($price.attr('data-dalactive-vnd'));
                var $container;

                if (shouldSkipPrice($price)) {
                    return;
                }

                if (!originalText) {
                    originalText = $.trim($price.text());
                    originalValue = parseVnd(originalText);

                    if (!originalValue) {
                        return;
                    }

                    $price.attr('data-dalactive-original-text', originalText);
                    $price.attr('data-dalactive-vnd', originalValue);
                }

                if (currentCurrency === 'VND') {
                    $price.text(originalText);
                    removeOriginalNote($price);
                    return;
                }

                if (!rates[currentCurrency]) {
                    setCurrency(defaultCurrency);
                    return;
                }

                $price.text(formatConverted(originalValue, currentCurrency));
                $container = $price.closest('.price-box');
                if (!$container.length) {
                    $container = $price.parent();
                }
                addOriginalNote($container, originalText);
            });

            updateCheckoutNote();
        }

        function addOriginalNote($container, originalText) {
            var $note = $container.find('> .dalactive-price-original').first();
            if (!$note.length) {
                $note = $('<span/>', {'class': 'dalactive-price-original'});
                $container.append($note);
            }
            $note.text('Original: ' + originalText + ' - Checkout in VND');
        }

        function removeOriginalNote($price) {
            var $container = $price.closest('.price-box');
            if (!$container.length) {
                $container = $price.parent();
            }
            $container.find('> .dalactive-price-original').remove();
        }

        function updateCheckoutNote() {
            var $target;
            var $note = $('.dalactive-checkout-vnd-note');
            var isCheckoutArea = $('body').hasClass('checkout-cart-index') || $('body').hasClass('checkout-index-index');

            if (!isCheckoutArea || currentCurrency === 'VND') {
                $note.remove();
                return;
            }

            if ($note.length) {
                return;
            }

            $target = $('.cart-summary, .checkout-container').first();
            if ($target.length) {
                $('<div/>', {
                    'class': 'dalactive-checkout-vnd-note',
                    text: config.checkoutNote || 'Thanh toán cuối cùng vẫn bằng VND.'
                }).insertBefore($target);
            }
        }

        function bindEvents() {
            $root.on('click', '[data-dalactive-currency-toggle]', function (event) {
                event.preventDefault();
                event.stopPropagation();
                $root.toggleClass('is-open');
                $(this).attr('aria-expanded', $root.hasClass('is-open') ? 'true' : 'false');
            });

            $root.on('click', '[data-dalactive-currency-option]', function (event) {
                event.preventDefault();
                setCurrency($(this).data('dalactive-currency-option'));
            });

            $(document).on('click.dalactiveCurrencySwitcher', function (event) {
                if (!$(event.target).closest('.dalactive-currency-switcher').length) {
                    $('.dalactive-currency-switcher').removeClass('is-open')
                        .find('[data-dalactive-currency-toggle]').attr('aria-expanded', 'false');
                }
            });

            $(document).on('keyup.dalactiveCurrencySwitcher', function (event) {
                if (event.key === 'Escape') {
                    $('.dalactive-currency-switcher').removeClass('is-open')
                        .find('[data-dalactive-currency-toggle]').attr('aria-expanded', 'false');
                }
            });
        }

        function removeSearchTooltip() {
            $('.page-header .block-search .action.search').removeAttr('title');
        }

        function observePriceChanges() {
            if (!window.MutationObserver) {
                return;
            }

            new MutationObserver(function () {
                window.clearTimeout(observerTimer);
                observerTimer = window.setTimeout(updatePrices, 120);
            }).observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        bindEvents();
        removeSearchTooltip();
        updateSwitcherState();
        updatePrices();
        observePriceChanges();
    };
});
