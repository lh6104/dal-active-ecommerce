define(['jquery'], function ($) {
    'use strict';

    function applyGalleryFix($root) {
        var $stage = $root.find('.fotorama__stage');
        var $shaft = $root.find('.fotorama__stage__shaft');
        var $next = $root.find('.fotorama__arr--next');
        var $prev = $root.find('.fotorama__arr--prev');

        if ($stage.length) {
            $stage[0].style.setProperty('left', '0px', 'important');
            $stage[0].style.setProperty('right', '0px', 'important');
            $stage[0].style.setProperty('width', '100%', 'important');
        }

        if ($shaft.length) {
            $shaft[0].style.setProperty('max-width', '100%', 'important');
            $shaft[0].style.setProperty('width', '100%', 'important');
        }

        if ($next.length) {
            $next[0].style.cssText = 'inset: auto 24px 24px auto !important; transform: none !important;';
        }

        if ($prev.length) {
            $prev[0].style.cssText = 'inset: auto 76px 24px auto !important; transform: none !important;';
        }
    }

    function scheduleGalleryFix($root) {
        var timers = $root.data('dalactiveGalleryFixTimers') || [];
        var intervalId;
        var timeoutId;
        var attempts = 0;

        while (timers.length) {
            clearTimeout(timers.pop());
        }

        intervalId = window.setInterval(function () {
            attempts += 1;
            applyGalleryFix($root);

            if (attempts >= 20) {
                window.clearInterval(intervalId);
            }
        }, 100);

        timeoutId = window.setTimeout(function () {
            applyGalleryFix($root);
            window.clearInterval(intervalId);
        }, 2200);

        timers = [intervalId, timeoutId];

        $root.data('dalactiveGalleryFixTimers', timers);
    }

    function bindGalleryFix($root) {
        var observer;

        if (!$root.length) {
            return;
        }

        if ($root.data('dalactiveGalleryFixBound')) {
            return;
        }

        $root.data('dalactiveGalleryFixBound', true);

        scheduleGalleryFix($root);

        $root.on('fotorama:ready.dalactiveGalleryFix fotorama:showend.dalactiveGalleryFix fotorama:fullscreenenter.dalactiveGalleryFix fotorama:fullscreenexit.dalactiveGalleryFix', function () {
            scheduleGalleryFix($root);
        });

        if (window.MutationObserver) {
            observer = new MutationObserver(function () {
                scheduleGalleryFix($root);
            });

            observer.observe($root[0], {
                attributes: true,
                childList: true,
                subtree: true,
                attributeFilter: ['style', 'class']
            });

            $root.data('dalactiveGalleryFixObserver', observer);
        }
    }

    return function (Gallery) {
        return Gallery.extend({
            initialize: function (config, element) {
                var $element = $(element);

                $element.one('gallery:loaded.dalactiveGalleryFix', function () {
                    var $root = $(this);

                    bindGalleryFix($root);

                    window.requestAnimationFrame(function () {
                        scheduleGalleryFix($root);
                    });
                });

                return this._super(config, element);
            }
        });
    };
});
