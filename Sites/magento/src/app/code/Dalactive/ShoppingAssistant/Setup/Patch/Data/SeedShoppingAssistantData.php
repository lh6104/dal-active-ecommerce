<?php

namespace Dalactive\ShoppingAssistant\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SeedShoppingAssistantData implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $connection = $this->moduleDataSetup->getConnection();

        $this->insertIfEmpty('dalactive_chatbot_quick_action', [
            ['label' => 'Xem sản phẩm mới nhất', 'action_type' => 'latest_products', 'sort_order' => 10],
            ['label' => 'Hỗ trợ chọn size/số', 'action_type' => 'size_advisor', 'sort_order' => 20],
            ['label' => 'Đang có những ưu đãi gì?', 'action_type' => 'promotion_list', 'sort_order' => 30],
            ['label' => 'Kiểm tra tồn hàng online', 'action_type' => 'stock_check', 'sort_order' => 40],
            ['label' => 'Hướng dẫn đặt hàng online', 'action_type' => 'order_guide', 'sort_order' => 50],
        ]);

        $this->insertIfEmpty('dalactive_chatbot_message_suggestion', [
            ['label' => 'Tư vấn size cho tôi', 'message' => 'Tôi muốn được tư vấn size', 'intent' => 'size_advisor', 'trigger_context' => 'default', 'sort_order' => 10],
            ['label' => 'Gợi ý áo dưới 400k', 'message' => 'Gợi ý cho tôi vài mẫu áo dưới 400k', 'intent' => 'product_recommendation', 'trigger_context' => 'default', 'sort_order' => 20],
            ['label' => 'Có ưu đãi nào hôm nay?', 'message' => 'Có ưu đãi nào hôm nay không?', 'intent' => 'promotion_list', 'trigger_context' => 'default', 'sort_order' => 30],
            ['label' => 'Kiểm tra sản phẩm còn hàng', 'message' => 'Kiểm tra sản phẩm còn hàng', 'intent' => 'stock_check', 'trigger_context' => 'default', 'sort_order' => 40],
            ['label' => 'Hướng dẫn đặt hàng online', 'message' => 'Hướng dẫn đặt hàng online', 'intent' => 'order_guide', 'trigger_context' => 'default', 'sort_order' => 50],
            ['label' => 'Gợi ý sản phẩm theo size này', 'message' => 'Gợi ý sản phẩm phù hợp với size vừa tư vấn', 'intent' => 'product_recommendation', 'trigger_context' => 'after_size_advisor', 'sort_order' => 10],
            ['label' => 'Tôi thích mặc rộng hơn', 'message' => 'Tôi thích mặc rộng hơn thì nên chọn size nào?', 'intent' => 'size_advisor', 'trigger_context' => 'after_size_advisor', 'sort_order' => 20],
            ['label' => 'Sản phẩm nào đang sale?', 'message' => 'Sản phẩm nào đang sale?', 'intent' => 'promotion_list', 'trigger_context' => 'after_promotion_list', 'sort_order' => 10],
            ['label' => 'Gợi ý sản phẩm còn hàng', 'message' => 'Gợi ý sản phẩm còn hàng', 'intent' => 'product_recommendation', 'trigger_context' => 'after_stock_check', 'sort_order' => 10],
        ]);

        $this->insertIfEmpty('dalactive_chatbot_size_rule', [
            ['product_type' => 'tshirt', 'gender' => 'unisex', 'height_min' => 160, 'height_max' => 168, 'weight_min' => 50, 'weight_max' => 62, 'fit_type' => 'regular', 'recommended_size' => 'M', 'note' => 'Nếu thích mặc rộng, bạn có thể tăng lên L.', 'priority' => 20],
            ['product_type' => 'tshirt', 'gender' => 'unisex', 'height_min' => 166, 'height_max' => 175, 'weight_min' => 63, 'weight_max' => 75, 'fit_type' => 'regular', 'recommended_size' => 'L', 'note' => 'Nếu thích mặc rộng có thể tăng lên XL.', 'priority' => 30],
            ['product_type' => 'tshirt', 'gender' => 'unisex', 'height_min' => 174, 'height_max' => 185, 'weight_min' => 76, 'weight_max' => 90, 'fit_type' => 'regular', 'recommended_size' => 'XL', 'note' => 'Nếu thích ôm hơn có thể thử L.', 'priority' => 20],
            ['product_type' => 'shoes', 'gender' => 'unisex', 'foot_length_min' => 24.5, 'foot_length_max' => 25.0, 'fit_type' => 'regular', 'recommended_size' => 'EU 40', 'note' => 'Nên chừa mũi khoảng 0.5cm khi vận động.', 'priority' => 20],
            ['product_type' => 'shoes', 'gender' => 'unisex', 'foot_length_min' => 25.1, 'foot_length_max' => 25.5, 'fit_type' => 'regular', 'recommended_size' => 'EU 41', 'note' => 'Nếu chân bè, bạn có thể tăng nửa đến một size.', 'priority' => 20],
            ['product_type' => 'shoes', 'gender' => 'unisex', 'foot_length_min' => 25.6, 'foot_length_max' => 26.0, 'fit_type' => 'regular', 'recommended_size' => 'EU 42', 'note' => 'Nếu nằm giữa hai size, nên chọn size lớn hơn.', 'priority' => 20],
        ]);

        $this->insertIfEmpty('dalactive_chatbot_promotion', [
            [
                'title' => 'Flash Sale 20% cho sản phẩm thể thao chọn lọc',
                'description' => 'Áp dụng cho một số sản phẩm đang được cấu hình khuyến mãi trong Magento.',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'minimum_order_amount' => null,
                'apply_channel' => 'Online Web',
                'apply_category' => 'Chạy bộ, Giày, Quần áo',
                'priority' => 50,
            ],
            [
                'title' => 'Gợi ý đơn online từ 699k',
                'description' => 'Nếu có chương trình đang bật, trợ lý sẽ so sánh giỏ hàng với mốc tối thiểu.',
                'discount_type' => 'fixed',
                'discount_value' => 100000,
                'minimum_order_amount' => 699000,
                'apply_channel' => 'Online Web',
                'priority' => 20,
            ],
        ]);

        $this->insertIfEmpty('dalactive_chatbot_knowledge_base', [
            ['title' => 'Hướng dẫn đặt hàng online', 'question' => 'Làm sao đặt hàng online?', 'answer' => 'Bạn chọn sản phẩm, chọn size/màu nếu có, bấm thêm vào giỏ hàng, kiểm tra giỏ hàng, nhập thông tin giao hàng rồi chọn phương thức thanh toán phù hợp.', 'keywords' => 'đặt hàng,mua online,checkout,cách mua', 'category' => 'order_guide', 'priority' => 50],
            ['title' => 'Hướng dẫn thanh toán', 'question' => 'DAL Active hỗ trợ thanh toán nào?', 'answer' => 'Website hỗ trợ các phương thức thanh toán đang được bật tại checkout như QR chuyển khoản, ZaloPay, VNPAY hoặc phương thức nội bộ khác nếu admin kích hoạt.', 'keywords' => 'thanh toán,zalopay,vnpay,sepay,qr', 'category' => 'payment_guide', 'priority' => 40],
            ['title' => 'Chính sách giao hàng', 'question' => 'Giao hàng thế nào?', 'answer' => 'Thông tin phí vận chuyển và thời gian giao hàng được hiển thị trong quá trình checkout theo địa chỉ nhận hàng của bạn.', 'keywords' => 'giao hàng,vận chuyển,ship', 'category' => 'shipping_policy', 'priority' => 30],
            ['title' => 'Đổi trả', 'question' => 'Có đổi trả không?', 'answer' => 'Bạn vui lòng liên hệ DAL Active để được kiểm tra điều kiện đổi trả theo đơn hàng và tình trạng sản phẩm thực tế.', 'keywords' => 'đổi trả,return,hoàn hàng', 'category' => 'return_policy', 'priority' => 30],
            ['title' => 'Bảng size', 'question' => 'Xem bảng size ở đâu?', 'answer' => 'Bạn có thể mở trang Hướng dẫn chọn size hoặc bấm nút Hướng dẫn chọn size trên trang sản phẩm để xem bảng size chi tiết.', 'keywords' => 'size,bảng size,kích cỡ', 'category' => 'size_guide', 'priority' => 40],
        ]);

        $connection->endSetup();
    }

    private function insertIfEmpty(string $tableName, array $rows): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable($tableName);
        if ((int)$connection->fetchOne($connection->select()->from($table, ['count' => 'COUNT(*)'])) > 0) {
            return;
        }

        $columns = [];
        foreach ($rows as $row) {
            $columns = array_unique(array_merge($columns, array_keys($row)));
        }

        $normalizedRows = [];
        foreach ($rows as $row) {
            $normalizedRows[] = array_replace(array_fill_keys($columns, null), $row);
        }

        $connection->insertMultiple($table, $normalizedRows);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
