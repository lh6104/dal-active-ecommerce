<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AssignAccessoryProductsAttributeSet implements DataPatchInterface
{
    private const ACCESSORY_CATEGORY_IDS = [11, 14, 17, 20, 30];

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $this->moduleDataSetup->getConnection()->startSetup();

        $setId = $this->getAttributeSetId($connection, 'DAL Accessories');
        $attributeId = $this->getAttributeId($connection, 'accessory_size');
        $oneSizeOptionId = $this->getOptionId($connection, $attributeId, 'One Size');

        if ($setId > 0) {
            $productIds = $this->getAccessoryProductIds($connection);
            if ($productIds) {
                $connection->update(
                    $this->moduleDataSetup->getTable('catalog_product_entity'),
                    ['attribute_set_id' => $setId],
                    ['entity_id IN (?)' => $productIds]
                );

                if ($attributeId > 0 && $oneSizeOptionId > 0) {
                    $this->setAccessorySize($connection, $productIds, $attributeId, $oneSizeOptionId);
                }
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    private function getAccessoryProductIds(AdapterInterface $connection): array
    {
        $productTable = $this->moduleDataSetup->getTable('catalog_product_entity');
        $categoryProductTable = $this->moduleDataSetup->getTable('catalog_category_product');
        $superLinkTable = $this->moduleDataSetup->getTable('catalog_product_super_link');

        $select = $connection->select()
            ->distinct(true)
            ->from(['e' => $productTable], ['entity_id'])
            ->joinInner(['cc' => $categoryProductTable], 'cc.product_id = e.entity_id', [])
            ->joinLeft(['sl' => $superLinkTable], 'sl.product_id = e.entity_id', [])
            ->where('e.sku LIKE ?', 'dal-accessory-%')
            ->where('e.type_id = ?', 'simple')
            ->where('cc.category_id IN (?)', self::ACCESSORY_CATEGORY_IDS)
            ->where('sl.parent_id IS NULL');

        return array_map('intval', $connection->fetchCol($select));
    }

    private function setAccessorySize(
        AdapterInterface $connection,
        array $productIds,
        int $attributeId,
        int $optionId
    ): void {
        $table = $this->moduleDataSetup->getTable('catalog_product_entity_int');
        $rows = [];
        foreach ($productIds as $productId) {
            $rows[] = [
                'attribute_id' => $attributeId,
                'store_id' => 0,
                'entity_id' => $productId,
                'value' => $optionId,
            ];
        }

        $connection->insertOnDuplicate($table, $rows, ['value']);
    }

    private function getAttributeSetId(AdapterInterface $connection, string $setName): int
    {
        $select = $connection->select()
            ->from(['s' => $this->moduleDataSetup->getTable('eav_attribute_set')], ['attribute_set_id'])
            ->joinInner(['t' => $this->moduleDataSetup->getTable('eav_entity_type')], 't.entity_type_id = s.entity_type_id', [])
            ->where('t.entity_type_code = ?', Product::ENTITY)
            ->where('s.attribute_set_name = ?', $setName)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    private function getAttributeId(AdapterInterface $connection, string $attributeCode): int
    {
        $select = $connection->select()
            ->from(['a' => $this->moduleDataSetup->getTable('eav_attribute')], ['attribute_id'])
            ->joinInner(['t' => $this->moduleDataSetup->getTable('eav_entity_type')], 't.entity_type_id = a.entity_type_id', [])
            ->where('t.entity_type_code = ?', Product::ENTITY)
            ->where('a.attribute_code = ?', $attributeCode)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    private function getOptionId(AdapterInterface $connection, int $attributeId, string $label): int
    {
        if ($attributeId <= 0) {
            return 0;
        }

        $select = $connection->select()
            ->from(['o' => $this->moduleDataSetup->getTable('eav_attribute_option')], ['option_id'])
            ->joinInner(['v' => $this->moduleDataSetup->getTable('eav_attribute_option_value')], 'v.option_id = o.option_id', [])
            ->where('o.attribute_id = ?', $attributeId)
            ->where('v.store_id = ?', 0)
            ->where('v.value = ?', $label)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    public static function getDependencies(): array
    {
        return [CreateConfigurableSizeVariants::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
