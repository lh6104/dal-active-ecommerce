<?php

namespace Dalactive\StoreLocator\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateStoreGhnMetadata implements DataPatchInterface
{
    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('dalactive_storelocator_store');

        foreach ($this->getStores() as $identifier => $data) {
            $connection->update($table, $data, ['identifier = ?' => $identifier]);
        }

        $this->moduleDataSetup->endSetup();
        return $this;
    }

    public static function getDependencies(): array
    {
        return [SeedDalactiveStores::class];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function getStores(): array
    {
        return [
            'dal-active-xuan-thuy' => [
                'city' => 'Hà Nội',
                'region' => 'Cầu Giấy',
                'ward' => 'Dịch Vọng Hậu',
                'ghn_province_id' => 201,
                'ghn_district_id' => 1485,
                'ghn_ward_code' => '1A0614',
            ],
            'dal-active-lang-son' => [
                'city' => 'Lạng Sơn',
                'region' => 'Thành phố Lạng Sơn',
                'ward' => 'Cửa Nam',
                'ghn_province_id' => 209,
                'ghn_district_id' => 1847,
                'ghn_ward_code' => '340503',
            ],
            'dal-active-bac-ninh' => [
                'city' => 'Bắc Ninh',
                'region' => 'Từ Sơn',
                'ward' => 'Đồng Nguyên',
                'ghn_province_id' => 221,
                'ghn_district_id' => 1516,
                'ghn_ward_code' => '27121',
            ],
        ];
    }
}
