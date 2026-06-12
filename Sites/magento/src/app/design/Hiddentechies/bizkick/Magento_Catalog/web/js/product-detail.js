require([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    function optimizeGalleryImages($scope) {
        var $images = $scope.find('[data-gallery-role="gallery-placeholder"] img, .fotorama img');

        $images.each(function (index, image) {
            if (index === 0) {
                image.setAttribute('loading', 'eager');
                image.setAttribute('fetchpriority', 'high');
                return;
            }

            image.setAttribute('loading', 'lazy');
            image.setAttribute('decoding', 'async');
        });
    }

    function syncAccordions($scope) {
        $scope.find('.product.data.items > .item.title > .switch').each(function () {
            var $trigger = $(this),
                $title = $trigger.closest('.item.title'),
                controls = $title.attr('aria-controls') || $trigger.attr('href');

            $trigger.attr({
                role: 'button',
                tabindex: 0,
                'aria-expanded': $title.hasClass('active') ? 'true' : 'false'
            });

            if (controls) {
                $trigger.attr('aria-controls', controls.replace('#', ''));
            }
        });
    }

    function updateAccordionState($trigger) {
        var $title = $trigger.closest('.item.title');

        window.setTimeout(function () {
            $trigger.attr('aria-expanded', $title.hasClass('active') ? 'true' : 'false');
        }, 0);
    }

    function getMissingRequiredOptions($form) {
        return $form.find('[name^="super_attribute"]').filter(function () {
            var $field = $(this);

            return !$field.val() && !$field.prop('disabled');
        });
    }

    function showOptionMessage($form, show) {
        var $target = $form.find('.product-options-wrapper').first(),
            $message = $target.find('.product-option-validation');

        if (!$target.length) {
            return;
        }

        if (!$message.length) {
            $message = $('<div/>', {
                class: 'product-option-validation',
                id: 'product-option-validation',
                text: $t('Please select the required option before adding this product to your cart.')
            });
            $target.append($message);
        }

        $message.toggleClass('is-visible', show);
        $target.attr('aria-describedby', show ? 'product-option-validation' : null);
    }

    function bindAddToCart($scope) {
        var $form = $scope.find('#product_addtocart_form'),
            $button = $scope.find('#product-addtocart-button');

        if (!$form.length || !$button.length) {
            return;
        }

        $form.on('change click', '[name^="super_attribute"], .swatch-option', function () {
            showOptionMessage($form, false);
        });

        $form.on('submit', function () {
            var missingOptions = getMissingRequiredOptions($form);

            if (missingOptions.length) {
                showOptionMessage($form, true);
                return false;
            }

            if ($button.hasClass('is-loading')) {
                return false;
            }

            $button.addClass('is-loading').prop('disabled', true).attr('aria-busy', 'true');
            return true;
        });

        $(document).on('ajaxComplete ajaxError', function () {
            $button.removeClass('is-loading').prop('disabled', false).removeAttr('aria-busy');
        });
    }

    function bindDetailAccordions($scope) {
        $scope.on('click keydown', '.dalactive-product-accordion-trigger', function (event) {
            var $trigger = $(this),
                $panel = $('#' + $trigger.attr('aria-controls')),
                expanded = $trigger.attr('aria-expanded') === 'true';

            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            $trigger.attr('aria-expanded', expanded ? 'false' : 'true');
            $panel.prop('hidden', expanded);
        });
    }

    $(function () {
        var $page = $('.catalog-product-view');

        if (!$page.length) {
            return;
        }

        optimizeGalleryImages($page);
        syncAccordions($page);
        bindAddToCart($page);
        bindDetailAccordions($page);

        $page.on('click keydown', '.product.data.items > .item.title > .switch', function (event) {
            if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            updateAccordionState($(this));
        });

        $('[data-gallery-role="gallery-placeholder"]').on('gallery:loaded fotorama:ready', function () {
            optimizeGalleryImages($page);
        });
    });
});
