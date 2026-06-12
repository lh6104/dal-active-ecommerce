<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateVietnameseProductAttributeLabels implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private EavSetupFactory $eavSetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        foreach ($this->getLabels() as $attributeCode => $label) {
            if ((int) $eavSetup->getAttributeId(Product::ENTITY, $attributeCode) <= 0) {
                continue;
            }

            $eavSetup->updateAttribute(Product::ENTITY, $attributeCode, 'frontend_label', $label);
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    private function getLabels(): array
    {
        return [
            'shoe_size_eu' => 'Kích cỡ giày',
            'kids_shoe_size_eu' => 'Kích cỡ giày trẻ em',
            'clothing_size' => 'Kích cỡ quần áo',
            'kids_clothing_size' => 'Kích cỡ quần áo trẻ em',
            'gender' => 'Giới tính',
            'color' => 'Màu sắc',
            'fit' => 'Form dáng',
            'sport_type' => 'Môn thể thao',
            'material' => 'Chất liệu',
            'width' => 'Độ rộng',
            'waist_size' => 'Vòng eo',
            'accessory_size' => 'Kích cỡ phụ kiện',
            'capacity' => 'Dung tích',
        ];
    }

    public static function getDependencies(): array
    {
        return [
            ConfigureDalactiveSizeAttributes::class,
            ConfigureExtendedProductTypeAttributes::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }
}
