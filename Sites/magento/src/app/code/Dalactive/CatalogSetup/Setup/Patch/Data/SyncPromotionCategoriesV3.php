<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SyncPromotionCategoriesV3 implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $targetIds = [8, 27];
        $sourceIds = '28,29,30';

        foreach ($targetIds as $targetId) {
            if (!$this->categoryExists($targetId)) {
                continue;
            }

            $connection->query(
                sprintf(
                    'INSERT IGNORE INTO catalog_category_product (category_id, product_id, position)
                     SELECT %d, product_id, MIN(position)
                     FROM catalog_category_product
                     WHERE category_id IN (%s)
                     GROUP BY product_id',
                    $targetId,
                    $sourceIds
                )
            );
        }

        $connection->endSetup();

        return $this;
    }

    private function categoryExists(int $categoryId): bool
    {
        return (bool) $this->moduleDataSetup->getConnection()->fetchOne(
            'SELECT 1 FROM catalog_category_entity WHERE entity_id = ?',
            [$categoryId]
        );
    }

    public static function getDependencies(): array
    {
        return [SyncPromotionCategoriesV2::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
