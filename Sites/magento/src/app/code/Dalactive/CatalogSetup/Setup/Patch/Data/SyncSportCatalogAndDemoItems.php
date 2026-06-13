<?php

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory as AttributeSetCollectionFactory;
use Magento\Framework\App\State;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;

class SyncSportCatalogAndDemoItems implements DataPatchInterface
{
    private const SPORT_PARENT = 'Môn thể thao';
    private const SPORT_FOOTBALL = 'Bóng đá';
    private const SPORT_BASKETBALL = 'Bóng rổ';
    private const SPORT_RUNNING = 'Chạy bộ';
    private const SPORT_TRAINING = 'Tập luyện & Gym';
    private const SPORT_TENNIS = 'Tennis';
    private const SPORT_YOGA = 'Yoga';

    private ModuleDataSetupInterface $moduleDataSetup;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private CategoryRepositoryInterface $categoryRepository;
    private ProductCollectionFactory $productCollectionFactory;
    private ProductFactory $productFactory;
    private ProductRepositoryInterface $productRepository;
    private AttributeRepositoryInterface $attributeRepository;
    private AttributeSetCollectionFactory $attributeSetCollectionFactory;
    private StockRegistryInterface $stockRegistry;
    private StoreManagerInterface $storeManager;
    private DirectoryList $directoryList;
    private MetadataPool $metadataPool;
    private State $appState;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryRepositoryInterface $categoryRepository,
        ProductCollectionFactory $productCollectionFactory,
        ProductFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        AttributeRepositoryInterface $attributeRepository,
        AttributeSetCollectionFactory $attributeSetCollectionFactory,
        StockRegistryInterface $stockRegistry,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        MetadataPool $metadataPool,
        State $appState
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->attributeRepository = $attributeRepository;
        $this->attributeSetCollectionFactory = $attributeSetCollectionFactory;
        $this->stockRegistry = $stockRegistry;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->metadataPool = $metadataPool;
        $this->appState = $appState;
    }

    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();
        try {
            $this->appState->setAreaCode('adminhtml');
        } catch (\Exception $exception) {
            // Area code can already be set when setup is run from another context.
        }

        $categories = $this->getSportCategories();
        if (!isset($categories[self::SPORT_PARENT])) {
            $connection->endSetup();
            return;
        }

        $this->hideYogaCategory($categories);
        $this->syncExistingProductSportCategories($categories);
        $this->createDemoSportProducts($categories);

        $connection->endSetup();
    }

    private function getSportCategories(): array
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'is_active', 'include_in_menu'])
            ->addAttributeToFilter('name', [
                'in' => [
                    self::SPORT_PARENT,
                    self::SPORT_FOOTBALL,
                    self::SPORT_BASKETBALL,
                    self::SPORT_RUNNING,
                    self::SPORT_TRAINING,
                    self::SPORT_TENNIS,
                    self::SPORT_YOGA,
                ],
            ]);

        $categories = [];
        foreach ($collection as $category) {
            $categories[(string)$category->getName()] = (int)$category->getId();
        }

        return $categories;
    }

    private function hideYogaCategory(array $categories): void
    {
        if (!isset($categories[self::SPORT_YOGA])) {
            return;
        }

        $category = $this->categoryRepository->get($categories[self::SPORT_YOGA]);
        $category->setIsActive(false);
        $category->setIncludeInMenu(false);
        $this->categoryRepository->save($category);
    }

    private function syncExistingProductSportCategories(array $categories): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $categoryProductTable = $this->moduleDataSetup->getTable('catalog_category_product');

        $sportCategoryIds = array_values(array_intersect_key($categories, array_flip([
            self::SPORT_PARENT,
            self::SPORT_FOOTBALL,
            self::SPORT_BASKETBALL,
            self::SPORT_RUNNING,
            self::SPORT_TRAINING,
            self::SPORT_TENNIS,
            self::SPORT_YOGA,
        ])));

        $connection->delete($categoryProductTable, ['category_id IN (?)' => $sportCategoryIds]);

        $sportOptionIds = $this->getSportOptionIds();
        $categoryRows = [];
        $sportAttributeRows = [];
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();
        $sportAttribute = $this->attributeRepository->get(Product::ENTITY, 'sport_type');
        $productIntTable = $this->moduleDataSetup->getTable('catalog_product_entity_int');

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'sku']);

        foreach ($collection as $product) {
            $sports = $this->detectSports((string)$product->getName(), (string)$product->getSku());
            if (!$sports) {
                continue;
            }

            $productId = (int)$product->getId();
            $categoryRows[] = [
                'category_id' => $categories[self::SPORT_PARENT],
                'product_id' => $productId,
                'position' => 0,
            ];

            foreach ($sports as $sportName) {
                if (!isset($categories[$sportName])) {
                    continue;
                }
                $categoryRows[] = [
                    'category_id' => $categories[$sportName],
                    'product_id' => $productId,
                    'position' => 0,
                ];
            }

            $primarySport = $sports[0];
            if (isset($sportOptionIds[$primarySport])) {
                $sportAttributeRows[] = [
                    'attribute_id' => (int)$sportAttribute->getAttributeId(),
                    'store_id' => 0,
                    $linkField => (int)($product->getData($linkField) ?: $productId),
                    'value' => (int)$sportOptionIds[$primarySport],
                ];
            }
        }

        if ($categoryRows) {
            $connection->insertOnDuplicate($categoryProductTable, $categoryRows, ['position']);
        }

        if ($sportAttributeRows) {
            $connection->insertOnDuplicate($productIntTable, $sportAttributeRows, ['value']);
        }
    }

    private function detectSports(string $name, string $sku): array
    {
        $text = strtolower($name . ' ' . $sku);
        $sports = [];

        $rules = [
            self::SPORT_FOOTBALL => [
                'football',
                'soccer',
                'bong da',
                'bóng đá',
                'samba',
                'gazelle',
                'superstar',
                'predator',
                'copa',
                'mercurial',
                'phantom',
                'blazer',
            ],
            self::SPORT_BASKETBALL => [
                'basketball',
                'bong ro',
                'bóng rổ',
                'lebron',
                'jordan',
                'dunk',
                'dame',
                'harden',
                'd rose',
                'rose',
                '550',
            ],
            self::SPORT_RUNNING => [
                'running',
                'runner',
                'run',
                'chay bo',
                'chạy bộ',
                'adizero',
                'alphaboost',
                'alphabounce',
                'ultraboost',
                'air max',
                'vapormax',
                'vomero',
                '530',
                '574',
            ],
            self::SPORT_TRAINING => [
                'training',
                'gym',
                'tap luyen',
                'tập luyện',
                'backpack',
                'balo',
                'bag',
                'bottle',
                'wrist',
                'band',
                'băng tay',
                'sock',
                'tất',
                'hat',
                'cap',
            ],
            self::SPORT_TENNIS => [
                'tennis',
                'stan smith',
                'campus',
                'lacoste',
            ],
        ];

        foreach ($rules as $sport => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $sports[] = $sport;
                    break;
                }
            }
        }

        return array_values(array_unique($sports));
    }

    private function getSportOptionIds(): array
    {
        $attribute = $this->attributeRepository->get(Product::ENTITY, 'sport_type');
        $optionIds = [];
        foreach ($attribute->getOptions() as $option) {
            $label = (string)$option->getLabel();
            if ($label !== '') {
                $optionIds[$label] = (int)$option->getValue();
            }
        }

        return $optionIds;
    }

    private function createDemoSportProducts(array $categories): void
    {
        $accessorySetId = $this->getAttributeSetId('DAL Accessories') ?: $this->getAttributeSetId('Default');
        $websiteId = (int)$this->storeManager->getDefaultStoreView()->getWebsiteId();
        $sportOptionIds = $this->getSportOptionIds();

        $demoProducts = [
            [
                'sku' => 'DAL-FOOTBALL-BALL-001',
                'name' => 'Bóng đá DAL Active Pro',
                'price' => 350000,
                'qty' => 50,
                'sport' => self::SPORT_FOOTBALL,
                'color' => [0, 108, 255],
                'label' => 'FOOTBALL',
            ],
            [
                'sku' => 'DAL-BASKETBALL-BALL-001',
                'name' => 'Bóng rổ DAL Active Street',
                'price' => 420000,
                'qty' => 40,
                'sport' => self::SPORT_BASKETBALL,
                'color' => [236, 119, 35],
                'label' => 'BASKETBALL',
            ],
            [
                'sku' => 'DAL-WRISTBAND-TRAINING-001',
                'name' => 'Băng tay thể thao DAL Active',
                'price' => 99000,
                'qty' => 100,
                'sport' => self::SPORT_TRAINING,
                'color' => [10, 27, 58],
                'label' => 'WRISTBAND',
            ],
            [
                'sku' => 'DAL-FOOTBALL-SOCKS-001',
                'name' => 'Tất bóng đá DAL Active Grip',
                'price' => 129000,
                'qty' => 80,
                'sport' => self::SPORT_FOOTBALL,
                'color' => [18, 148, 83],
                'label' => 'SOCKS',
            ],
        ];

        foreach ($demoProducts as $data) {
            if ($this->productExists($data['sku'])) {
                continue;
            }

            $imagePath = $this->createDemoImage($data['sku'], $data['color'], $data['label']);
            $categoryIds = array_filter([
                $categories[self::SPORT_PARENT] ?? null,
                $categories[$data['sport']] ?? null,
            ]);

            $product = $this->productFactory->create();
            $product->setSku($data['sku']);
            $product->setName($data['name']);
            $product->setAttributeSetId($accessorySetId);
            $product->setTypeId(Product\Type::TYPE_SIMPLE);
            $product->setWebsiteIds([$websiteId]);
            $product->setStatus(Status::STATUS_ENABLED);
            $product->setVisibility(Visibility::VISIBILITY_BOTH);
            $product->setPrice($data['price']);
            $product->setTaxClassId(0);
            $product->setCategoryIds($categoryIds);
            $product->setUrlKey($this->buildUrlKey($data['name']));
            $product->setData('sport_type', $sportOptionIds[$data['sport']] ?? null);
            $product->setData('accessory_size', null);
            $product->setStockData([
                'use_config_manage_stock' => 1,
                'qty' => $data['qty'],
                'is_qty_decimal' => 0,
                'is_in_stock' => 1,
            ]);

            if ($imagePath) {
                $product->addImageToMediaGallery($imagePath, ['image', 'small_image', 'thumbnail'], false, false);
            }

            $savedProduct = $this->productRepository->save($product);
            $stockItem = $this->stockRegistry->getStockItemBySku($savedProduct->getSku());
            $stockItem->setQty($data['qty']);
            $stockItem->setIsInStock(true);
            $this->stockRegistry->updateStockItemBySku($savedProduct->getSku(), $stockItem);
        }
    }

    private function productExists(string $sku): bool
    {
        try {
            $this->productRepository->get($sku);
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    private function getAttributeSetId(string $attributeSetName): ?int
    {
        $collection = $this->attributeSetCollectionFactory->create();
        $collection->addFieldToFilter('attribute_set_name', $attributeSetName);
        $set = $collection->getFirstItem();

        return $set && $set->getId() ? (int)$set->getId() : null;
    }

    private function createDemoImage(string $sku, array $rgb, string $label): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $dir = BP . '/pub/media/import/dalactive-demo-products';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir . '/' . strtolower($sku) . '.png';
        if (is_file($path)) {
            return $path;
        }

        $image = imagecreatetruecolor(900, 900);
        $background = imagecolorallocate($image, 246, 248, 251);
        $primary = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);
        $navy = imagecolorallocate($image, 7, 27, 58);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, 900, 900, $background);
        imagefilledellipse($image, 450, 390, 430, 430, $primary);
        imagefilledellipse($image, 450, 390, 330, 330, $white);
        imagefilledellipse($image, 450, 390, 250, 250, $primary);
        imagestring($image, 5, 360, 620, 'DAL ACTIVE', $navy);
        imagestring($image, 4, max(24, 450 - (strlen($label) * 5)), 660, $label, $navy);
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function buildUrlKey(string $name): string
    {
        $map = [
            'đ' => 'd',
            'Đ' => 'd',
        ];
        $value = strtr($name, $map);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $name;
        $value = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
        $value = trim($value, '-');

        return $value ?: uniqid('dal-product-', false);
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
