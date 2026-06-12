<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

class AddNikeAirForceProduct implements DataPatchInterface
{
    private const PARENT_SKU = 'NIKE-AF1-07-LV8-II9807-001';
    private const PRODUCT_NAME = "Nike Air Force 1 '07 LV8";
    private const PRICE = 3500.0;
    private const SIZE_VALUES = [
        'EU 35', 'EU 36', 'EU 37', 'EU 38', 'EU 39', 'EU 40',
        'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45',
    ];
    private const CATEGORY_IDS = [3, 4, 9, 12];

    private ModuleDataSetupInterface $moduleDataSetup;
    private ProductRepositoryInterface $productRepository;
    private ProductFactory $productFactory;
    private State $appState;
    private ?SourceItemsSaveInterface $sourceItemsSave;
    private ?SourceItemInterfaceFactory $sourceItemFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ProductRepositoryInterface $productRepository,
        ProductFactory $productFactory,
        State $appState,
        ?SourceItemsSaveInterface $sourceItemsSave = null,
        ?SourceItemInterfaceFactory $sourceItemFactory = null
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->appState = $appState;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
    }

    public function apply(): self
    {
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (LocalizedException $exception) {
            // Area code may already be set during setup.
        }

        $connection = $this->moduleDataSetup->getConnection();
        $this->moduleDataSetup->getConnection()->startSetup();

        $attributeSetId = $this->getAttributeSetId($connection, 'DAL Shoes');
        $sizeAttributeId = $this->getAttributeId($connection, 'shoe_size_eu');
        if ($attributeSetId <= 0 || $sizeAttributeId <= 0) {
            $this->moduleDataSetup->getConnection()->endSetup();
            return $this;
        }

        $parent = $this->ensureParentProduct($attributeSetId);
        $childIds = [];

        foreach (self::SIZE_VALUES as $sizeLabel) {
            $optionId = $this->getOptionId($connection, $sizeAttributeId, $sizeLabel);
            if ($optionId <= 0) {
                continue;
            }

            $child = $this->ensureChildProduct($parent, $attributeSetId, $optionId, $sizeLabel);
            if ($child && (int) $child->getId() > 0) {
                $childIds[] = (int) $child->getId();
            }
        }

        if ($childIds) {
            $this->convertParentToConfigurable($connection, (int) $parent->getId(), $attributeSetId);
            $this->linkConfigurableChildren($connection, (int) $parent->getId(), $sizeAttributeId, $childIds);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    private function ensureParentProduct(int $attributeSetId): Product
    {
        try {
            $parent = $this->productRepository->get(self::PARENT_SKU, true, null, true);
        } catch (\Throwable $exception) {
            $parent = $this->productFactory->create();
            $parent->setSku(self::PARENT_SKU);
            $parent->setTypeId(Type::TYPE_SIMPLE);
            $parent->setUrlKey('nike-air-force-1-07-lv8-ii9807-001');
        }

        $description = implode("\n\n", [
            "Comfortable, durable and timeless. The '80s construction pairs canvas underlays with leather for a textured take on the classic silhouette.",
            'Colour Shown: Anthracite/Off-Noir/Anthracite',
            'Style: II9807-001',
            'Country/Region of Origin: Vietnam',
            'Benefits: Nike Air unit provides lightweight cushioning. Rubber outsole delivers traction and durability.',
        ]);

        $parent->setName(self::PRODUCT_NAME);
        $parent->setAttributeSetId($attributeSetId);
        $parent->setStatus(Status::STATUS_ENABLED);
        $parent->setVisibility(Visibility::VISIBILITY_BOTH);
        $parent->setWebsiteIds([1]);
        $parent->setCategoryIds(self::CATEGORY_IDS);
        $parent->setPrice(self::PRICE);
        $parent->setTaxClassId(0);
        $parent->setDescription($description);
        $parent->setShortDescription("Men's Shoes - Anthracite/Off-Noir/Anthracite");
        $parent->setMetaTitle(self::PRODUCT_NAME . ' - II9807-001');
        $parent->setMetaDescription("Nike Air Force 1 '07 LV8 men's shoes, style II9807-001.");
        $parent->setData('material', 'Canvas underlays and leather');
        $parent->setStockData([
            'use_config_manage_stock' => 1,
            'manage_stock' => 1,
            'is_in_stock' => 1,
        ]);

        return $this->productRepository->save($parent);
    }

    private function ensureChildProduct(
        Product $parent,
        int $attributeSetId,
        int $sizeOptionId,
        string $sizeLabel
    ): ?Product {
        $childSku = self::PARENT_SKU . '-' . str_replace(' ', '', $sizeLabel);

        try {
            $child = $this->productRepository->get($childSku, true, null, true);
        } catch (\Throwable $exception) {
            $child = $this->productFactory->create();
            $child->setSku($childSku);
            $child->setTypeId(Type::TYPE_SIMPLE);
            $child->setUrlKey(strtolower(str_replace([' ', "'", '.'], ['-', '', ''], self::PRODUCT_NAME)) . '-' . strtolower(str_replace(' ', '', $sizeLabel)));
        }

        $child->setName(self::PRODUCT_NAME . ' - ' . $sizeLabel);
        $child->setAttributeSetId($attributeSetId);
        $child->setStatus(Status::STATUS_ENABLED);
        $child->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
        $child->setWebsiteIds($parent->getWebsiteIds() ?: [1]);
        $child->setCategoryIds(self::CATEGORY_IDS);
        $child->setPrice(self::PRICE);
        $child->setTaxClassId(0);
        $child->setDescription($parent->getDescription());
        $child->setShortDescription($parent->getShortDescription());
        $child->setData('shoe_size_eu', $sizeOptionId);
        $child->setData('material', 'Canvas underlays and leather');
        $child->setStockData([
            'use_config_manage_stock' => 0,
            'manage_stock' => 1,
            'qty' => 20,
            'is_in_stock' => 1,
        ]);

        try {
            $savedChild = $this->productRepository->save($child);
            $this->saveDefaultSourceStock($childSku);
            return $savedChild;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function convertParentToConfigurable(AdapterInterface $connection, int $parentId, int $attributeSetId): void
    {
        $connection->update(
            $this->moduleDataSetup->getTable('catalog_product_entity'),
            [
                'type_id' => 'configurable',
                'attribute_set_id' => $attributeSetId,
            ],
            ['entity_id = ?' => $parentId]
        );
    }

    private function linkConfigurableChildren(
        AdapterInterface $connection,
        int $parentId,
        int $sizeAttributeId,
        array $childIds
    ): void {
        $superAttributeId = $this->ensureSuperAttribute($connection, $parentId, $sizeAttributeId);
        if ($superAttributeId <= 0) {
            return;
        }

        foreach (array_unique($childIds) as $childId) {
            $connection->insertOnDuplicate(
                $this->moduleDataSetup->getTable('catalog_product_super_link'),
                ['parent_id' => $parentId, 'product_id' => $childId],
                ['parent_id', 'product_id']
            );
            $connection->insertOnDuplicate(
                $this->moduleDataSetup->getTable('catalog_product_relation'),
                ['parent_id' => $parentId, 'child_id' => $childId],
                ['parent_id', 'child_id']
            );
        }
    }

    private function ensureSuperAttribute(AdapterInterface $connection, int $parentId, int $attributeId): int
    {
        $table = $this->moduleDataSetup->getTable('catalog_product_super_attribute');
        $superAttributeId = (int) $connection->fetchOne(
            $connection->select()
                ->from($table, ['product_super_attribute_id'])
                ->where('product_id = ?', $parentId)
                ->where('attribute_id = ?', $attributeId)
                ->limit(1)
        );

        if ($superAttributeId > 0) {
            return $superAttributeId;
        }

        $connection->insert($table, [
            'product_id' => $parentId,
            'attribute_id' => $attributeId,
            'position' => 0,
        ]);

        $superAttributeId = (int) $connection->lastInsertId($table);
        $connection->insertOnDuplicate(
            $this->moduleDataSetup->getTable('catalog_product_super_attribute_label'),
            [
                'product_super_attribute_id' => $superAttributeId,
                'store_id' => 0,
                'use_default' => 1,
                'value' => '',
            ],
            ['use_default', 'value']
        );

        return $superAttributeId;
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
        $select = $connection->select()
            ->from(['o' => $this->moduleDataSetup->getTable('eav_attribute_option')], ['option_id'])
            ->joinInner(['v' => $this->moduleDataSetup->getTable('eav_attribute_option_value')], 'v.option_id = o.option_id', [])
            ->where('o.attribute_id = ?', $attributeId)
            ->where('v.store_id = ?', 0)
            ->where('v.value = ?', $label)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    private function saveDefaultSourceStock(string $sku): void
    {
        if (!$this->sourceItemsSave || !$this->sourceItemFactory) {
            return;
        }

        try {
            $sourceItem = $this->sourceItemFactory->create();
            $sourceItem->setSourceCode('default');
            $sourceItem->setSku($sku);
            $sourceItem->setQuantity(20);
            $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
            $this->sourceItemsSave->execute([$sourceItem]);
        } catch (\Throwable $exception) {
            // Legacy stock_data still covers this local demo product.
        }
    }

    public static function getDependencies(): array
    {
        return [AssignAccessoryProductsAttributeSet::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
