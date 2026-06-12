<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class MarkNikeAirForceConfigurableSalable implements DataPatchInterface
{
    private const PARENT_SKU = 'NIKE-AF1-07-LV8-II9807-001';

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $productId = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->moduleDataSetup->getTable('catalog_product_entity'), ['entity_id'])
                ->where('sku = ?', self::PARENT_SKU)
                ->limit(1)
        );

        if ($productId > 0) {
            $connection->insertOnDuplicate(
                $this->moduleDataSetup->getTable('cataloginventory_stock_status'),
                [
                    'product_id' => $productId,
                    'website_id' => 0,
                    'stock_id' => 1,
                    'qty' => 0,
                    'stock_status' => 1,
                ],
                ['stock_status']
            );
        }

        $connection->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [FixNikeAirForceProductStock::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
