<?php
declare(strict_types=1);

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\Store;

require __DIR__ . '/../app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();

try {
    $objectManager->get(State::class)->setAreaCode('adminhtml');
} catch (\Exception $exception) {
}

$dataFile = $argv[1] ?? BP . '/var/import/dalactive_sports/products.json';
$imageBaseDir = $argv[2] ?? BP . '/pub/media/import';

if (!is_file($dataFile)) {
    throw new RuntimeException("Data file not found: {$dataFile}");
}

$payload = json_decode((string)file_get_contents($dataFile), true, 512, JSON_THROW_ON_ERROR);
$products = $payload['products'] ?? [];
if (!$products) {
    throw new RuntimeException('No products found in data file.');
}

/** @var ModuleDataSetupInterface $setup */
$setup = $objectManager->get(ModuleDataSetupInterface::class);
/** @var EavSetupFactory $eavSetupFactory */
$eavSetupFactory = $objectManager->get(EavSetupFactory::class);
$eavSetup = $eavSetupFactory->create(['setup' => $setup]);
/** @var ResourceConnection $resource */
$resource = $objectManager->get(ResourceConnection::class);

$setup->startSetup();
ensureTextAttribute($eavSetup, 'brand', 'Brand');
ensureTextAttribute($eavSetup, 'sport', 'Sport');
ensureTextAttribute($eavSetup, 'product_type', 'Product Type');
ensureTextAttribute($eavSetup, 'size', 'Size');
ensureTextAttribute($eavSetup, 'age_group', 'Age Group');
ensureColorOptions($eavSetup, $resource, $products);
$setup->endSetup();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var ProductFactory $productFactory */
$productFactory = $objectManager->get(ProductFactory::class);
/** @var CategoryFactory $categoryFactory */
$categoryFactory = $objectManager->get(CategoryFactory::class);
/** @var CategoryRepositoryInterface $categoryRepository */
$categoryRepository = $objectManager->get(CategoryRepositoryInterface::class);
/** @var StockRegistryInterface $stockRegistry */
$stockRegistry = $objectManager->get(StockRegistryInterface::class);
/** @var File $fileIo */
$fileIo = $objectManager->get(File::class);

$attributeSetId = (int)$eavSetup->getDefaultAttributeSetId(Product::ENTITY);
$genderMap = optionMap($eavSetup, $resource, 'gender');
$colorMap = optionMap($eavSetup, $resource, 'color');
$categoryCache = [];
$createdCategories = [];
$createdProducts = 0;
$updatedProducts = 0;

