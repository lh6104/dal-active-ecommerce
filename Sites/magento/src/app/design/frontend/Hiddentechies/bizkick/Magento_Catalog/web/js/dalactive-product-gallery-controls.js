require(['jquery'], function ($) {
    'use strict';

    function applyGalleryGeometry($media) {
        var $stage = $media.find('.fotorama__stage');
        var $shaft = $media.find('.fotorama__stage__shaft');
        var $wrap = $media.find('.fotorama__wrap');
        var $navWrap = $media.find('.fotorama__nav-wrap--vertical');
        var $prev = $media.find('.fotorama__arr--prev');
        var $next = $media.find('.fotorama__arr--next');

        if ($wrap.length) {
            $wrap[0].style.setProperty('display', 'flex', 'important');
            $wrap[0].style.setProperty('align-items', 'stretch', 'important');
            $wrap[0].style.setProperty('gap', '24px', 'important');
            $wrap[0].style.setProperty('position', 'relative', 'important');
            $wrap[0].style.setProperty('overflow', 'visible', 'important');
            $wrap[0].style.setProperty('width', '100%', 'important');
        }

        if ($navWrap.length) {
            $navWrap[0].style.setProperty('position', 'static', 'important');
            $navWrap[0].style.setProperty('flex', '0 0 102px', 'important');
            $navWrap[0].style.setProperty('width', '102px', 'important');
            $navWrap[0].style.setProperty('padding-right', '0', 'important');
            $navWrap[0].style.setProperty('margin-right', '0', 'important');
        }

        if ($stage.length) {
            $stage[0].style.setProperty('flex', '1 1 auto', 'important');
            $stage[0].style.setProperty('min-width', '0', 'important');
            $stage[0].style.setProperty('width', 'auto', 'important');
            $stage[0].style.setProperty('left', '0px', 'important');
            $stage[0].style.setProperty('right', '0px', 'important');
            $stage[0].style.setProperty('overflow', 'hidden', 'important');
        }

        if ($shaft.length) {
            $shaft[0].style.setProperty('max-width', '100%', 'important');
            $shaft[0].style.setProperty('width', '100%', 'important');
            $shaft[0].style.setProperty('min-width', '0', 'important');
        }

        if ($prev.length) {
            $prev[0].style.cssText = 'inset: auto 76px 24px auto !important; transform: none !important;';
        }

        if ($next.length) {
            $next[0].style.cssText = 'inset: auto 24px 24px auto !important; transform: none !important;';
        }
    }

    function applyInfoGeometry($page) {
        $page.find('.product-detail-scroll-section, .product-detail-pin, .product-gallery-column, .product-info-column, .product-info-column > .product-info-main, .product-info-main, .product-add-form, .product-options-wrapper, .product.attribute.overview, .dalactive-product-accordions').each(function () {
            this.style.setProperty('height', 'auto', 'important');
            this.style.setProperty('max-height', 'none', 'important');
            this.style.setProperty('min-height', '0', 'important');
            this.style.setProperty('overflow', 'visible', 'important');
            this.style.setProperty('overflow-x', 'visible', 'important');
            this.style.setProperty('overflow-y', 'visible', 'important');
            this.style.setProperty('position', 'static', 'important');
            this.style.setProperty('top', 'auto', 'important');
            this.style.setProperty('transform', 'none', 'important');
            this.style.setProperty('will-change', 'auto', 'important');
        });
    }

    function applyPageGeometry($page) {
        var isDesktop = window.matchMedia('(min-width: 900px)').matches;
        var $pin = $page.find('.product-detail-pin').first();
        var $galleryColumn = $page.find('.product-gallery-column').first();
        var $infoColumn = $page.find('.product-info-column').first();
        var $media = $page.find('.product.media').first();
        var $infoMain = $page.find('.product-info-column > .product-info-main, .product-info-main').first();

        if ($pin.length) {
            $pin[0].style.setProperty('display', isDesktop ? 'grid' : 'block', 'important');
            $pin[0].style.setProperty('align-items', 'start', 'important');
            $pin[0].style.setProperty('gap', isDesktop ? '40px' : '0', 'important');
            $pin[0].style.setProperty('grid-template-columns', isDesktop ? 'minmax(0, 1.35fr) minmax(360px, .65fr)' : 'none', 'important');
            $pin[0].style.setProperty('max-width', '1320px', 'important');
            $pin[0].style.setProperty('margin', '0 auto', 'important');
            $pin[0].style.setProperty('padding', isDesktop ? '28px 24px 40px' : '0', 'important');
        }

        if ($galleryColumn.length) {
            $galleryColumn[0].style.setProperty('grid-column', isDesktop ? '1' : 'auto', 'important');
            $galleryColumn[0].style.setProperty('grid-row', '1', 'important');
            $galleryColumn[0].style.setProperty('position', 'relative', 'important');
            $galleryColumn[0].style.setProperty('overflow', 'visible', 'important');
        }

        if ($infoColumn.length) {
            $infoColumn[0].style.setProperty('grid-column', isDesktop ? '2' : 'auto', 'important');
            $infoColumn[0].style.setProperty('grid-row', '1', 'important');
            $infoColumn[0].style.setProperty('position', 'static', 'important');
            $infoColumn[0].style.setProperty('overflow', 'visible', 'important');
        }

        if ($media.length) {
            $media[0].style.setProperty('grid-column', 'auto', 'important');
            $media[0].style.setProperty('grid-row', 'auto', 'important');
            $media[0].style.setProperty('position', 'relative', 'important');
            $media[0].style.setProperty('overflow', 'visible', 'important');
        }

        if ($infoMain.length) {
            $infoMain[0].style.setProperty('position', 'static', 'important');
            $infoMain[0].style.setProperty('top', 'auto', 'important');
            $infoMain[0].style.setProperty('transform', 'none', 'important');
            $infoMain[0].style.setProperty('overflow', 'visible', 'important');
            $infoMain[0].style.setProperty('max-height', 'none', 'important');
        }
    }

    function runFix() {
        var $page = $('.catalog-product-view.page-layout-1column');
        var attempts = 0;
        var intervalId;

        if (!$page.length) {
            return;
        }

        intervalId = window.setInterval(function () {
            var $media = $page.find('.product.media').first();

            attempts += 1;
            applyInfoGeometry($page);
            applyPageGeometry($page);

            if ($media.length) {
                applyGalleryGeometry($media);
            }

            if (attempts >= 40) {
                window.clearInterval(intervalId);
            }
        }, 120);

        applyInfoGeometry($page);
        applyPageGeometry($page);
        window.requestAnimationFrame(function () {
            var $media = $page.find('.product.media').first();

            applyInfoGeometry($page);
            applyPageGeometry($page);

            if ($media.length) {
                applyGalleryGeometry($media);
            }

            window.setTimeout(function () {
                applyInfoGeometry($page);
                applyPageGeometry($page);
                if ($media.length) {
                    applyGalleryGeometry($media);
                }
            }, 200);

            window.setTimeout(function () {
                applyInfoGeometry($page);
                applyPageGeometry($page);
                if ($media.length) {
                    applyGalleryGeometry($media);
                }
                window.clearInterval(intervalId);
            }, 4200);
        });
    }

    $(runFix);
});
