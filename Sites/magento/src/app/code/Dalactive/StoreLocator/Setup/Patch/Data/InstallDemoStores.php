<?php

namespace Dalactive\StoreLocator\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallDemoStores implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('dalactive_storelocator_store');
        $stores = $this->getStores();

        foreach ($stores as $store) {
            $connection->insertOnDuplicate(
                $table,
                $store,
                ['name', 'address', 'city', 'region', 'country', 'latitude', 'longitude', 'google_maps_url', 'phone', 'email', 'opening_hours', 'is_active', 'sort_order']
            );
        }

        $this->moduleDataSetup->endSetup();
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

    private function getStores(): array
    {
        return [
            [
                'name' => 'DAL Active Xuân Thủy',
                'identifier' => 'dal-active-xuan-thuy',
                'address' => '144 Xuân Thủy, Cầu Giấy, Hà Nội, Việt Nam',
                'city' => 'Hà Nội',
                'region' => 'Cầu Giấy',
                'country' => 'VN',
                'latitude' => 21.0363890,
                'longitude' => 105.7827780,
                'google_maps_url' => '',
                'phone' => '',
                'email' => '',
                'opening_hours' => '09:00 - 21:00',
                'is_active' => 1,
                'sort_order' => 10,
            ],
            [
                'name' => 'DAL Active Tuệ Tĩnh',
                'identifier' => 'dal-active-tue-tinh',
                'address' => '16 Tuệ Tĩnh, Cửa Nam, Lạng Sơn, Việt Nam',
                'city' => 'Lạng Sơn',
                'region' => 'Cửa Nam',
                'country' => 'VN',
                'latitude' => 21.8527780,
                'longitude' => 106.7619440,
                'google_maps_url' => '',
                'phone' => '',
                'email' => '',
                'opening_hours' => '09:00 - 21:00',
                'is_active' => 1,
                'sort_order' => 20,
            ],
            [
                'name' => 'DAL Active Đồng Nguyên',
                'identifier' => 'dal-active-dong-nguyen',
                'address' => '38, khu phố 6, phường Đồng Nguyên, Việt Nam',
                'city' => 'Đồng Nguyên',
                'region' => 'Khu phố 6',
                'country' => 'VN',
                'latitude' => 21.1058330,
                'longitude' => 106.0472220,
                'google_maps_url' => '',
                'phone' => '',
                'email' => '',
                'opening_hours' => '09:00 - 21:00',
                'is_active' => 1,
                'sort_order' => 30,
            ],
        ];
    }
}