foreach ($products as $row) {
    $categoryId = ensureCategoryPath(
        $row['category_path'],
        $categoryFactory,
        $categoryRepository,
        $resource,
        $categoryCache,
        $createdCategories
    );

    $sportParentId = ensureCategoryPath(
        implode('/', array_slice(explode('/', $row['category_path']), 0, 3)),
        $categoryFactory,
        $categoryRepository,
        $resource,
        $categoryCache,
        $createdCategories
    );

    try {
        $product = $productRepository->get($row['sku'], false, Store::DEFAULT_STORE_ID, true);
        $isNew = false;
    } catch (NoSuchEntityException $exception) {
        $product = $productFactory->create();
        $product->setSku($row['sku']);
        $product->setAttributeSetId($attributeSetId);
        $product->setTypeId(Type::TYPE_SIMPLE);
        $product->setWebsiteIds([1]);
        $isNew = true;
    }

    $product->setStoreId(Store::DEFAULT_STORE_ID);
    $product->setName($row['name']);
    $product->setUrlKey($row['url_key']);
    $product->setPrice((float)$row['price']);
    $product->setSpecialPrice($row['special_price'] ? (float)$row['special_price'] : null);
    $product->setDescription($row['description']);
    $product->setShortDescription($row['short_description']);
    $product->setStatus(Status::STATUS_ENABLED);
    $product->setVisibility(Visibility::VISIBILITY_BOTH);
    $product->setTaxClassId(2);
    $product->setWeight(1);
    $product->setCategoryIds(array_values(array_unique([$categoryId, $sportParentId, 7])));
    $product->setData('brand', $row['brand']);
    $product->setData('sport', $row['sport']);
    if (isset($row['product_type'])) {
        $product->setData('product_type', $row['product_type']);
    }
    $product->setData('size', $row['size']);
    if (isset($row['age_group'])) {
        $product->setData('age_group', $row['age_group']);
    }
    $product->setData('material', $row['material']);
    if (isset($genderMap[$row['gender']])) {
        $product->setData('gender', $genderMap[$row['gender']]);
    }
    if (isset($colorMap[$row['color']])) {
        $product->setData('color', $colorMap[$row['color']]);
    }
    $product->setStockData([
        'use_config_manage_stock' => 0,
        'manage_stock' => 1,
        'is_in_stock' => 1,
        'qty' => (float)$row['qty'],
    ]);

    $imageRoot = str_starts_with($imageBaseDir, '/') ? $imageBaseDir : BP . '/' . $imageBaseDir;
    $imagePath = rtrim($imageRoot, '/') . '/' . ltrim($row['image'], '/');
    $shouldRefreshImage = str_starts_with($row['sku'], 'DAL-REAL-');
    if (is_file($imagePath) && ($shouldRefreshImage || !$product->getImage() || $product->getImage() === 'no_selection')) {
        $product->addImageToMediaGallery($imagePath, ['image', 'small_image', 'thumbnail'], false, false);
    }

    $saved = $productRepository->save($product);
    updateStock($objectManager, $stockRegistry, $saved->getSku(), (float)$row['qty']);
    $isNew ? $createdProducts++ : $updatedProducts++;
}

echo json_encode(
    [
        'created_categories' => array_values($createdCategories),
        'created_product_count' => $createdProducts,
        'updated_product_count' => $updatedProducts,
        'total_products_processed' => count($products),
    ],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
) . PHP_EOL;

function ensureTextAttribute(\Magento\Eav\Setup\EavSetup $eavSetup, string $code, string $label): void
{
    $attributeId = $eavSetup->getAttributeId(Product::ENTITY, $code);
    if ($attributeId) {
        return;
    }

    $eavSetup->addAttribute(Product::ENTITY, $code, [
        'type' => 'varchar',
        'label' => $label,
        'input' => 'text',
        'required' => false,
        'visible' => true,
        'user_defined' => true,
        'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
        'group' => 'General',
        'visible_on_front' => true,
        'used_in_product_listing' => true,
    ]);
}

function ensureColorOptions(\Magento\Eav\Setup\EavSetup $eavSetup, ResourceConnection $resource, array $products): void
{
    $attributeId = (int)$eavSetup->getAttributeId(Product::ENTITY, 'color');
    if (!$attributeId) {
        return;
    }

    $existing = optionMap($eavSetup, $resource, 'color');
    $values = [];
    foreach ($products as $row) {
        if (!isset($existing[$row['color']])) {
            $values[] = $row['color'];
        }
    }
    $values = array_values(array_unique($values));
    if (!$values) {
        return;
    }

    $option = ['attribute_id' => $attributeId, 'values' => $values];
    $eavSetup->addAttributeOption($option);
}

function optionMap(\Magento\Eav\Setup\EavSetup $eavSetup, ResourceConnection $resource, string $attributeCode): array
{
    $attributeId = (int)$eavSetup->getAttributeId(Product::ENTITY, $attributeCode);
    if (!$attributeId) {
        return [];
    }

    $connection = $resource->getConnection();
    $select = $connection->select()
        ->from(['o' => $resource->getTableName('eav_attribute_option')], ['option_id'])
        ->join(
            ['v' => $resource->getTableName('eav_attribute_option_value')],
            'v.option_id = o.option_id AND v.store_id = 0',
            ['value']
        )
        ->where('o.attribute_id = ?', $attributeId)
        ->order('o.sort_order ASC');
    $options = $connection->fetchPairs($select);
    $map = [];
    foreach ($options as $optionId => $label) {
        if ($label !== '') {
            $map[(string)$label] = (int)$optionId;
        }
    }
    return $map;
}

