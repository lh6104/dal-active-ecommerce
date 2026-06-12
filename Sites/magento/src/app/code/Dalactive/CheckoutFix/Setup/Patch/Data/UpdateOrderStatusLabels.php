<?php

namespace Dalactive\CheckoutFix\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;

class UpdateOrderStatusLabels implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $statusTable = $this->moduleDataSetup->getTable('sales_order_status');

        $statusesToUpdate = [
            'pending' => 'Chờ xác nhận',
            'pending_payment' => 'Chờ thanh toán',
            'processing' => 'Đang chuẩn bị hàng',
            'canceled' => 'Đã hủy',
            'complete' => 'Hoàn tất',
            'closed' => 'Đã hoàn tiền / Đã đóng',
            'dalactive_payment_failed' => 'Thanh toán thất bại',
        ];

        foreach ($statusesToUpdate as $status => $label) {
            $connection->update(
                $statusTable,
                ['label' => $label],
                ['status = ?' => $status]
            );
        }

        // Also ensure dalactive_payment_failed is mapped to canceled state correctly
        $stateTable = $this->moduleDataSetup->getTable('sales_order_status_state');
        $connection->insertOnDuplicate($stateTable, [
            'status' => 'dalactive_payment_failed',
            'state' => Order::STATE_CANCELED,
            'is_default' => 0,
            'visible_on_front' => 1,
        ], ['state', 'is_default', 'visible_on_front']);

        $connection->endSetup();

        return $this;
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
