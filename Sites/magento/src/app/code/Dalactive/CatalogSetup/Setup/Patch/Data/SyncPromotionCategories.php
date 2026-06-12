<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class SyncPromotionCategories implements DataPatchInterface
{
    private const TARGET_CATEGORY_NAMES = [
        'Khuyến mãi',
        'Tất cả khuyến mãi',
    ];

    private const SOURCE_CATEGORY_NAMES = [
        'Quần áo giảm giá',
        'Giày giảm giá',
        'Phụ kiện giảm giá',
    ];

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $this->moduleDataSetup->getConnection()->startSetup();

        $targetIds = $this->getCategoryIdsByNames(self::TARGET_CATEGORY_NAMES);
        $sourceIds = $this->getCategoryIdsByNames(self::SOURCE_CATEGORY_NAMES);

        if ($targetIds && $sourceIds) {
            foreach ($targetIds as $targetId) {
                $this->copyProductsToCategory((int) $targetId, array_map('intval', $sourceIds));
            }
        }

        $connection->endSetup();

        return $this;
    }

    private function getCategoryIdsByNames(array $names): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $nameAttributeId = (int) $connection->fetchOne(
            'SELECT attribute_id FROM eav_attribute WHERE entity_type_id = ? AND attribute_code = ?',
            [3, 'name']
        );

        if ($nameAttributeId <= 0) {
            return [];
        }

        return $connection->fetchCol(
            'SELECT DISTINCT c.entity_id
             FROM catalog_category_entity c
             INNER JOIN catalog_category_entity_varchar v
                ON v.entity_id = c.entity_id
               AND v.attribute_id = ?
             WHERE v.value IN (?)',
            [$nameAttributeId, $names]
        );
    }

    private function copyProductsToCategory(int $targetCategoryId, array $sourceCategoryIds): void
    {
        if (!$sourceCategoryIds) {
            return;
        }

        $connection = $this->moduleDataSetup->getConnection();
        $sourceIds = implode(',', array_map('intval', $sourceCategoryIds));

        $connection->query(
            sprintf(
                'INSERT IGNORE INTO catalog_category_product (category_id, product_id, position)
                 SELECT %d, product_id, MIN(position)
                 FROM catalog_category_product
                 WHERE category_id IN (%s)
                 GROUP BY product_id',
                $targetCategoryId,
                $sourceIds
            )
        );
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
