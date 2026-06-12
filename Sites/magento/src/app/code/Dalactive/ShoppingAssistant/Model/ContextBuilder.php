<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;

class ContextBuilder
{
    private CheckoutSession $checkoutSession;
    private CustomerSession $customerSession;

    public function __construct(
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
    }

    public function buildBaseContext(string $intent, string $message): array
    {
        $quote = $this->checkoutSession->getQuote();

        return [
            'intent' => $intent,
            'user_message' => $message,
            'customer_context' => [
                'logged_in' => $this->customerSession->isLoggedIn(),
                'customer_group' => $this->customerSession->isLoggedIn() ? $this->customerSession->getCustomerGroupId() : null,
            ],
            'cart_context' => [
                'has_cart' => (bool)$quote->getItemsCount(),
                'subtotal' => (float)$quote->getSubtotal(),
            ],
            'response_rules' => [
                'Trả lời bằng tiếng Việt.',
                'Chỉ dùng dữ liệu Magento/admin được cung cấp.',
                'Không tự tạo sản phẩm, giá, tồn kho, ưu đãi hoặc chính sách.',
                'Nếu thiếu thông tin, hỏi lại người dùng.',
            ],
        ];
    }
}
