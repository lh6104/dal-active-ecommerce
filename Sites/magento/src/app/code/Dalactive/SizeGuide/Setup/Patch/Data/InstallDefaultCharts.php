<?php

namespace Dalactive\SizeGuide\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallDefaultCharts implements DataPatchInterface
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
        $table = $this->moduleDataSetup->getTable('dalactive_sizeguide_chart');
        $charts = [
            [
                'name' => 'Default Shoes Size Chart',
                'product_type' => 'shoes',
                'gender' => 'unisex',
                'content' => 'EU 39 = 24.5cm, EU 40 = 25cm, EU 41 = 26cm, EU 42 = 26.5cm, EU 43 = 27.5cm.',
            ],
            [
                'name' => 'Default Apparel Size Chart',
                'product_type' => 'apparel',
                'gender' => 'unisex',
                'content' => 'S: Chest 86-94cm, M: 94-102cm, L: 102-110cm, XL: 110-118cm.',
            ],
        ];

        foreach ($charts as $chart) {
            $connection->insertOnDuplicate(
                $table,
                $chart + ['status' => 1],
                ['content', 'status']
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
}
