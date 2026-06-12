<?php

namespace Dalactive\Sepay\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Sales\Model\Order;

class RegisterPaymentStatuses implements DataPatchInterface
{
    public const STATUS_PROCESSING = 'dalactive_payment_processing';
    public const STATUS_SUCCESS = 'dalactive_payment_success';
    public const STATUS_FAILED = 'dalactive_payment_failed';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $statusTable = $this->moduleDataSetup->getTable('sales_order_status');
        $stateTable = $this->moduleDataSetup->getTable('sales_order_status_state');

        $connection->insertOnDuplicate($statusTable, [
            ['status' => self::STATUS_PROCESSING, 'label' => 'Đang xử lý'],
            ['status' => self::STATUS_SUCCESS, 'label' => 'Thành công'],
            ['status' => self::STATUS_FAILED, 'label' => 'Thất bại'],
        ], ['label']);

        $connection->insertOnDuplicate($stateTable, [
            [
                'status' => self::STATUS_PROCESSING,
                'state' => Order::STATE_PENDING_PAYMENT,
                'is_default' => 0,
                'visible_on_front' => 1,
            ],
            [
                'status' => self::STATUS_SUCCESS,
                'state' => Order::STATE_PROCESSING,
                'is_default' => 0,
                'visible_on_front' => 1,
            ],
            [
                'status' => self::STATUS_FAILED,
                'state' => Order::STATE_CANCELED,
                'is_default' => 0,
                'visible_on_front' => 1,
            ],
        ], ['is_default', 'visible_on_front']);

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
