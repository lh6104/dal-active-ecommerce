define(['jquery'], function ($) {
    'use strict';

    function applyBottomRight($root) {
        var $media = $root.closest('.catalog-product-view').find('.product.media');
        var $stage = $media.find('.fotorama__stage');
        var $shaft = $media.find('.fotorama__stage__shaft');
        var $prev = $media.find('.fotorama__arr--prev');
        var $next = $media.find('.fotorama__arr--next');

        if ($stage.length) {
            $stage[0].style.setProperty('left', '0px', 'important');
            $stage[0].style.setProperty('right', '0px', 'important');
            $stage[0].style.setProperty('width', '100%', 'important');
        }

        if ($shaft.length) {
            $shaft[0].style.setProperty('max-width', '100%', 'important');
            $shaft[0].style.setProperty('width', '100%', 'important');
        }

        if ($prev.length) {
            $prev[0].style.cssText = 'left: 24px !important; right: auto !important; bottom: 24px !important; top: auto !important; transform: none !important;';
        }

        if ($next.length) {
            $next[0].style.cssText = 'right: 24px !important; left: auto !important; bottom: 24px !important; top: auto !important; transform: none !important;';
        }
    }

    function runWithRetries($root) {
        var attempts = 0;
        var intervalId = window.setInterval(function () {
            attempts += 1;
            applyBottomRight($root);

            if (attempts >= 40) {
                window.clearInterval(intervalId);
            }
        }, 100);

        applyBottomRight($root);
        window.requestAnimationFrame(function () {
            applyBottomRight($root);
            window.setTimeout(applyBottomRight, 120, $root);
            window.setTimeout(applyBottomRight, 600, $root);
            window.setTimeout(function () {
                window.clearInterval(intervalId);
                applyBottomRight($root);
            }, 4200);
        });
    }

    return function (config, element) {
        var $root = $(element);

        runWithRetries($root);
    };
});
