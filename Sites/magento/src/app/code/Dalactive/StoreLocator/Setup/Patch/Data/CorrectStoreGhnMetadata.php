<?php

namespace Dalactive\StoreLocator\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class CorrectStoreGhnMetadata implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('dalactive_storelocator_store');

        $this->moduleDataSetup->startSetup();

        foreach ($this->getStoreMetadata() as $identifier => $data) {
            $connection->update($table, $data, ['identifier = ?' => $identifier]);
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            UpdateStoreGhnMetadata::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function getStoreMetadata(): array
    {
        return [
            'dal-active-xuan-thuy' => [
                'city' => 'Hà Nội',
                'region' => 'Cầu Giấy',
                'ward' => 'Dịch Vọng Hậu',
                'ghn_province_id' => 201,
                'ghn_district_id' => 1485,
                'ghn_ward_code' => '1A0602',
            ],
            'dal-active-lang-son' => [
                'city' => 'Lạng Sơn',
                'region' => 'Thành phố Lạng Sơn',
                'ward' => 'Đông Kinh',
                'ghn_province_id' => 247,
                'ghn_district_id' => 1642,
                'ghn_ward_code' => '100102',
            ],
            'dal-active-bac-ninh' => [
                'city' => 'Bắc Ninh',
                'region' => 'Từ Sơn',
                'ward' => 'Đồng Nguyên',
                'ghn_province_id' => 249,
                'ghn_district_id' => 1730,
                'ghn_ward_code' => '190505',
            ],
        ];
    }
}
