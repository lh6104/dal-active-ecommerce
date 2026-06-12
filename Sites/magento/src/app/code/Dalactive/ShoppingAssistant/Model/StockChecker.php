<?php

namespace Dalactive\ShoppingAssistant\Model;

class StockChecker
{
    private ProductRecommender $productRecommender;

    public function __construct(ProductRecommender $productRecommender)
    {
        $this->productRecommender = $productRecommender;
    }

    public function check(string $message): array
    {
        $products = $this->productRecommender->searchByMessage($message, 5);
        if (!$products) {
            $products = $this->productRecommender->recommend($message, 5);
        }

        if (!$products) {
            return [
                'message' => 'Mình chưa tìm thấy sản phẩm khớp chính xác. Bạn có thể gửi link sản phẩm, SKU hoặc tên sản phẩm đầy đủ hơn để mình kiểm tra tồn hàng.',
                'products' => [],
            ];
        }

        if (count($products) === 1) {
            $product = $products[0];
            $quantity = isset($product['salable_quantity']) ? (float)$product['salable_quantity'] : null;
            if ($product['stock_status'] === 'in_stock') {
                $status = 'Sản phẩm ' . $product['name'] . ' hiện còn hàng. SKU: ' . $product['sku'] . '.';
                $status .= $quantity !== null && $quantity > 0
                    ? ' Số lượng tồn kho: ' . rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.') . ' sản phẩm.'
                    : ' Hệ thống không hiển thị số lượng tồn kho chi tiết.';
            } else {
                $status = 'Sản phẩm ' . $product['name'] . ' hiện đang hết hàng. SKU: ' . $product['sku'] . '. Bạn có thể xem các mẫu tương tự bên dưới.';
            }

            return [
                'message' => $status,
                'products' => $products,
            ];
        }

        return [
            'message' => 'Mình tìm thấy một vài sản phẩm có thể khớp với yêu cầu. Bạn chọn sản phẩm bên dưới hoặc nhập số thứ tự, ví dụ “2”, để mình kiểm tra tồn kho chính xác nhé.',
            'products' => $products,
        ];
    }
}
