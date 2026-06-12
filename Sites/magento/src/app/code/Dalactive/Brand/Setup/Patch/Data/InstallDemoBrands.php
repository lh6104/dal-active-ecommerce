<?php

namespace Dalactive\Brand\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallDemoBrands implements DataPatchInterface
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
        $table = $this->moduleDataSetup->getTable('dalactive_brand');

        foreach (['Nike', 'Adidas', 'Puma', 'New Balance', 'Asics', 'Chelsea', 'NBA'] as $brand) {
            $urlKey = strtolower(str_replace(' ', '-', $brand));
            $connection->insertOnDuplicate(
                $table,
                [
                    'name' => $brand,
                    'url_key' => $urlKey,
                    'description' => sprintf('%s sports products at DAL Active.', $brand),
                    'status' => 1,
                ],
                ['name', 'description', 'status']
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
