<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Store\Model\StoreManagerInterface;

class ChatbotProductService
{
    private CollectionFactory $collectionFactory;
    private ProductRepositoryInterface $productRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private Visibility $visibility;
    private StockRegistryInterface $stockRegistry;
    private StoreManagerInterface $storeManager;
    private PriceHelper $priceHelper;

    public function __construct(
        CollectionFactory $collectionFactory,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Visibility $visibility,
        StockRegistryInterface $stockRegistry,
        StoreManagerInterface $storeManager,
        PriceHelper $priceHelper
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->visibility = $visibility;
        $this->stockRegistry = $stockRegistry;
        $this->storeManager = $storeManager;
        $this->priceHelper = $priceHelper;
    }

    public function searchProducts(array $filters, int $limit = 4, bool $withFallback = true): array
    {
        $exactPhraseProducts = $this->collectExactPhraseProducts($filters, $limit);
        if ($exactPhraseProducts) {
            return [
                'products' => array_values(array_map([$this, 'normalizeProduct'], $exactPhraseProducts)),
                'fallback' => false,
                'fallback_reason' => null,
            ];
        }

        $products = $this->collectProducts($filters, max($limit, 24));
        $filtered = $this->applyPhpFilters($products, $filters);

        if ($filtered) {
            return [
                'products' => array_slice(array_map([$this, 'normalizeProduct'], $filtered), 0, $limit),
                'fallback' => false,
                'fallback_reason' => null,
            ];
        }

        if (!$withFallback) {
            return ['products' => [], 'fallback' => false, 'fallback_reason' => null];
        }

        $fallbackProducts = $this->fallbackProducts($filters, $limit);
        return [
            'products' => $fallbackProducts,
            'fallback' => (bool)$fallbackProducts,
            'fallback_reason' => $fallbackProducts ? 'near_match' : null,
        ];
    }

    public function getNewestProducts(int $limit = 4): array
    {
        $collection = $this->baseCollection($limit);
        $collection->setOrder('created_at', 'DESC');

        return array_values(array_map([$this, 'normalizeProduct'], iterator_to_array($collection)));
    }

    public function getPromotionProducts(int $limit = 4): array
    {
        $collection = $this->baseCollection(max(30, $limit * 5));
        $collection->setOrder('created_at', 'DESC');

        $products = [];
        foreach ($collection as $product) {
            $normalized = $this->normalizeProduct($product);
            if ($normalized['regularPrice'] > 0 && $normalized['price'] < $normalized['regularPrice']) {
                $products[] = $normalized;
            }
            if (count($products) >= $limit) {
                break;
            }
        }

        return $products;
    }

    public function getSimilarProducts(string $productIdOrSku, int $limit = 4): array
    {
        $product = $this->loadProduct($productIdOrSku);
        if (!$product) {
            return [];
        }

        $categoryIds = $product->getCategoryIds();
        $collection = $this->baseCollection(max($limit * 3, 12));
        if ($categoryIds) {
            $collection->addCategoriesFilter(['in' => $categoryIds]);
        }
        $collection->addAttributeToFilter('entity_id', ['neq' => (int)$product->getId()]);

        return array_slice(array_values(array_map([$this, 'normalizeProduct'], iterator_to_array($collection))), 0, $limit);
    }

    public function getStockInfo(string $sku): ?array
    {
        $product = $this->loadProduct($sku);
        if (!$product) {
            return null;
        }

        $normalized = $this->normalizeProduct($product);
        return [
            'sku' => $normalized['sku'],
            'name' => $normalized['name'],
            'stockStatus' => $normalized['stockStatus'],
            'qty' => $normalized['qty'],
            'product' => $normalized,
        ];
    }

