<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class PromotionProvider
{
    private ResourceConnection $resource;
    private TimezoneInterface $timezone;
    private CheckoutSession $checkoutSession;

    public function __construct(
        ResourceConnection $resource,
        TimezoneInterface $timezone,
        CheckoutSession $checkoutSession
    ) {
        $this->resource = $resource;
        $this->timezone = $timezone;
        $this->checkoutSession = $checkoutSession;
    }

    public function getActivePromotions(): array
    {
        $now = $this->timezone->date()->format('Y-m-d H:i:s');
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('dalactive_chatbot_promotion');
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table)
                ->where('status = ?', 1)
                ->where('(start_date IS NULL OR start_date <= ?)', $now)
                ->where('(end_date IS NULL OR end_date >= ?)', $now)
                ->order('priority DESC')
                ->limit(5)
        );

        $subtotal = (float)$this->checkoutSession->getQuote()->getSubtotal();
        return array_map(static function (array $row) use ($subtotal): array {
            $minimum = $row['minimum_order_amount'] !== null ? (float)$row['minimum_order_amount'] : null;
            return [
                'title' => (string)$row['title'],
                'description' => (string)$row['description'],
                'discount_type' => (string)$row['discount_type'],
                'discount_value' => $row['discount_value'] !== null ? (float)$row['discount_value'] : null,
                'minimum_order_amount' => $minimum,
                'coupon_code' => (string)$row['coupon_code'],
                'apply_channel' => (string)$row['apply_channel'],
                'apply_category' => (string)$row['apply_category'],
                'missing_amount' => $minimum && $subtotal > 0 && $subtotal < $minimum ? $minimum - $subtotal : 0,
            ];
        }, $rows);
    }
}
