define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $slides = $root.find('.dalactive-hero-slide'),
            $dots = $root.find('.dalactive-hero-dots'),
            $pause = $root.find('.dalactive-hero-pause'),
            current = 0,
            paused = false,
            timer = null,
            progressFrame = null,
            progressStartedAt = 0,
            progressTotal = 0,
            delayRemaining = null;

        function getCurrentDelay() {
            var slideDelay = parseInt($slides.eq(current).data('timeout'), 10),
                defaultDelay = parseInt(config.interval, 10) || 6000;

            return Math.max(1000, slideDelay || defaultDelay);
        }

        function setProgress(value) {
            $pause.css('--hero-pause-progress', Math.max(0, Math.min(1, value)));
        }

        function clearSchedule() {
            clearTimeout(timer);
            timer = null;
            if (progressFrame) {
                window.cancelAnimationFrame(progressFrame);
                progressFrame = null;
            }
        }

        function renderProgress() {
            var elapsed;

            if (!progressTotal) {
                setProgress(0);
                return;
            }

            elapsed = Date.now() - progressStartedAt;
            setProgress(elapsed / progressTotal);

            if (!paused && elapsed < progressTotal) {
                progressFrame = window.requestAnimationFrame(renderProgress);
            }
        }

        function syncVideos() {
            $slides.each(function (index, slide) {
                var video = $(slide).find('video').get(0),
                    playPromise;

                if (!video) {
                    return;
                }

                if (index === current && !paused) {
                    playPromise = video.play();
                    if (playPromise && typeof playPromise.catch === 'function') {
                        playPromise.catch(function () {});
                    }
                    return;
                }

                video.pause();
                if (index !== current) {
                    video.currentTime = 0;
                }
            });
        }

        function show(index) {
            current = (index + $slides.length) % $slides.length;
            $slides.removeClass('is-active').eq(current).addClass('is-active');
            $dots.find('.dalactive-hero-dot').removeClass('is-active').eq(current).addClass('is-active');
            delayRemaining = null;
            setProgress(0);
            syncVideos();
        }

        function start() {
            var delay;

            if (!config.autoplay || paused || $slides.length < 2) {
                return;
            }

            clearSchedule();
            progressTotal = getCurrentDelay();
            delay = delayRemaining || progressTotal;
            progressStartedAt = Date.now() - (progressTotal - delay);

            timer = setTimeout(function () {
                show(current + 1);
                delayRemaining = null;
                start();
            }, delay);

            renderProgress();
        }

        if (!config.showArrows) {
            $root.find('.dalactive-hero-prev, .dalactive-hero-next').hide();
        }
        if (!config.pauseButton) {
            $root.find('.dalactive-hero-pause').hide();
        }

        if (config.showDots) {
            $slides.each(function (index) {
                $('<button type="button" class="dalactive-hero-dot" aria-label="Slide"></button>')
                    .on('click', function () {
                        show(index);
                        delayRemaining = null;
                        start();
                    })
                    .appendTo($dots);
            });
        } else {
            $dots.hide();
        }

        $root.find('.dalactive-hero-prev').on('click', function () {
            show(current - 1);
            delayRemaining = null;
            start();
        });
        $root.find('.dalactive-hero-next').on('click', function () {
            show(current + 1);
            delayRemaining = null;
            start();
        });
        $pause.on('click', function () {
            var elapsed;

            paused = !paused;
            $pause.toggleClass('is-paused', paused);
            $pause.attr('aria-label', paused ? 'Resume hero' : 'Pause hero');

            if (paused) {
                elapsed = Date.now() - progressStartedAt;
                delayRemaining = Math.max(1, progressTotal - elapsed);
                clearSchedule();
                syncVideos();
            } else {
                syncVideos();
                start();
            }
        });

        show(0);
        start();
    };
});