function ensureCategoryPath(
    string $path,
    CategoryFactory $categoryFactory,
    CategoryRepositoryInterface $categoryRepository,
    ResourceConnection $resource,
    array &$categoryCache,
    array &$createdCategories
): int {
    if (isset($categoryCache[$path])) {
        return $categoryCache[$path];
    }

    $parts = explode('/', $path);
    if ($parts[0] !== 'Default Category') {
        throw new RuntimeException("Category path must start with Default Category: {$path}");
    }

    $parent = $categoryRepository->get(2, Store::DEFAULT_STORE_ID);
    $currentPath = 'Default Category';
    $categoryCache[$currentPath] = 2;

    foreach (array_slice($parts, 1) as $part) {
        $currentPath .= '/' . $part;
        if (isset($categoryCache[$currentPath])) {
            $parent = $categoryRepository->get($categoryCache[$currentPath], Store::DEFAULT_STORE_ID);
            continue;
        }

        $existing = null;
        foreach ($parent->getChildrenCategories() as $child) {
            if ((string)$child->getName() === $part) {
                $existing = $categoryRepository->get((int)$child->getId(), Store::DEFAULT_STORE_ID);
                ensureStoredCategoryPath($resource, $existing, $parent);
                break;
            }
        }

        if ($existing) {
            $parent = $existing;
            $categoryCache[$currentPath] = (int)$parent->getId();
            continue;
        }

        $category = $categoryFactory->create();
        $category->setName($part);
        $category->setIsActive(true);
        $category->setIncludeInMenu(true);
        $category->setParentId((int)$parent->getId());
        $category->setPath($parent->getPath());
        $category->setDisplayMode('PRODUCTS');
        $category->setUrlKey(slugify('dal-sports-' . (int)$parent->getId() . '-' . $part));
        $category = $categoryRepository->save($category);
        ensureStoredCategoryPath($resource, $category, $parent);

        $createdCategories[$currentPath] = $currentPath;
        $parent = $category;
        $categoryCache[$currentPath] = (int)$category->getId();
    }

    return (int)$parent->getId();
}

function ensureStoredCategoryPath(ResourceConnection $resource, \Magento\Catalog\Api\Data\CategoryInterface $category, \Magento\Catalog\Api\Data\CategoryInterface $parent): void
{
    $expected = rtrim((string)$parent->getPath(), '/') . '/' . (int)$category->getId();
    if ((string)$category->getPath() === $expected) {
        return;
    }

    $connection = $resource->getConnection();
    $connection->update(
        $resource->getTableName('catalog_category_entity'),
        ['path' => $expected],
        ['entity_id = ?' => (int)$category->getId()]
    );
    $category->setPath($expected);
}

function slugify(string $value): string
{
    $value = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value) ?: strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim(preg_replace('/-+/', '-', $value) ?: '', '-');
}

function updateStock(
    \Magento\Framework\ObjectManagerInterface $objectManager,
    StockRegistryInterface $stockRegistry,
    string $sku,
    float $qty
): void {
    $stockItem = $stockRegistry->getStockItemBySku($sku);
    $stockItem->setQty($qty);
    $stockItem->setIsInStock(true);
    $stockRegistry->updateStockItemBySku($sku, $stockItem);

    if (!interface_exists(SourceItemsSaveInterface::class)) {
        return;
    }

    /** @var SourceItemInterfaceFactory $sourceItemFactory */
    $sourceItemFactory = $objectManager->get(SourceItemInterfaceFactory::class);
    /** @var SourceItemsSaveInterface $sourceItemsSave */
    $sourceItemsSave = $objectManager->get(SourceItemsSaveInterface::class);
    /** @var SourceItemInterface $sourceItem */
    $sourceItem = $sourceItemFactory->create();
    $sourceItem->setSourceCode('default');
    $sourceItem->setSku($sku);
    $sourceItem->setQuantity($qty);
    $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
    $sourceItemsSave->execute([$sourceItem]);
}
