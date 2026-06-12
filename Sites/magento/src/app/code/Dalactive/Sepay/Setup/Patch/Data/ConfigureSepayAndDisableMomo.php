<?php

namespace Dalactive\Sepay\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ConfigureSepayAndDisableMomo implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->configWriter->save('payment/momo/active', '0');
        $this->configWriter->save('payment/sepay/active', '1');
        $this->configWriter->save('payment/sepay/title', 'QR chuyển khoản');
        $this->configWriter->save('payment/sepay/order_status', RegisterPaymentStatuses::STATUS_PROCESSING);
        $this->moduleDataSetup->getConnection()->endSetup();

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
