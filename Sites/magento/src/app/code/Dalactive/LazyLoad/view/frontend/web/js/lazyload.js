(function () {
    'use strict';

    var ignoredSelectors = [
        '.logo img',
        '.home-slider-wrapper img',
        '.page-header img',
        'img[data-no-lazy="true"]'
    ].join(',');

    var homepageEagerSelectors = [
        '.cms-index-index .block-new-products img',
        '.cms-index-index .hb-wrapper.testimonials-section img',
        '.cms-index-index .brands-wrapper img'
    ].join(',');

    function shouldSkip(image, index) {
        return index < 2 || image.matches(ignoredSelectors);
    }

    function shouldLoadEager(image, index) {
        return index < 8 || image.matches(homepageEagerSelectors);
    }

    function setImageLoading(image, loading) {
        if (!image.hasAttribute('decoding')) {
            image.setAttribute('decoding', 'async');
        }

        if (loading === 'eager') {
            image.setAttribute('loading', 'eager');
            return;
        }

        if (!image.hasAttribute('loading')) {
            image.setAttribute('loading', loading);
        }
    }

    function prepareImage(image) {
        setImageLoading(image, 'lazy');
    }

    function init() {
        var images = Array.prototype.slice.call(document.querySelectorAll('img'));

        images.forEach(function (image, index) {
            if (shouldSkip(image, index)) {
                setImageLoading(image, 'eager');
                return;
            }

            if (shouldLoadEager(image, index)) {
                setImageLoading(image, 'eager');
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
