<?php

declare(strict_types=1);

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Retires products created by the former generated-catalog setup patch.
 *
 * The products are disabled rather than deleted so existing order history and
 * local references remain intact. Real catalogue data must be imported through
 * Magento's standard import flow or an approved product-information source.
 */
class DisableGeneratedDemoProducts implements DataPatchInterface
{
    private const GENERATED_SKUS = [
        'DAL-FOOTBALL-BALL-001',
        'DAL-BASKETBALL-BALL-001',
        'DAL-WRISTBAND-TRAINING-001',
        'DAL-FOOTBALL-SOCKS-001',
    ];

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        try {
            $entityTable = $this->moduleDataSetup->getTable('catalog_product_entity');
            $attributeTable = $this->moduleDataSetup->getTable('eav_attribute');
            $productIntTable = $this->moduleDataSetup->getTable('catalog_product_entity_int');
            // Magento Open Source stores product EAV values by entity_id.
            // row_id is an Adobe Commerce staging field and is not available
            // in this Magento Open Source 2.4.7 installation.
            $linkField = 'entity_id';

            $statusAttributeId = (int)$connection->fetchOne(
                $connection->select()
                    ->from($attributeTable, 'attribute_id')
                    ->where('entity_type_id = ?', 4)
                    ->where('attribute_code = ?', 'status')
            );

            if (!$statusAttributeId) {
                return $this;
            }

            $productRows = $connection->fetchAll(
                $connection->select()
                    ->from($entityTable, [$linkField])
                    ->where('sku IN (?)', self::GENERATED_SKUS)
            );

            foreach ($productRows as $productRow) {
                $connection->insertOnDuplicate(
                    $productIntTable,
                    [
                        'attribute_id' => $statusAttributeId,
                        'store_id' => 0,
                        $linkField => (int)$productRow[$linkField],
                        'value' => Status::STATUS_DISABLED,
                    ],
                    ['value']
                );
            }
        } finally {
            $connection->endSetup();
        }

        return $this;
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
