<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Setup\CategorySetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class NormalizeDalactiveAttributeSetAssignments implements DataPatchInterface
{
    private const MANAGED_ATTRIBUTES = [
        'shoe_size_eu',
        'kids_shoe_size_eu',
        'clothing_size',
        'kids_clothing_size',
        'gender',
        'color',
        'fit',
        'sport_type',
        'material',
    ];

    private ModuleDataSetupInterface $moduleDataSetup;
    private CategorySetupFactory $categorySetupFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategorySetupFactory $categorySetupFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categorySetupFactory = $categorySetupFactory;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $categorySetup = $this->categorySetupFactory->create(['setup' => $this->moduleDataSetup]);
        $entityTypeId = (int) $categorySetup->getEntityTypeId(Product::ENTITY);

        $assignments = [
            'DAL Shoes' => ['shoe_size_eu', 'gender', 'color', 'sport_type', 'material'],
            'DAL Clothing' => ['clothing_size', 'gender', 'color', 'fit', 'sport_type', 'material'],
            'DAL Kids' => ['kids_shoe_size_eu', 'kids_clothing_size', 'gender', 'color', 'sport_type', 'material'],
        ];

        foreach ($assignments as $setName => $allowedAttributes) {
            $setId = (int) $categorySetup->getAttributeSet($entityTypeId, $setName, 'attribute_set_id');
            if ($setId <= 0) {
                continue;
            }

            $this->removeUnexpectedAttributes($entityTypeId, $setId, $allowedAttributes);
            foreach ($allowedAttributes as $index => $attributeCode) {
                $categorySetup->addAttributeToSet(
                    $entityTypeId,
                    $setId,
                    'Product Details',
                    $attributeCode,
                    100 + ($index * 10)
                );
            }
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    private function removeUnexpectedAttributes(int $entityTypeId, int $setId, array $allowedAttributes): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $attributeTable = $this->moduleDataSetup->getTable('eav_attribute');
        $entityAttributeTable = $this->moduleDataSetup->getTable('eav_entity_attribute');

        $select = $connection->select()
            ->from($attributeTable, ['attribute_id', 'attribute_code'])
            ->where('entity_type_id = ?', $entityTypeId)
            ->where('attribute_code IN (?)', self::MANAGED_ATTRIBUTES);

        $removeIds = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (!in_array($row['attribute_code'], $allowedAttributes, true)) {
                $removeIds[] = (int) $row['attribute_id'];
            }
        }

        if (!$removeIds) {
            return;
        }

        $connection->delete($entityAttributeTable, [
            'attribute_set_id = ?' => $setId,
            'attribute_id IN (?)' => $removeIds,
        ]);
    }

    public static function getDependencies(): array
    {
        return [ConfigureDalactiveSizeAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
