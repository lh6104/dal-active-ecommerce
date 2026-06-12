define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var $element = $(element),
            statusUrl = $element.data('status-url'),
            failUrl = $element.data('fail-url'),
            successUrl = $element.data('success-url'),
            timeout = parseInt($element.data('timeout'), 10) || 900,
            $countdown = $element.find('[data-role="sepay-countdown"]'),
            $statusLabel = $element.find('[data-role="sepay-status-label"]'),
            remaining = timeout,
            statusTimer,
            countdownTimer,
            failed = false;

        function setStatus(label, className) {
            $statusLabel.text(label);
            $element
                .removeClass('is-processing is-success is-failed')
                .addClass(className);
        }

        function markFailed() {
            if (failed) {
                return;
            }

            failed = true;
            setStatus('Thất bại', 'is-failed');
            $countdown.text('');
            clearInterval(countdownTimer);
            clearInterval(statusTimer);

            if (failUrl) {
                $.post(failUrl);
            }
        }

        function updateCountdown() {
            var minutes = Math.floor(remaining / 60),
                seconds = remaining % 60;

            $countdown.text('(' + minutes + ':' + String(seconds).padStart(2, '0') + ')');
            remaining -= 1;

            if (remaining < 0) {
                markFailed();
            }
        }

        function checkStatus() {
            $.getJSON(statusUrl).done(function (response) {
                if (response && response.payment_status_label) {
                    setStatus(response.payment_status_label, 'is-' + response.payment_status);
                }

                if (response && response.paid) {
                    clearInterval(countdownTimer);
                    clearInterval(statusTimer);
                    setStatus('Thành công', 'is-success');
                    window.location.href = successUrl;
                } else if (response && response.failed) {
                    markFailed();
                }
            });
        }

        setStatus('Đang xử lý', 'is-processing');
        updateCountdown();
        checkStatus();
        countdownTimer = setInterval(updateCountdown, 1000);
        statusTimer = setInterval(checkStatus, 5000);
    };
});