    public function normalizeProduct($product): array
    {
        $regularPrice = (float)$product->getPrice();
        $finalPrice = (float)$product->getFinalPrice();
        try {
            $regularPrice = (float)$product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
            $finalPrice = (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        } catch (\Throwable $e) {
            // Keep attribute prices when price info is unavailable in CLI or incomplete products.
        }
        if ($finalPrice <= 0 && $regularPrice > 0) {
            $finalPrice = $regularPrice;
        }
        if ($regularPrice <= 0 && $finalPrice > 0) {
            $regularPrice = $finalPrice;
        }

        $stockItem = $this->stockRegistry->getStockItem((int)$product->getId());
        $imageUrl = $this->getImageUrl($product);
        $categoryNames = $this->getCategoryNames($product);
        $brand = $this->safeAttributeText($product, 'brand') ?: $this->safeAttributeText($product, 'manufacturer');

        return [
            'id' => (int)$product->getId(),
            'product_id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'name' => (string)$product->getName(),
            'price' => $finalPrice,
            'regularPrice' => $regularPrice,
            'specialPrice' => $finalPrice < $regularPrice ? $finalPrice : null,
            'final_price' => $finalPrice,
            'price_text' => $this->priceHelper->currency($finalPrice, true, false),
            'regular_price_text' => $regularPrice > $finalPrice ? $this->priceHelper->currency($regularPrice, true, false) : '',
            'imageUrl' => $imageUrl,
            'image' => $imageUrl,
            'productUrl' => $product->getProductUrl(),
            'url' => $product->getProductUrl(),
            'stockStatus' => $stockItem->getIsInStock() ? 'in_stock' : 'out_of_stock',
            'stock_status' => $stockItem->getIsInStock() ? 'in_stock' : 'out_of_stock',
            'qty' => (float)$stockItem->getQty(),
            'salable_quantity' => (float)$stockItem->getQty(),
            'categoryNames' => $categoryNames,
            'category_names' => $categoryNames,
            'brand' => $brand,
            'color' => $this->safeAttributeText($product, 'color'),
            'gender' => $this->safeAttributeText($product, 'gender'),
            'sizes' => array_values(array_filter([
                $this->safeAttributeText($product, 'shoe_size_eu'),
                $this->safeAttributeText($product, 'kids_shoe_size_eu'),
                $this->safeAttributeText($product, 'clothing_size'),
                $this->safeAttributeText($product, 'kids_clothing_size'),
                $this->safeAttributeText($product, 'accessory_size'),
            ])),
        ];
    }

    private function collectProducts(array $filters, int $limit): array
    {
        $collection = $this->baseCollection(max(30, $limit));
        $query = trim((string)($filters['product_name'] ?: $filters['query'] ?: ''));
        if ($query !== '') {
            $query = $this->normalizeQuery($query);
            $conditions = [];
            foreach (array_slice($this->queryTokens($query), 0, 6) as $token) {
                $conditions[] = ['attribute' => 'name', 'like' => '%' . $token . '%'];
                $conditions[] = ['attribute' => 'sku', 'like' => '%' . $token . '%'];
                $conditions[] = ['attribute' => 'url_key', 'like' => '%' . $token . '%'];
            }
            if ($conditions) {
                $collection->addAttributeToFilter($conditions);
            }
        }

        $brand = trim((string)($filters['brand'] ?? ''));
        if ($brand !== '') {
            $collection->addAttributeToFilter('name', ['like' => '%' . $brand . '%']);
        }

        $collection->setOrder('created_at', 'DESC');
        return iterator_to_array($collection);
    }

    private function collectExactPhraseProducts(array $filters, int $limit): array
    {
        $phrase = trim((string)($filters['product_name'] ?: ''));
        if ($phrase === '') {
            return [];
        }

        $phrase = $this->normalizeQuery($phrase);
        if (mb_strlen($phrase) < 3) {
            return [];
        }

        $collection = $this->baseCollection(max($limit, 5));
        $collection->addAttributeToFilter([
            ['attribute' => 'name', 'like' => '%' . $phrase . '%'],
            ['attribute' => 'sku', 'like' => '%' . $phrase . '%'],
            ['attribute' => 'url_key', 'like' => '%' . str_replace(' ', '-', mb_strtolower($phrase)) . '%'],
        ]);
        $collection->setOrder('created_at', 'DESC');

        $products = iterator_to_array($collection);
        return array_slice(array_values($products), 0, $limit);
    }

    private function fallbackProducts(array $filters, int $limit): array
    {
        $fallbackFilters = $filters;
        if (!empty($fallbackFilters['max_price'])) {
            $fallbackFilters['max_price'] = min((float)$fallbackFilters['max_price'] + 100000, (float)$fallbackFilters['max_price'] * 1.25);
        }
        $fallbackFilters['query'] = $fallbackFilters['category'] ?? $fallbackFilters['query'] ?? '';
        $fallbackFilters['product_name'] = null;

        $products = $this->applyPhpFilters($this->collectProducts($fallbackFilters, 40), $fallbackFilters);
        if (!$products && !empty($filters['category'])) {
            $products = $this->applyPhpFilters(iterator_to_array($this->baseCollection(60)), [
                'category' => $filters['category'],
                'min_price' => null,
                'max_price' => null,
            ]);
            usort($products, static function ($a, $b) {
                return (float)$a->getFinalPrice() <=> (float)$b->getFinalPrice();
            });
        }

        if (!$products) {
            $promotions = $this->getPromotionProducts($limit);
            return $promotions ?: $this->getNewestProducts($limit);
        }

        return array_slice(array_map([$this, 'normalizeProduct'], $products), 0, $limit);
    }

    private function applyPhpFilters(array $products, array $filters): array
    {
        $category = $filters['category'] ?? null;
        $brand = $filters['brand'] ?? null;
        $color = $filters['color'] ?? null;
        $gender = $filters['gender'] ?? null;
        $size = $filters['size'] ?? null;
        $minPrice = $filters['min_price'] ?? null;
        $maxPrice = $filters['max_price'] ?? null;

        return array_values(array_filter($products, function ($product) use ($category, $brand, $color, $gender, $size, $minPrice, $maxPrice) {
            $normalized = $this->normalizeProduct($product);
            if ($minPrice !== null && $normalized['price'] < (float)$minPrice) {
                return false;
            }
            if ($maxPrice !== null && $normalized['price'] > (float)$maxPrice) {
                return false;
            }
            if ($brand && stripos($normalized['name'] . ' ' . $normalized['brand'], (string)$brand) === false) {
                return false;
            }
            if ($color && stripos($normalized['color'], (string)$color) === false) {
                return false;
            }
            if ($gender && stripos($normalized['gender'], (string)$gender) === false) {
                return false;
            }
            if ($size && !preg_grep('/' . preg_quote((string)$size, '/') . '/i', $normalized['sizes'])) {
                return false;
            }
            if ($category && !$this->matchesCategory($normalized, (string)$category)) {
                return false;
            }
            return true;
        }));
    }

    private function matchesCategory(array $product, string $category): bool
    {
        $haystack = $this->removeAccents(implode(' ', array_merge(
            [$product['name'], $product['brand']],
            $product['categoryNames']
        )));

        $needles = [
            'shoes' => ['giay', 'shoe', 'sneaker'],
            'shirts' => ['ao', 'shirt', 'tee', 'hoodie'],
            'pants' => ['quan', 'short', 'pants'],
            'accessories' => ['phu kien', 'tat', 'vo', 'balo', 'mu', 'tui'],
            'football' => ['bong da', 'football'],
            'basketball' => ['bong ro', 'basketball'],
            'running' => ['chay', 'running', 'runner'],
            'gym' => ['gym', 'training', 'tap luyen'],
        ];

        foreach ($needles[$category] ?? [] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function baseCollection(int $limit): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect([
            'name',
            'sku',
            'url_key',
            'price',
            'special_price',
            'image',
            'small_image',
            'thumbnail',
            'brand',
            'manufacturer',
            'color',
            'gender',
            'shoe_size_eu',
            'kids_shoe_size_eu',
            'clothing_size',
            'kids_clothing_size',
            'accessory_size',
            'sport_type',
            'created_at',
        ]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->visibility->getVisibleInSiteIds());
        $collection->addFinalPrice();
        $collection->setPageSize($limit);

        $collection->joinField(
            'is_in_stock',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );
        $collection->addFieldToFilter('is_in_stock', 1);

        return $collection;
    }

    private function loadProduct(string $productIdOrSku): ?Product
    {
        $value = trim($productIdOrSku);
        if ($value === '') {
            return null;
        }

        try {
            if (ctype_digit($value)) {
                return $this->productRepository->getById((int)$value);
            }
            return $this->productRepository->get($value);
        } catch (NoSuchEntityException $e) {
            $collection = $this->baseCollection(1);
            $collection->addAttributeToFilter('url_key', ['eq' => preg_replace('/\.html$/i', '', $value)]);
            $item = $collection->getFirstItem();
            return $item && $item->getId() ? $item : null;
        }
    }

    private function getImageUrl($product): string
    {
        $image = (string)($product->getSmallImage() ?: $product->getImage() ?: $product->getThumbnail());
        if ($image && $image !== 'no_selection') {
            return rtrim($this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/')
                . '/catalog/product' . $image;
        }
        return '';
    }

    private function getCategoryNames($product): array
    {
        $names = [];
        try {
            $categories = $product->getCategoryCollection()->addAttributeToSelect('name');
            foreach ($categories as $category) {
                $name = trim((string)$category->getName());
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return array_values(array_unique($names));
    }

    private function safeAttributeText($product, string $code): string
    {
        try {
            $value = $product->getAttributeText($code);
        } catch (\Throwable $e) {
            return '';
        }
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $value ? (string)$value : '';
    }

    private function normalizeQuery(string $query): string
    {
        if (preg_match('#https?://[^\s]+#i', $query, $match)) {
            $path = (string)(parse_url($match[0], PHP_URL_PATH) ?: '');
            $query = basename($path, '.html');
        }
        $query = preg_replace('/\b(kiem tra|tồn hàng|ton hang|tồn kho|ton kho|còn hàng|con hang|check|stock|online)\b/iu', ' ', $query);
        $query = str_replace(['-', '_', '.html'], ' ', (string)$query);
        return trim(preg_replace('/\s+/u', ' ', (string)$query));
    }

    private function queryTokens(string $query): array
    {
        $tokens = array_filter(preg_split('/\s+/u', $query) ?: [], static function ($token) {
            return mb_strlen($token) >= 2;
        });
        return $tokens ? array_values(array_unique($tokens)) : [$query];
    }

    private function removeAccents(string $value): string
    {
        $map = [
            'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
            'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
            'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
            'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
            'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
            'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
        ];
        return strtr(mb_strtolower($value), $map);
    }
}
