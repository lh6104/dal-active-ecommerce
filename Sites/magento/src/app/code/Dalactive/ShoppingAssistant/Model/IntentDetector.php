<?php

namespace Dalactive\ShoppingAssistant\Model;

class IntentDetector
{
    private array $keywords = [
        'stock_check' => ['kiểm tra', 'check', 'còn hàng', 'tồn hàng', 'tồn kho', 'kiểm tra tồn', 'còn không', 'còn ko', 'còn k', 'size l còn', 'màu đen còn'],
        'size_advisor' => ['size', 'số', 'chiều cao', 'cân nặng', 'vừa vặn', 'rộng', 'ôm body', 'oversize', 'mặc size gì', 'đi size gì'],
        'promotion_list' => ['ưu đãi', 'giảm giá', 'khuyến mãi', 'voucher', 'coupon', 'mã giảm giá', 'sale'],
        'store_locator' => ['cửa hàng', 'store', 'shop gần', 'địa chỉ shop', 'find a store', 'chi nhánh'],
        'exchange_rate' => ['tỷ giá', 'ty gia', 'ngoại tệ', 'usd', 'eur', 'vnd'],
        'economic_news' => ['tin tức', 'tin kinh tế', 'bài viết', 'news'],
        'weather' => ['thời tiết', 'weather', 'nhiệt độ', 'mưa', 'nắng'],
        'order_guide' => ['đặt hàng', 'mua online', 'checkout', 'cách mua'],
        'latest_products' => ['sản phẩm mới', 'hàng mới', 'mới nhất', 'new arrival'],
        'product_recommendation' => ['gợi ý', 'recommend', 'dưới', 'đi học', 'đi làm', 'chạy bộ', 'gym', 'bóng đá', 'lifestyle'],
        'shipping_policy' => ['giao hàng', 'vận chuyển', 'ship'],
        'return_policy' => ['đổi trả', 'hoàn hàng', 'return'],
        'payment_guide' => ['thanh toán', 'zalopay', 'vnpay', 'sepay', 'qr chuyển khoản'],
    ];

    public function detect(string $message): string
    {
        $normalized = mb_strtolower($message);
        foreach ($this->keywords as $intent => $words) {
            foreach ($words as $word) {
                if (mb_strpos($normalized, mb_strtolower($word)) !== false) {
                    return $intent;
                }
            }
        }

        return 'fallback';
    }
}
