(function () {
    'use strict';

    var ignoredSelectors = [
        '.logo img',
        '.home-slider-wrapper img',
        '.page-header img',
        'img[data-no-lazy="true"]'
    ].join(',');

    function shouldSkip(image, index) {
        return index < 2 || image.matches(ignoredSelectors);
    }

    function prepareImage(image) {
        if (!image.hasAttribute('decoding')) {
            image.setAttribute('decoding', 'async');
        }

        if (!image.hasAttribute('loading')) {
            image.setAttribute('loading', 'lazy');
        }
    }

    function init() {
        var images = Array.prototype.slice.call(document.querySelectorAll('img'));

        images.forEach(function (image, index) {
            if (shouldSkip(image, index)) {
                if (!image.hasAttribute('decoding')) {
                    image.setAttribute('decoding', 'async');
                }
                return;
            }

            prepareImage(image);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
