<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ConfigureExtendedProductTypeAttributes implements DataPatchInterface
{
    private const FILTER_CATEGORY_IDS = [
        3, 4, 5, 6, 7, 8,
        9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
        21, 22, 23, 24, 25, 26, 28, 29, 30,
    ];

    private ModuleDataSetupInterface $moduleDataSetup;
    private EavSetupFactory $eavSetupFactory;
    private CategorySetupFactory $categorySetupFactory;
    private EavConfig $eavConfig;
    private AttributeSetFactory $attributeSetFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        CategorySetupFactory $categorySetupFactory,
        EavConfig $eavConfig,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->categorySetupFactory = $categorySetupFactory;
        $this->eavConfig = $eavConfig;
        $this->attributeSetFactory = $attributeSetFactory;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $categorySetup = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);
        $entityTypeId = (int) $categorySetup->getEntityTypeId(Product::ENTITY);
        $defaultSetId = (int) $categorySetup->getDefaultAttributeSetId($entityTypeId);

        $sets = [
            'DAL Shoes' => [
                'sort' => 110,
                'attributes' => ['shoe_size_eu', 'gender', 'color', 'sport_type', 'material', 'width'],
            ],
            'DAL Clothing' => [
                'sort' => 120,
                'attributes' => ['clothing_size', 'gender', 'color', 'fit', 'sport_type', 'material'],
            ],
            'DAL Kids' => [
                'sort' => 130,
                'attributes' => ['kids_shoe_size_eu', 'kids_clothing_size', 'gender', 'color', 'sport_type', 'material'],
            ],
            'DAL Bottoms' => [
                'sort' => 140,
                'attributes' => ['clothing_size', 'waist_size', 'gender', 'color', 'fit', 'sport_type', 'material'],
            ],
            'DAL Accessories' => [
                'sort' => 150,
                'attributes' => ['accessory_size', 'capacity', 'color', 'sport_type', 'material'],
            ],
        ];

        $this->ensureSelectAttribute($eavSetup, 'width', 'Độ rộng', ['Regular', 'Wide'], 190);
        $this->ensureSelectAttribute($eavSetup, 'waist_size', 'Vòng eo', ['28', '29', '30', '31', '32', '33', '34', '36'], 200);
        $this->ensureSelectAttribute($eavSetup, 'accessory_size', 'Kích cỡ phụ kiện', ['One Size', 'S', 'M', 'L'], 210);
        $this->ensureSelectAttribute($eavSetup, 'capacity', 'Dung tích', ['10L', '20L', '30L'], 220);

        $this->addMissingOptions($eavSetup, 'fit', ['Slim', 'Regular', 'Oversized']);
        $this->addMissingOptions($eavSetup, 'sport_type', ['Running', 'Football', 'Basketball', 'Training', 'Tennis', 'Lifestyle']);
        $this->updateFilterableAttribute($eavSetup, 'color', 1);

        $setIds = [];
        foreach ($sets as $setName => $setConfig) {
            $setIds[$setName] = $this->ensureAttributeSet(
                $categorySetup,
                $entityTypeId,
                $defaultSetId,
                $setName,
                (int) $setConfig['sort']
            );
        }

        foreach ($sets as $setName => $setConfig) {
            $this->assignAttributes(
                $categorySetup,
                $entityTypeId,
                $setIds[$setName],
                $setConfig['attributes']
            );
        }

        $this->makeCategoriesAnchor($categorySetup);

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    private function ensureAttributeSet(
        \Magento\Catalog\Setup\CategorySetup $categorySetup,
        int $entityTypeId,
        int $defaultSetId,
        string $setName,
        int $sortOrder
    ): int {
        $setId = (int) $categorySetup->getAttributeSet($entityTypeId, $setName, 'attribute_set_id');
        if ($setId > 0) {
            return $setId;
        }

        $categorySetup->addAttributeSet($entityTypeId, $setName, $sortOrder);
        $setId = (int) $categorySetup->getAttributeSet($entityTypeId, $setName, 'attribute_set_id');

        $attributeSet = $this->attributeSetFactory->create();
        $attributeSet->load($setId);
        $attributeSet->setEntityTypeId($entityTypeId);
        $attributeSet->initFromSkeleton($defaultSetId);
        $attributeSet->save();

        return $setId;
    }

    private function ensureSelectAttribute(
        \Magento\Eav\Setup\EavSetup $eavSetup,
        string $code,
        string $label,
        array $values,
        int $sortOrder
    ): void {
        $attributeId = (int) $eavSetup->getAttributeId(Product::ENTITY, $code);
        $config = [
            'type' => 'int',
            'label' => $label,
            'input' => 'select',
            'source' => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
            'required' => false,
            'sort_order' => $sortOrder,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'user_defined' => true,
            'filterable' => 1,
            'filterable_in_search' => 1,
            'searchable' => false,
            'comparable' => false,
            'unique' => false,
            'apply_to' => 'simple,configurable',
            'group' => 'Product Details',
        ];

        if ($attributeId <= 0) {
            $config['option'] = ['values' => $values];
            $eavSetup->addAttribute(Product::ENTITY, $code, $config);
            return;
        }

        $this->updateFilterableAttribute($eavSetup, $code, 1);
        $this->addMissingOptions($eavSetup, $code, $values);
    }

    private function updateFilterableAttribute(
        \Magento\Eav\Setup\EavSetup $eavSetup,
        string $code,
        int $filterable
    ): void {
        if ((int) $eavSetup->getAttributeId(Product::ENTITY, $code) <= 0) {
            return;
        }

        foreach ([
            'is_global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'is_visible' => 1,
            'is_visible_on_front' => 1,
            'is_filterable' => $filterable,
            'is_filterable_in_search' => $filterable,
            'is_searchable' => 0,
            'is_comparable' => 0,
            'used_in_product_listing' => 1,
            'apply_to' => 'simple,configurable',
        ] as $field => $value) {
            $eavSetup->updateAttribute(Product::ENTITY, $code, $field, $value);
        }
    }

    private function addMissingOptions(\Magento\Eav\Setup\EavSetup $eavSetup, string $code, array $values): void
    {
        if ((int) $eavSetup->getAttributeId(Product::ENTITY, $code) <= 0) {
            return;
        }

        $this->eavConfig->clear();
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
        $existing = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $existing[mb_strtolower((string) $option['label'])] = true;
        }

        $missing = [];
        foreach ($values as $index => $value) {
            if (!isset($existing[mb_strtolower($value)])) {
                $missing[$code . '_' . $index] = [0 => $value];
            }
        }

        if (!$missing) {
            return;
        }

        $eavSetup->addAttributeOption([
            'attribute_id' => (int) $attribute->getId(),
            'value' => $missing,
        ]);
        $this->eavConfig->clear();
    }

    private function assignAttributes(
        \Magento\Catalog\Setup\CategorySetup $categorySetup,
        int $entityTypeId,
        int $setId,
        array $attributeCodes
    ): void {
        foreach ($attributeCodes as $index => $attributeCode) {
            $categorySetup->addAttributeToSet(
                $entityTypeId,
                $setId,
                'Product Details',
                $attributeCode,
                100 + ($index * 10)
            );
        }
    }

    private function makeCategoriesAnchor(\Magento\Catalog\Setup\CategorySetup $categorySetup): void
    {
        $attributeId = (int) $categorySetup->getAttributeId(Category::ENTITY, 'is_anchor');
        if ($attributeId <= 0) {
            return;
        }

        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('catalog_category_entity_int');
        $rows = [];

        foreach (self::FILTER_CATEGORY_IDS as $categoryId) {
            $rows[] = [
                'attribute_id' => $attributeId,
                'store_id' => 0,
                'entity_id' => $categoryId,
                'value' => 1,
            ];
        }

        $connection->insertOnDuplicate(
            $table,
            $rows,
            ['value']
        );
    }

    public static function getDependencies(): array
    {
        return [NormalizeDalactiveAttributeSetAssignments::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
