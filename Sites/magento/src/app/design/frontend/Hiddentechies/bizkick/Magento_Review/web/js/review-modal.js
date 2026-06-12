(function () {
    'use strict';

    var activeTrigger = null;

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function getFocusable(modal) {
        return Array.prototype.slice.call(modal.querySelectorAll(
            'a[href], button:not([disabled]), textarea, input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (item) {
            return item.offsetParent !== null;
        });
    }

    function moveNodeToBody(selector) {
        var node = document.querySelector(selector);

        if (node && node.parentNode !== document.body) {
            document.body.appendChild(node);
        }
    }

    function moveModalsToBody() {
        moveNodeToBody('[data-review-modal-overlay]');
        moveNodeToBody('[data-review-modal]');
        moveNodeToBody('[data-review-list-modal-overlay]');
        moveNodeToBody('[data-review-list-modal]');
    }

    function openModal(trigger) {
        moveModalsToBody();

        var modal = document.querySelector('[data-review-modal]');
        var overlay = document.querySelector('[data-review-modal-overlay]');
        var panel = modal ? modal.querySelector('.dal-review-modal__panel') : null;

        if (!modal || !overlay || !panel) {
            return;
        }

        activeTrigger = trigger || document.activeElement;
        overlay.hidden = false;
        modal.hidden = false;
        document.body.classList.add('dal-review-modal-open');
        document.body.classList.add('dal-modal-open');

        window.requestAnimationFrame(function () {
            overlay.classList.add('is-open');
            modal.classList.add('is-open');
            panel.focus();
        });
    }

    function openListModal(trigger) {
        moveModalsToBody();

        var modal = document.querySelector('[data-review-list-modal]');
        var overlay = document.querySelector('[data-review-list-modal-overlay]');
        var panel = modal ? modal.querySelector('.dal-review-modal__panel') : null;

        if (!modal || !overlay || !panel) {
            return;
        }

        activeTrigger = trigger || document.activeElement;
        overlay.hidden = false;
        modal.hidden = false;
        document.body.classList.add('dal-review-modal-open');
        document.body.classList.add('dal-modal-open');

        window.requestAnimationFrame(function () {
            overlay.classList.add('is-open');
            modal.classList.add('is-open');
            panel.focus();
        });
    }

    function closeDialog(modal, overlay) {
        if (!modal || !overlay || modal.hidden) {
            return;
        }

        overlay.classList.remove('is-open');
        modal.classList.remove('is-open');

        window.setTimeout(function () {
            overlay.hidden = true;
            modal.hidden = true;
            if (!document.querySelector('.dal-review-modal.is-open')) {
                document.body.classList.remove('dal-review-modal-open');
                document.body.classList.remove('dal-modal-open');
            }
            if (activeTrigger && typeof activeTrigger.focus === 'function') {
                activeTrigger.focus();
            }
        }, 180);
    }

    function closeModal() {
        closeDialog(
            document.querySelector('[data-review-modal]'),
            document.querySelector('[data-review-modal-overlay]')
        );
    }

    function closeListModal() {
        closeDialog(
            document.querySelector('[data-review-list-modal]'),
            document.querySelector('[data-review-list-modal-overlay]')
        );
    }


    function getRatingNumber(input) {
        var label = input ? document.querySelector('label[for="' + input.id + '"]') : null;
        var labelClass = label ? (label.className || '') : '';
        var match = labelClass.match(/rating-(\d+)/);

        return match ? match[1] : '';
    }

    function updateRatingStatus(input) {
        var form = input ? input.closest('.dal-review-form') : null;
        var vote = input ? input.closest('.review-control-vote') : null;
        var status = form ? form.querySelector('[data-review-rating-status]') : null;
        var ratingNumber = getRatingNumber(input);

        if (vote) {
            vote.setAttribute('data-selected-rating', ratingNumber);
            Array.prototype.forEach.call(vote.querySelectorAll('label'), function (label) {
                var match = (label.className || '').match(/rating-(\d+)/);
                var labelRating = match ? parseInt(match[1], 10) : 0;
                label.classList.toggle('is-selected', labelRating > 0 && labelRating <= parseInt(ratingNumber, 10));
            });
        }

        if (form) {
            var validateRating = form.querySelector('input.validate-rating');
            if (validateRating && ratingNumber) {
                validateRating.value = ratingNumber;
            }
        }

        if (status && ratingNumber) {
            status.textContent = 'Bạn đã chọn ' + ratingNumber + '/5 sao';
        }
    }

    function paintRatingPreview(vote, ratingNumber, className) {
        var selected = parseInt(ratingNumber, 10);

        if (!vote || !selected) {
            return;
        }

        Array.prototype.forEach.call(vote.querySelectorAll('label'), function (label) {
            var match = (label.className || '').match(/rating-(\d+)/);
            var labelRating = match ? parseInt(match[1], 10) : 0;

            label.classList.toggle(className, labelRating > 0 && labelRating <= selected);
        });
    }

    function clearRatingPreview(vote) {
        if (!vote) {
            return;
        }

        Array.prototype.forEach.call(vote.querySelectorAll('label.is-hovered'), function (label) {
            label.classList.remove('is-hovered');
        });
    }

    function prepareSimpleReviewForm(form) {
        var detail = form ? form.querySelector('textarea[name="detail"]') : null;
        var title = form ? form.querySelector('input[name="title"]') : null;
        var nickname = form ? form.querySelector('input[name="nickname"]') : null;
        var text = detail ? detail.value.trim() : '';

        if (title && !title.value.trim()) {
            title.value = text ? text.slice(0, 80) : 'Nhận xét sản phẩm';
        }

        if (nickname && !nickname.value.trim()) {
            nickname.value = 'Khách hàng DAL Active';
        }
    }

    function bindModal() {
        document.addEventListener('click', function (event) {
            var modalOpener = event.target.closest('[data-review-modal-open]');
            var openType = modalOpener ? modalOpener.getAttribute('data-review-modal-open') : '';
            var opener = event.target.closest('a[href$="#review-form"], a[href="#review-form"]');
            var listOpener = event.target.closest('[data-review-list-modal-open]');
            var closer = event.target.closest('[data-review-modal-close]');
            var listCloser = event.target.closest('[data-review-list-modal-close]');
            var overlay = event.target.matches('[data-review-modal-overlay]');
            var listOverlay = event.target.matches('[data-review-list-modal-overlay]');

            if (listOpener || openType === 'list') {
                event.preventDefault();
                openListModal(listOpener);
                return;
            }

            if (modalOpener || opener) {
                event.preventDefault();
                closeListModal();
                openModal(modalOpener || opener);
                return;
            }

            if (closer || overlay) {
                event.preventDefault();
                closeModal();
            }

            if (listCloser || listOverlay) {
                event.preventDefault();
                closeListModal();
            }
        });

        document.addEventListener('click', function (event) {
            var ratingLabel = event.target.closest('.dal-review-form .review-control-vote label');
            var input;

            if (ratingLabel) {
                input = document.getElementById(ratingLabel.getAttribute('for'));
                if (input) {
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    updateRatingStatus(input);
                }
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target.matches('.dal-review-form .review-control-vote input[type="radio"]')) {
                updateRatingStatus(event.target);
            }
        });

        document.addEventListener('mouseover', function (event) {
            var ratingLabel = event.target.closest('.dal-review-form .review-control-vote label');
            var vote;

            if (!ratingLabel) {
                return;
            }

            vote = ratingLabel.closest('.review-control-vote');
            clearRatingPreview(vote);
            paintRatingPreview(vote, getRatingNumber(document.getElementById(ratingLabel.getAttribute('for'))), 'is-hovered');
        });

        document.addEventListener('mouseout', function (event) {
            var vote = event.target.closest ? event.target.closest('.dal-review-form .review-control-vote') : null;

            if (vote && (!event.relatedTarget || !vote.contains(event.relatedTarget))) {
                clearRatingPreview(vote);
            }
        });

        document.addEventListener('submit', function (event) {
            if (event.target.matches('.dal-review-form')) {
                prepareSimpleReviewForm(event.target);
            }
        }, true);

        document.addEventListener('keydown', function (event) {
            var modal = document.querySelector('[data-review-modal]:not([hidden]), [data-review-list-modal]:not([hidden])');

            if (!modal) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeModal();
                closeListModal();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var focusable = getFocusable(modal);
            if (!focusable.length) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    ready(function () {
        moveModalsToBody();
        bindModal();
    });
}());
