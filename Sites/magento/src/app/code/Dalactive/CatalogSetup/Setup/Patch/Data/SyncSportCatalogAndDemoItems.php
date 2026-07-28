<?php

namespace Dalactive\CatalogSetup\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\App\State;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

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
    private AttributeRepositoryInterface $attributeRepository;
    private MetadataPool $metadataPool;
    private State $appState;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryRepositoryInterface $categoryRepository,
        ProductCollectionFactory $productCollectionFactory,
        AttributeRepositoryInterface $attributeRepository,
        MetadataPool $metadataPool,
        State $appState
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->attributeRepository = $attributeRepository;
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
    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
