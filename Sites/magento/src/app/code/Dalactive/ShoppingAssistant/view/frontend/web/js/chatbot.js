define(['jquery'], function ($) {
    'use strict';

    return function (config, element) {
        var root = $(element);
        var panel = root.find('.dal-chatbot__panel');
        var messages = root.find('.dal-chatbot__messages');
        var input = root.find('.dal-chatbot__input');
        var send = root.find('.dal-chatbot__send');
        var counter = root.find('.dal-chatbot__counter');
        var error = root.find('.dal-chatbot__error');
        var suggestions = root.find('.dal-chatbot__suggestions');
        var quickActions = root.find('.dal-chatbot__quick-actions');
        var pending = false;
        var maxLength = parseInt(config.maxLength, 10) || 500;
        var maxQuickActions = 4;
        var maxSuggestions = 5;
        var lastProducts = [];
        var suggestionTimer = null;
        var suggestionsVisible = true;

        root.find('.dal-chatbot__name').text(config.botName || 'DAL Assistant');
        counter.text('0/' + maxLength);

        function escapeHtml(value) {
            return $('<div/>').text(value || '').html();
        }

        function scrollToBottom() {
            messages.scrollTop(messages[0].scrollHeight);
        }

        function appendMessage(type, text) {
            messages.append(
                '<div class="dal-chatbot__message dal-chatbot__message--' + type + '">' +
                escapeHtml(text) +
                '</div>'
            );
            scrollToBottom();
        }

        function hideSuggestions() {
            suggestionsVisible = false;
            quickActions.addClass('is-hidden');
            if (suggestionTimer) {
                window.clearTimeout(suggestionTimer);
                suggestionTimer = null;
            }
        }

        function scheduleSuggestions() {
            if (suggestionTimer) {
                window.clearTimeout(suggestionTimer);
            }
            suggestionTimer = window.setTimeout(function () {
                if (!pending && $.trim(input.val()) === '') {
                    suggestionsVisible = true;
                    quickActions.removeClass('is-hidden');
                }
            }, 8000);
        }

        function compactLabel(label) {
            var value = $.trim(label || '');
            var lower = value.toLowerCase();

            if (lower.indexOf('size') !== -1 || lower.indexOf('kích') !== -1 || lower.indexOf('số') !== -1) {
                return lower.indexOf('theo') !== -1 ? 'Gợi ý theo size này' : 'Chọn size/số';
            }
            if (lower.indexOf('400') !== -1 || lower.indexOf('áo') !== -1) {
                return 'Áo dưới 400k';
            }
            if (lower.indexOf('ưu đãi') !== -1 || lower.indexOf('sale') !== -1) {
                return 'Ưu đãi hôm nay';
            }
            if (lower.indexOf('tồn') !== -1 || lower.indexOf('còn hàng') !== -1) {
                return 'Kiểm tra tồn hàng';
            }
            if (lower.indexOf('mới') !== -1) {
                return 'Sản phẩm mới nhất';
            }
            if (lower.indexOf('đặt hàng') !== -1 || lower.indexOf('online') !== -1) {
                return 'Hướng dẫn đặt hàng';
            }

            return value;
        }

        function defaultSuggestions(intent) {
            var map = {
                size_advisor: [
                    {label: 'Gợi ý theo size này', message: 'Gợi ý sản phẩm phù hợp với size vừa tư vấn'},
                    {label: 'Tôi thích mặc rộng', message: 'Tôi thích mặc rộng hơn thì nên chọn size nào?'},
                    {label: 'Xem áo', message: 'Gợi ý cho tôi vài mẫu áo thể thao'}
                ],
                product_recommendation: [
                    {label: 'Xem thêm mẫu tương tự', message: 'Xem thêm mẫu tương tự'},
                    {label: 'Lọc dưới 400k', message: 'Gợi ý sản phẩm dưới 400k'},
                    {label: 'Chỉ còn hàng', message: 'Chỉ xem sản phẩm còn hàng'}
                ],
                promotion_list: [
                    {label: 'Ưu đãi hợp với giỏ hàng', message: 'Ưu đãi nào hợp với giỏ hàng của tôi?'},
                    {label: 'Sản phẩm đang sale', message: 'Sản phẩm nào đang sale?'},
                    {label: 'Còn thiếu bao nhiêu?', message: 'Tôi còn thiếu bao nhiêu để đạt ưu đãi?'}
                ],
                stock_check: [
                    {label: 'Kiểm tra size khác', message: 'Kiểm tra size khác'},
                    {label: 'Xem mẫu tương tự', message: 'Xem mẫu tương tự còn hàng'},
                    {label: 'Chỉ xem còn hàng', message: 'Chỉ xem sản phẩm còn hàng'}
                ]
            };

            return map[intent] || [
                {label: 'Gợi ý sản phẩm', message: 'Gợi ý sản phẩm phù hợp cho tôi'},
                {label: 'Tư vấn size', message: 'Tôi muốn được tư vấn size'},
                {label: 'Áo dưới 400k', message: 'Gợi ý cho tôi vài mẫu áo dưới 400k'},
                {label: 'Ưu đãi hôm nay', message: 'Có ưu đãi nào hôm nay không?'}
            ];
        }

        function renderProducts(products) {
            if (!products || !products.length) {
                return;
            }

            lastProducts = products.slice(0, 8);
            var html = '<div class="dal-chatbot__products-wrap"><div class="dal-chatbot__products">';
            products.forEach(function (product, index) {
                var url = product.productUrl || product.url || '#';
                var image = product.imageUrl || product.image || '';
                var stock = product.stockStatus || product.stock_status || '';
                var stockLabel = stock === 'in_stock' ? 'Còn hàng' : (stock === 'out_of_stock' ? 'Hết hàng' : '');
                html += '<a class="dal-chatbot__product" href="' + escapeHtml(url) + '">' +
                    (image ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(product.name) + '" loading="lazy"/>' : '') +
                    '<span class="dal-chatbot__product-name">' + (index + 1) + '. ' + escapeHtml(product.name) + '</span>' +
                    '<strong>' + escapeHtml(product.price_text || '') + '</strong>' +
                    (stockLabel ? '<small class="dal-chatbot__stock dal-chatbot__stock--' + escapeHtml(stock) + '">' + escapeHtml(stockLabel) + '</small>' : '') +
                    '<em>Xem sản phẩm</em>' +
                    '</a>';
            });
            html += '</div></div>';
            messages.append(html);
            scrollToBottom();
        }

        function renderModuleItems(items) {
            if (!items || !items.length) {
                return;
            }

            var html = '<div class="dal-chatbot__module-items">';
            items.forEach(function (item) {
                var url = item.url || '#';
                var tag = url === '#' ? 'div' : 'a';
                html += '<' + tag + ' class="dal-chatbot__module-card"' + (url === '#' ? '' : ' href="' + escapeHtml(url) + '"') + '>' +
                    (item.image ? '<img src="' + escapeHtml(item.image) + '" alt="' + escapeHtml(item.title) + '" loading="lazy"/>' : '') +
                    '<strong>' + escapeHtml(item.title || '') + '</strong>' +
                    (item.subtitle ? '<small>' + escapeHtml(item.subtitle) + '</small>' : '') +
                    (item.description ? '<span>' + escapeHtml(item.description) + '</span>' : '') +
                    (item.meta ? '<em>' + escapeHtml(item.meta) + '</em>' : '') +
                    '</' + tag + '>';
            });
            html += '</div>';
            messages.append(html);
            scrollToBottom();
        }

        function renderPromotions(promotions) {
            if (!promotions || !promotions.length) {
                return;
            }

            var html = '<div class="dal-chatbot__promotions">';
            promotions.forEach(function (promotion) {
                html += '<div class="dal-chatbot__promotion"><strong>' + escapeHtml(promotion.title) + '</strong><span>' +
                    escapeHtml(promotion.description || '') + '</span></div>';
            });
            html += '</div>';
            messages.append(html);
            scrollToBottom();
        }

        function renderSuggestions(items, intent) {
            quickActions.empty();
            (items && items.length ? items : defaultSuggestions(intent)).slice(0, maxSuggestions).forEach(function (item) {
                $('<button/>', {
                    type: 'button',
                    class: 'dal-chatbot__quick',
                    text: compactLabel(item.label || item.message || ''),
                    'data-message': item.message || item.label || ''
                }).appendTo(quickActions);
            });
            quickActions.toggleClass('is-hidden', !suggestionsVisible);
        }

        function renderQuickActions(items) {
            quickActions.empty();
            (items || []).slice(0, maxQuickActions).forEach(function (item) {
                $('<button/>', {
                    type: 'button',
                    class: 'dal-chatbot__quick',
                    text: compactLabel(item.label || ''),
                    'data-message': item.message || item.label || ''
                }).appendTo(quickActions);
            });
            quickActions.toggleClass('is-hidden', !suggestionsVisible);
        }

        function growInput() {
            input.css('height', 'auto');
            input.css('height', Math.min(input[0].scrollHeight, 96) + 'px');
        }

        function updateState() {
            var length = $.trim(input.val()).length;
            counter.text(length + '/' + maxLength);
            counter.toggleClass('is-error', length > maxLength);
            error.text(length > maxLength ? 'Tin nhắn vượt quá giới hạn.' : '');
            send.prop('disabled', pending || length === 0 || length > maxLength);
            if (length > 0) {
                hideSuggestions();
            } else if (!pending) {
                scheduleSuggestions();
            }
            growInput();
        }

        function sendMessage(message) {
            message = $.trim(message || input.val());
            if (!message || message.length > maxLength || pending) {
                updateState();
                return;
            }

            var sentMessage = $.trim(message);
            pending = true;
            hideSuggestions();
            updateState();
            appendMessage('user', message);
            input.val('');
            growInput();
            appendMessage('bot dal-chatbot__message--loading', 'Đang kiểm tra dữ liệu DAL Active...');

            $.ajax({
                url: config.endpoint,
                method: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                    form_key: config.formKey,
                    message: sentMessage
                })
            }).done(function (response) {
                messages.find('.dal-chatbot__message--loading').last().remove();
                appendMessage('bot', response.message || 'Mình chưa có câu trả lời phù hợp.');
                renderProducts(response.products);
                renderModuleItems(response.module_items);
                renderPromotions(response.promotions);
                renderSuggestions(response.suggestions && response.suggestions.length ? response.suggestions : [], response.intent);
            }).fail(function (xhr) {
                var response = xhr.responseJSON || {};
                messages.find('.dal-chatbot__message--loading').last().remove();
                appendMessage('bot', response.message || 'Trợ lý đang gặp lỗi tạm thời. Bạn thử lại sau nhé.');
                renderSuggestions([], null);
            }).always(function () {
                pending = false;
                updateState();
                scheduleSuggestions();
            });
        }

        root.find('.dal-chatbot__toggle').on('click', function () {
            root.addClass('is-opened');
            panel.attr('aria-hidden', 'false').addClass('is-open');
            input.trigger('focus');
        });

        root.find('.dal-chatbot__close').on('click', function () {
            root.removeClass('is-opened');
            panel.attr('aria-hidden', 'true').removeClass('is-open');
        });

        input.on('input', updateState);
        input.on('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        });
        root.find('.dal-chatbot__form').on('submit', function (event) {
            event.preventDefault();
            sendMessage();
        });
        root.on('click', '.dal-chatbot__suggestion, .dal-chatbot__quick', function () {
            hideSuggestions();
            root.find('.dal-chatbot__quick, .dal-chatbot__suggestion').removeClass('is-active');
            $(this).addClass('is-active');
            sendMessage($(this).data('message'));
        });

        appendMessage('bot', config.welcomeMessage || 'Xin chào, mình có thể hỗ trợ mua sắm cho bạn.');
        renderQuickActions(config.quickActions || []);
        renderSuggestions(config.suggestions || [], null);
        updateState();
    };
});
