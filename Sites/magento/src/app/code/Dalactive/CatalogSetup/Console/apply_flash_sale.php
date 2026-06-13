<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;

require __DIR__ . '/../../../../../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

try {
    $om->get(State::class)->setAreaCode('adminhtml');
} catch (Throwable $e) {
}

$resource = $om->get(ResourceConnection::class);
$connection = $resource->getConnection();

$catalogRuleTable = $resource->getTableName('catalogrule');
$categoryProductTable = $resource->getTableName('catalog_category_product');
$rulePriceTable = $resource->getTableName('catalogrule_product_price');
$productEntityTable = $resource->getTableName('catalog_product_entity');
$relationTable = $resource->getTableName('catalog_product_relation');
$eavAttributeTable = $resource->getTableName('eav_attribute');
$varcharTable = $resource->getTableName('catalog_product_entity_varchar');
$intTable = $resource->getTableName('catalog_product_entity_int');

$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$fromDate = $today->format('Y-m-d');
$toDate = $today->modify('+14 days')->format('Y-m-d');
$syncOnly = in_array('--sync-only', $argv ?? [], true);

$ruleId = (int) $connection->fetchOne(
    "SELECT rule_id FROM {$catalogRuleTable} WHERE name = ? ORDER BY rule_id DESC LIMIT 1",
    ['Flash Sale Running Shoes']
);

if (!$syncOnly && $ruleId > 0) {
    $connection->update(
        $catalogRuleTable,
        [
            'is_active' => 1,
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ],
        ['rule_id = ?' => $ruleId]
    );
    echo "Flash sale rule #{$ruleId} active from {$fromDate} to {$toDate}\n";
} elseif (!$syncOnly) {
    echo "Flash sale rule not found; skipped date refresh.\n";
}

$todayDate = $today->format('Y-m-d');
$promotionCategoryIds = [8, 27, 28, 29, 30];
$allPromotionCategoryIds = [8, 27];
$clothingCategoryId = 28;
$shoeCategoryId = 29;
$accessoryCategoryId = 30;

$saleProductIds = array_map(
    'intval',
    $connection->fetchCol(
        "SELECT DISTINCT COALESCE(rel.parent_id, price.product_id) AS visible_product_id
         FROM {$rulePriceTable} price
         LEFT JOIN {$relationTable} rel ON rel.child_id = price.product_id
         INNER JOIN {$productEntityTable} entity
            ON entity.entity_id = COALESCE(rel.parent_id, price.product_id)
         WHERE price.rule_date = ?
         ORDER BY visible_product_id",
        [$todayDate]
    )
);

$connection->delete($categoryProductTable, ['category_id IN (?)' => $promotionCategoryIds]);

if (!$saleProductIds) {
    echo "No active catalog-rule products for {$todayDate}; promotion categories cleared.\n";
    exit(0);
}

$nameAttributeId = (int) $connection->fetchOne(
    "SELECT attribute_id FROM {$eavAttributeTable} WHERE entity_type_id = 4 AND attribute_code = 'name'"
);
$shoeSizeAttributeId = (int) $connection->fetchOne(
    "SELECT attribute_id FROM {$eavAttributeTable} WHERE entity_type_id = 4 AND attribute_code = 'shoe_size_eu'"
);
$clothingSizeAttributeId = (int) $connection->fetchOne(
    "SELECT attribute_id FROM {$eavAttributeTable} WHERE entity_type_id = 4 AND attribute_code = 'clothing_size'"
);
$accessorySizeAttributeId = (int) $connection->fetchOne(
    "SELECT attribute_id FROM {$eavAttributeTable} WHERE entity_type_id = 4 AND attribute_code = 'accessory_size'"
);

$insert = static function (int $categoryId, int $productId, int $position) use ($connection, $categoryProductTable): void {
    $connection->query(
        "INSERT INTO {$categoryProductTable} (category_id, product_id, position)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE position = VALUES(position)",
        [$categoryId, $productId, $position]
    );
};

foreach (array_values(array_unique($saleProductIds)) as $index => $productId) {
    $position = $index + 1;
    foreach ($allPromotionCategoryIds as $categoryId) {
        $insert($categoryId, $productId, $position);
    }

    $name = '';
    if ($nameAttributeId > 0) {
        $name = (string) $connection->fetchOne(
            "SELECT value FROM {$varcharTable}
             WHERE entity_id = ? AND attribute_id = ?
             ORDER BY store_id DESC LIMIT 1",
            [$productId, $nameAttributeId]
        );
    }
    $sku = (string) $connection->fetchOne(
        "SELECT sku FROM {$productEntityTable} WHERE entity_id = ?",
        [$productId]
    );
    $haystack = mb_strtolower($sku . ' ' . $name);

    $hasShoeSize = $shoeSizeAttributeId > 0 && (bool) $connection->fetchOne(
        "SELECT 1 FROM {$intTable} WHERE entity_id = ? AND attribute_id = ? AND value IS NOT NULL LIMIT 1",
        [$productId, $shoeSizeAttributeId]
    );
    $hasClothingSize = $clothingSizeAttributeId > 0 && (bool) $connection->fetchOne(
        "SELECT 1 FROM {$intTable} WHERE entity_id = ? AND attribute_id = ? AND value IS NOT NULL LIMIT 1",
        [$productId, $clothingSizeAttributeId]
    );
    $hasAccessorySize = $accessorySizeAttributeId > 0 && (bool) $connection->fetchOne(
        "SELECT 1 FROM {$intTable} WHERE entity_id = ? AND attribute_id = ? AND value IS NOT NULL LIMIT 1",
        [$productId, $accessorySizeAttributeId]
    );

    if ($hasAccessorySize || preg_match('/\b(ball|bóng|bang|băng|sock|tất|bag|balo|cap|mũ)\b/u', $haystack)) {
        $insert($accessoryCategoryId, $productId, $position);
        continue;
    }

    if ($hasClothingSize || preg_match('/\b(tee|shirt|áo|quan|quần|short|hoodie|jacket)\b/u', $haystack)) {
        $insert($clothingCategoryId, $productId, $position);
        continue;
    }

    if ($hasShoeSize || preg_match('/\b(shoe|shoes|giày|adidas|nike|new balance|sneaker|runner)\b/u', $haystack)) {
        $insert($shoeCategoryId, $productId, $position);
        continue;
    }

    $insert($accessoryCategoryId, $productId, $position);
}

echo 'Synced ' . count(array_unique($saleProductIds)) . " sale products into promotion categories.\n";
