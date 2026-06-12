/**
 * DAL Active Product Review Modal
 */
define([
    'jquery',
    'domReady!'
], function ($) {
    'use strict';

    return function (config) {
        var modalOverlay = $('<div class="dal-review-modal-overlay"></div>');
        var modalContainer = $('<div class="dal-review-modal-container"></div>');
        var modalClose = $('<button type="button" class="dal-review-modal-close" aria-label="Close">&times;</button>');
        var modalContent = $('<div class="dal-review-modal-content"></div>');

        modalContainer.append(modalClose).append(modalContent);
        $('body').append(modalOverlay).append(modalContainer);

        function openModal(contentHtml) {
            modalContent.html(contentHtml);
            modalOverlay.addClass('active');
            modalContainer.addClass('active');
            $('body').css('overflow', 'hidden');
        }

        // Store original parents so we can put them back if needed
        var originalParents = {};

        // Trigger for "Viết nhận xét" (Write Review)
        $(document).on('click', '.action.add, .review-form-trigger', function (e) {
            e.preventDefault();
            var reviewForm = $('#review-form').closest('.block.review-add');
            if (reviewForm.length) {
                originalParents['reviewForm'] = reviewForm.parent();
                openModal(reviewForm);
            }
        });

        // Trigger for "Xem nhận xét" (See Reviews)
        $(document).on('click', '.action.view, .review-list-trigger', function (e) {
            e.preventDefault();
            var reviewList = $('#customer-reviews');
            var photoReviews = $('.dalactive-photo-reviews-block');
            
            var content = $('<div></div>');
            if (reviewList.length) {
                originalParents['reviewList'] = reviewList.parent();
                content.append(reviewList);
            }
            if (photoReviews.length) {
                originalParents['photoReviews'] = photoReviews.parent();
                content.append(photoReviews);
            }
            
            if (content.children().length > 0) {
                openModal(content.children());
            } else {
                openModal('<p>Chưa có nhận xét nào. Hãy là người đầu tiên đánh giá sản phẩm này.</p>');
            }
        });

        // Optional: restore elements when closing modal, but keeping them in modal or detached is fine if modal is just hidden.
        // Since we clear modalContent on close, we MUST put them back!
        function closeModal() {
            modalOverlay.removeClass('active');
            modalContainer.removeClass('active');
            $('body').css('overflow', '');
            
            // Restore elements
            var children = modalContent.children();
            children.each(function() {
                var el = $(this);
                if (el.hasClass('review-add') && originalParents['reviewForm']) {
                    originalParents['reviewForm'].append(el);
                } else if (el.attr('id') === 'customer-reviews' && originalParents['reviewList']) {
                    originalParents['reviewList'].append(el);
                } else if (el.hasClass('dalactive-photo-reviews-block') && originalParents['photoReviews']) {
                    originalParents['photoReviews'].append(el);
                }
            });
            modalContent.empty();
        }

        modalClose.off('click').on('click', closeModal);
        modalOverlay.off('click').on('click', closeModal);

        $(document).off('keyup').on('keyup', function (e) {
            if (e.key === "Escape") closeModal();
        });
    };
});
