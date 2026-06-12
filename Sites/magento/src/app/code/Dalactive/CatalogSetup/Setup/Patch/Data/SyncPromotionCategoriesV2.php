<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SyncPromotionCategoriesV2 implements DataPatchInterface
{
    private const TARGET_CATEGORY_IDS = [8, 27];
    private const SOURCE_CATEGORY_IDS = [28, 29, 30];

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $existingTargets = $this->filterExistingCategories(self::TARGET_CATEGORY_IDS);
        $existingSources = $this->filterExistingCategories(self::SOURCE_CATEGORY_IDS);

        if ($existingTargets && $existingSources) {
            $sourceIds = implode(',', array_map('intval', $existingSources));
            foreach ($existingTargets as $targetId) {
                $connection->query(
                    sprintf(
                        'INSERT IGNORE INTO catalog_category_product (category_id, product_id, position)
                         SELECT %d, product_id, MIN(position)
                         FROM catalog_category_product
                         WHERE category_id IN (%s)
                         GROUP BY product_id',
                        (int) $targetId,
                        $sourceIds
                    )
                );
            }
        }

        $connection->endSetup();

        return $this;
    }

    private function filterExistingCategories(array $categoryIds): array
    {
        if (!$categoryIds) {
            return [];
        }

        return array_map(
            'intval',
            $this->moduleDataSetup->getConnection()->fetchCol(
                'SELECT entity_id FROM catalog_category_entity WHERE entity_id IN (?)',
                [$categoryIds]
            )
        );
    }

    public static function getDependencies(): array
    {
        return [SyncPromotionCategories::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
