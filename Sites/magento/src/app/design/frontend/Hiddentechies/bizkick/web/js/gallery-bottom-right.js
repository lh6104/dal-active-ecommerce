require(['jquery'], function ($) {
    'use strict';

    var animationDirection = 'next';

    function applyBottomRight($media) {
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
            $prev[0].style.cssText = 'left: auto !important; right: 66px !important; bottom: 24px !important; top: auto !important; transform: none !important;';
        }

        if ($next.length) {
            $next[0].style.cssText = 'right: 22px !important; left: auto !important; bottom: 24px !important; top: auto !important; transform: none !important;';
        }
    }

    function animateActiveFrame($media) {
        var $active = $media.find('.fotorama__stage__frame.fotorama__active').first();

        if (!$active.length) {
            return;
        }

        $active
            .removeClass('dal-gallery-from-top dal-gallery-from-bottom')
            .addClass(animationDirection === 'prev' ? 'dal-gallery-from-top' : 'dal-gallery-from-bottom');

        window.setTimeout(function () {
            $active.removeClass('dal-gallery-from-top dal-gallery-from-bottom');
        }, 520);
    }

    function bindDirectionEvents($media) {
        var lastThumbIndex = $media.find('.fotorama__nav__frame.fotorama__active').index();

        if ($media.data('dalactiveVerticalGalleryBound')) {
            return;
        }

        $media.data('dalactiveVerticalGalleryBound', true);

        $media.on('click.dalactiveVerticalGallery', '.fotorama__arr--prev', function () {
            animationDirection = 'prev';
        });

        $media.on('click.dalactiveVerticalGallery', '.fotorama__arr--next', function () {
            animationDirection = 'next';
        });

        $media.on('click.dalactiveVerticalGallery', '.fotorama__nav__frame', function () {
            var nextThumbIndex = $(this).index();

            animationDirection = nextThumbIndex < lastThumbIndex ? 'prev' : 'next';
            lastThumbIndex = nextThumbIndex;
        });

        $media.on('fotorama:show.dalactiveVerticalGallery fotorama:showend.dalactiveVerticalGallery', function () {
            applyBottomRight($media);
            animateActiveFrame($media);
        });
    }

    function runGalleryFix($media) {
        var attempts = 0;
        var intervalId;

        if (!$media.length) {
            return;
        }

        bindDirectionEvents($media);

        intervalId = window.setInterval(function () {
            attempts += 1;
            applyBottomRight($media);

            if (attempts >= 30) {
                window.clearInterval(intervalId);
            }
        }, 100);

        applyBottomRight($media);
        window.requestAnimationFrame(function () {
            applyBottomRight($media);
            window.setTimeout(applyBottomRight, 120, $media);
            window.setTimeout(applyBottomRight, 600, $media);
            window.setTimeout(function () {
                window.clearInterval(intervalId);
                applyBottomRight($media);
            }, 3200);
        });
    }

    $(function () {
        var $page = $('.catalog-product-view');
        var $media = $page.find('.product.media').first();

        if (!$page.length || !$media.length) {
            return;
        }

        runGalleryFix($media);

        $('[data-gallery-role="gallery-placeholder"]').on('gallery:loaded fotorama:ready', function () {
            runGalleryFix($media);
            animateActiveFrame($media);
        });
    });
});
