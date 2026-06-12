<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class PrioritizePromotionRuleProducts implements DataPatchInterface
{
    private const PROMOTION_CATEGORY_IDS = [8, 27, 29];

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $saleProductIds = $this->getSaleProductIds();
        if ($saleProductIds) {
            foreach (self::PROMOTION_CATEGORY_IDS as $categoryId) {
                $this->addSaleProductsToCategory((int) $categoryId, $saleProductIds);
            }
        }

        $connection->endSetup();

        return $this;
    }

    private function getSaleProductIds(): array
    {
        $connection = $this->moduleDataSetup->getConnection();

        return array_map(
            'intval',
            $connection->fetchCol(
                'SELECT DISTINCT COALESCE(rel.parent_id, rule_product.product_id) AS visible_product_id
                 FROM catalogrule_product rule_product
                 LEFT JOIN catalog_product_relation rel
                   ON rel.child_id = rule_product.product_id
                 INNER JOIN catalog_product_entity entity
                   ON entity.entity_id = COALESCE(rel.parent_id, rule_product.product_id)
                 ORDER BY visible_product_id'
            )
        );
    }

    private function addSaleProductsToCategory(int $categoryId, array $productIds): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        if (!$this->categoryExists($categoryId)) {
            return;
        }

        foreach (array_values(array_unique($productIds)) as $index => $productId) {
            $position = -10000 + $index;
            $connection->query(
                'INSERT INTO catalog_category_product (category_id, product_id, position)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE position = VALUES(position)',
                [$categoryId, (int) $productId, $position]
            );
        }
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
        return [SyncPromotionCategoriesV3::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
