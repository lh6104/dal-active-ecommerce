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

class CreateConfigurableSizeVariants implements DataPatchInterface
{
    private const SHOE_CATEGORY_IDS = [9, 12, 15, 18, 29];
    private const CLOTHING_CATEGORY_IDS = [10, 13, 16, 19, 28];
    private const SHOE_SIZE_VALUES = [
        'EU 35', 'EU 36', 'EU 37', 'EU 38', 'EU 39', 'EU 40',
        'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45',
    ];
    private const CLOTHING_SIZE_VALUES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];

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

        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $this->createVariantsForGroup(
            $connection,
            'dal-shoes-%',
            self::SHOE_CATEGORY_IDS,
            'DAL Shoes',
            'shoe_size_eu',
            self::SHOE_SIZE_VALUES
        );
        $this->createVariantsForGroup(
            $connection,
            'dal-clothes-%',
            self::CLOTHING_CATEGORY_IDS,
            'DAL Clothing',
            'clothing_size',
            self::CLOTHING_SIZE_VALUES
        );

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    private function createVariantsForGroup(
        AdapterInterface $connection,
        string $skuLike,
        array $categoryIds,
        string $attributeSetName,
        string $sizeAttributeCode,
        array $sizeLabels
    ): void {
        $attributeSetId = $this->getAttributeSetId($attributeSetName);
        $sizeAttributeId = $this->getAttributeId($sizeAttributeCode);
        if ($attributeSetId <= 0 || $sizeAttributeId <= 0) {
            return;
        }

        $parentIds = $this->getParentCandidateIds($connection, $skuLike, $categoryIds);
        foreach ($parentIds as $parentId) {
            $parent = $this->productRepository->getById((int) $parentId, true, null, true);
            $childIds = [];

            foreach ($sizeLabels as $sizeLabel) {
                $optionId = $this->getOptionId($sizeAttributeId, $sizeLabel);
                if ($optionId <= 0) {
                    continue;
                }

                $child = $this->ensureChildProduct(
                    $parent,
                    $attributeSetId,
                    $sizeAttributeCode,
                    $optionId,
                    $sizeLabel
                );
                if ($child && (int) $child->getId() > 0) {
                    $childIds[] = (int) $child->getId();
                }
            }

            if (!$childIds) {
                continue;
            }

            $this->convertParentToConfigurable($connection, (int) $parent->getId(), $attributeSetId);
            $this->linkConfigurableChildren($connection, (int) $parent->getId(), $sizeAttributeId, $childIds);
        }
    }

    private function ensureChildProduct(
        Product $parent,
        int $attributeSetId,
        string $sizeAttributeCode,
        int $optionId,
        string $sizeLabel
    ): ?Product {
        $childSku = $this->buildChildSku((string) $parent->getSku(), $sizeLabel);
        try {
            $child = $this->productRepository->get($childSku, true, null, true);
            $child->setAttributeSetId($attributeSetId);
            $child->setData($sizeAttributeCode, $optionId);
            $child->setStatus(Status::STATUS_ENABLED);
            $child->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
            $child->setStockData($this->buildStockData());
            $this->productRepository->save($child);
            $this->saveDefaultSourceStock($childSku);
            return $child;
        } catch (\Throwable $exception) {
            // Missing SKU is expected; other save errors are retried through fresh product creation.
        }

        $child = $this->productFactory->create();
        $child->setSku($childSku);
        $child->setName(trim((string) $parent->getName() . ' - ' . $sizeLabel));
        $child->setAttributeSetId($attributeSetId);
        $child->setTypeId(Type::TYPE_SIMPLE);
        $child->setStatus(Status::STATUS_ENABLED);
        $child->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
        $child->setWebsiteIds($parent->getWebsiteIds() ?: [1]);
        $child->setCategoryIds($parent->getCategoryIds());
        $child->setPrice((float) $parent->getPrice());
        $child->setTaxClassId($parent->getTaxClassId());
        $child->setDescription($parent->getDescription());
        $child->setShortDescription($parent->getShortDescription());
        $child->setMetaTitle($parent->getMetaTitle());
        $child->setMetaDescription($parent->getMetaDescription());
        $child->setData($sizeAttributeCode, $optionId);
        $child->setData('url_key', $this->buildUrlKey((string) $parent->getUrlKey(), $childSku));
        $child->setStockData($this->buildStockData());

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

        $superLinkTable = $this->moduleDataSetup->getTable('catalog_product_super_link');
        $relationTable = $this->moduleDataSetup->getTable('catalog_product_relation');

        foreach (array_unique($childIds) as $childId) {
            $connection->insertOnDuplicate(
                $superLinkTable,
                [
                    'parent_id' => $parentId,
                    'product_id' => $childId,
                ],
                ['parent_id', 'product_id']
            );
            $connection->insertOnDuplicate(
                $relationTable,
                [
                    'parent_id' => $parentId,
                    'child_id' => $childId,
                ],
                ['parent_id', 'child_id']
            );
        }
    }

    private function ensureSuperAttribute(AdapterInterface $connection, int $parentId, int $attributeId): int
    {
        $table = $this->moduleDataSetup->getTable('catalog_product_super_attribute');
        $select = $connection->select()
            ->from($table, ['product_super_attribute_id'])
            ->where('product_id = ?', $parentId)
            ->where('attribute_id = ?', $attributeId)
            ->limit(1);

        $existingId = (int) $connection->fetchOne($select);
        if ($existingId > 0) {
            return $existingId;
        }

        $connection->insert($table, [
            'product_id' => $parentId,
            'attribute_id' => $attributeId,
            'position' => 0,
        ]);

        $superAttributeId = (int) $connection->lastInsertId($table);
        $labelTable = $this->moduleDataSetup->getTable('catalog_product_super_attribute_label');
        $connection->insertOnDuplicate(
            $labelTable,
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

    private function getParentCandidateIds(AdapterInterface $connection, string $skuLike, array $categoryIds): array
    {
        $productTable = $this->moduleDataSetup->getTable('catalog_product_entity');
        $categoryProductTable = $this->moduleDataSetup->getTable('catalog_category_product');
        $superLinkTable = $this->moduleDataSetup->getTable('catalog_product_super_link');

        $select = $connection->select()
            ->distinct(true)
            ->from(['e' => $productTable], ['entity_id'])
            ->joinInner(['cc' => $categoryProductTable], 'cc.product_id = e.entity_id', [])
            ->joinLeft(['sl' => $superLinkTable], 'sl.product_id = e.entity_id', [])
            ->where('e.sku LIKE ?', $skuLike)
            ->where('e.sku NOT REGEXP ?', '-(EU[0-9]+|XS|S|M|L|XL|XXL)$')
            ->where('e.type_id IN (?)', ['simple', 'configurable'])
            ->where('cc.category_id IN (?)', $categoryIds)
            ->where('sl.parent_id IS NULL');

        return array_map('intval', $connection->fetchCol($select));
    }

    private function getAttributeSetId(string $setName): int
    {
        $connection = $this->moduleDataSetup->getConnection();
        $setTable = $this->moduleDataSetup->getTable('eav_attribute_set');
        $entityTypeTable = $this->moduleDataSetup->getTable('eav_entity_type');

        $select = $connection->select()
            ->from(['s' => $setTable], ['attribute_set_id'])
            ->joinInner(['t' => $entityTypeTable], 't.entity_type_id = s.entity_type_id', [])
            ->where('t.entity_type_code = ?', Product::ENTITY)
            ->where('s.attribute_set_name = ?', $setName)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    private function getAttributeId(string $attributeCode): int
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('eav_attribute');
        $select = $connection->select()
            ->from($table, ['attribute_id'])
            ->where('entity_type_id = (SELECT entity_type_id FROM ' . $this->moduleDataSetup->getTable('eav_entity_type') . ' WHERE entity_type_code = ?)', Product::ENTITY)
            ->where('attribute_code = ?', $attributeCode)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    private function getOptionId(int $attributeId, string $label): int
    {
        $connection = $this->moduleDataSetup->getConnection();
        $optionTable = $this->moduleDataSetup->getTable('eav_attribute_option');
        $valueTable = $this->moduleDataSetup->getTable('eav_attribute_option_value');

        $select = $connection->select()
            ->from(['o' => $optionTable], ['option_id'])
            ->joinInner(['v' => $valueTable], 'v.option_id = o.option_id', [])
            ->where('o.attribute_id = ?', $attributeId)
            ->where('v.store_id = ?', 0)
            ->where('v.value = ?', $label)
            ->limit(1);

        return (int) $connection->fetchOne($select);
    }

    private function buildChildSku(string $parentSku, string $sizeLabel): string
    {
        $suffix = strtoupper(str_replace([' ', '.'], '', $sizeLabel));
        return strtoupper($parentSku . '-' . $suffix);
    }

    private function buildUrlKey(string $parentUrlKey, string $childSku): string
    {
        $base = $parentUrlKey !== '' ? $parentUrlKey : strtolower($childSku);
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $base . '-' . $childSku));
    }

    private function buildStockData(): array
    {
        return [
            'use_config_manage_stock' => 0,
            'manage_stock' => 1,
            'qty' => 20,
            'is_in_stock' => 1,
        ];
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
            // Legacy stock_data still covers non-MSI installs and local demo data.
        }
    }

    public static function getDependencies(): array
    {
        return [ConfigureExtendedProductTypeAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
