<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class ConfigureDalactiveSizeAttributes implements DataPatchInterface
{
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

        $shoesSetId = $this->ensureAttributeSet($categorySetup, $entityTypeId, $defaultSetId, 'DAL Shoes', 110);
        $clothingSetId = $this->ensureAttributeSet($categorySetup, $entityTypeId, $defaultSetId, 'DAL Clothing', 120);
        $kidsSetId = $this->ensureAttributeSet($categorySetup, $entityTypeId, $defaultSetId, 'DAL Kids', 130);

        $this->ensureSelectAttribute($eavSetup, 'shoe_size_eu', 'Kích cỡ giày', [
            'EU 35', 'EU 36', 'EU 37', 'EU 38', 'EU 39', 'EU 40',
            'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45',
        ], 110);
        $this->ensureSelectAttribute($eavSetup, 'kids_shoe_size_eu', 'Kích cỡ giày trẻ em', [
            'EU 28', 'EU 29', 'EU 30', 'EU 31', 'EU 32', 'EU 33', 'EU 34', 'EU 35', 'EU 36',
        ], 120);
        $this->ensureSelectAttribute($eavSetup, 'clothing_size', 'Kích cỡ quần áo', [
            'XS', 'S', 'M', 'L', 'XL', 'XXL',
        ], 130);
        $this->ensureSelectAttribute($eavSetup, 'kids_clothing_size', 'Kích cỡ quần áo trẻ em', [
            '110', '120', '130', '140', '150', '160',
        ], 140);
        $this->ensureSelectAttribute($eavSetup, 'gender', 'Giới tính', [
            'Nam', 'Nữ', 'Trẻ em', 'Unisex',
        ], 150);
        $this->ensureSelectAttribute($eavSetup, 'fit', 'Form dáng', [
            'Ôm vừa', 'Tiêu chuẩn', 'Rộng rãi',
        ], 160);
        $this->ensureSelectAttribute($eavSetup, 'sport_type', 'Môn thể thao', [
            'Chạy bộ', 'Bóng đá', 'Bóng rổ', 'Tập luyện', 'Tennis', 'Lifestyle',
        ], 170);
        $this->ensureTextAttribute($eavSetup, 'material', 'Chất liệu', 180);
        $this->updateExistingColorAttribute($eavSetup);

        $this->assignAttributes($categorySetup, $entityTypeId, $shoesSetId, [
            'shoe_size_eu', 'gender', 'color', 'sport_type', 'material',
        ]);
        $this->assignAttributes($categorySetup, $entityTypeId, $clothingSetId, [
            'clothing_size', 'gender', 'color', 'fit', 'sport_type', 'material',
        ]);
        $this->assignAttributes($categorySetup, $entityTypeId, $kidsSetId, [
            'kids_shoe_size_eu', 'kids_clothing_size', 'gender', 'color', 'sport_type', 'material',
        ]);

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
        } else {
            $this->updateCatalogAttribute($eavSetup, $code, 1);
            $this->addMissingOptions($eavSetup, $code, $values);
        }
    }

    private function ensureTextAttribute(
        \Magento\Eav\Setup\EavSetup $eavSetup,
        string $code,
        string $label,
        int $sortOrder
    ): void {
        $attributeId = (int) $eavSetup->getAttributeId(Product::ENTITY, $code);
        $config = [
            'type' => 'varchar',
            'label' => $label,
            'input' => 'text',
            'required' => false,
            'sort_order' => $sortOrder,
            'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'user_defined' => true,
            'filterable' => 0,
            'filterable_in_search' => 0,
            'searchable' => false,
            'comparable' => false,
            'unique' => false,
            'apply_to' => 'simple,configurable',
            'group' => 'Product Details',
        ];

        if ($attributeId <= 0) {
            $eavSetup->addAttribute(Product::ENTITY, $code, $config);
            return;
        }

        $this->updateCatalogAttribute($eavSetup, $code, 0);
    }

    private function updateExistingColorAttribute(\Magento\Eav\Setup\EavSetup $eavSetup): void
    {
        if ((int) $eavSetup->getAttributeId(Product::ENTITY, 'color') <= 0) {
            return;
        }

        $this->updateCatalogAttribute($eavSetup, 'color', 1);
    }

    private function updateCatalogAttribute(
        \Magento\Eav\Setup\EavSetup $eavSetup,
        string $code,
        int $filterable
    ): void {
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
        $this->eavConfig->clear();
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
        $existing = [];
        foreach ($attribute->getSource()->getAllOptions(false) as $option) {
            $existing[mb_strtolower((string) $option['label'])] = true;
        }

        $missing = [];
        foreach ($values as $index => $value) {
            if (!isset($existing[mb_strtolower($value)])) {
                $missing[$code . '_' . $index] = [$value];
            }
        }

        if ($missing) {
            $eavSetup->addAttributeOption([
                'attribute_id' => (int) $attribute->getId(),
                'values' => $missing,
            ]);
            $this->eavConfig->clear();
        }
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

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
