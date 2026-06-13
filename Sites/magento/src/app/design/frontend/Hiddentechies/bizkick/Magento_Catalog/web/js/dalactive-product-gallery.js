require(['jquery'], function ($) {
    'use strict';

    function preload(src, callback) {
        var image = new Image();

        image.onload = function () {
            callback();
        };

        image.onerror = function () {
            callback();
        };

        image.src = src;
    }

    function initGallery(element) {
        var $gallery = $(element);
        var $image = $gallery.find('.dalactive-product-gallery__image').first();
        var $thumbs = $gallery.find('.dalactive-product-gallery__thumb');
        var currentIndex = Math.max(0, $thumbs.index($thumbs.filter('.is-active').first()));

        if (!$image.length || !$thumbs.length) {
            return;
        }

        function update(index) {
            var $target = $thumbs.eq(index);
            var main = $target.data('gallery-main');
            var full = $target.data('gallery-full');
            var caption = $target.data('gallery-caption') || '';

            if (!$target.length || !main || index === currentIndex) {
                return;
            }

            $gallery.addClass('is-changing');

            preload(main, function () {
                $image.attr({
                    src: main,
                    alt: caption,
                    'data-full-image': full || main
                });

                currentIndex = index;
                $thumbs.removeClass('is-active').attr('aria-current', 'false');
                $target.addClass('is-active').attr('aria-current', 'true');

                window.setTimeout(function () {
                    $gallery.removeClass('is-changing');
                }, 80);
            });
        }

        $thumbs.on('click', function () {
            update($thumbs.index(this));
        });

        $gallery.find('[data-gallery-control="prev"]').on('click', function () {
            update((currentIndex - 1 + $thumbs.length) % $thumbs.length);
        });

        $gallery.find('[data-gallery-control="next"]').on('click', function () {
            update((currentIndex + 1) % $thumbs.length);
        });

        $gallery.on('keydown', function (event) {
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                update((currentIndex - 1 + $thumbs.length) % $thumbs.length);
            }

            if (event.key === 'ArrowRight') {
                event.preventDefault();
                update((currentIndex + 1) % $thumbs.length);
            }
        });
    }

    $(function () {
        $('[data-role="dalactive-product-gallery"]').each(function () {
            initGallery(this);
        });
    });
});
