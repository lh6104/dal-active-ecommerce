<?php

namespace Dalactive\ShoppingAssistant\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Store\Model\StoreManagerInterface;

class ProductRecommender
{
    private CollectionFactory $collectionFactory;
    private Visibility $visibility;
    private StockRegistryInterface $stockRegistry;
    private StoreManagerInterface $storeManager;
    private PriceHelper $priceHelper;
    private Config $config;

    public function __construct(
        CollectionFactory $collectionFactory,
        Visibility $visibility,
        StockRegistryInterface $stockRegistry,
        StoreManagerInterface $storeManager,
        PriceHelper $priceHelper,
        Config $config
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->visibility = $visibility;
        $this->stockRegistry = $stockRegistry;
        $this->storeManager = $storeManager;
        $this->priceHelper = $priceHelper;
        $this->config = $config;
    }

    public function getLatestProducts(?int $limit = null): array
    {
        $collection = $this->baseCollection($limit);
        $collection->setOrder('created_at', 'DESC');
        return $this->formatProducts($collection);
    }

    public function recommend(string $message, ?int $limit = null): array
    {
        $collection = $this->baseCollection($limit);
        $budget = $this->extractBudget($message);
        if ($budget) {
            $collection->addFinalPrice()->getSelect()->where('price_index.final_price <= ?', $budget);
        }

        $keyword = $this->extractKeyword($message);
        if ($keyword) {
            $collection->addAttributeToFilter('name', ['like' => '%' . $keyword . '%']);
        }

        $collection->setOrder('created_at', 'DESC');
        return $this->formatProducts($collection);
    }

    public function searchByMessage(string $message, ?int $limit = null): array
    {
        $exactKey = $this->extractExactProductKey($message);
        if ($exactKey !== '') {
            $collection = $this->baseCollection(1);
            $collection->addAttributeToFilter([
                ['attribute' => 'sku', 'eq' => $exactKey],
                ['attribute' => 'url_key', 'eq' => $exactKey],
            ]);
            $products = $this->formatProducts($collection);
            if ($products) {
                return $products;
            }
        }

        $query = $this->normalizeSearchQuery($message);
        if ($query === '') {
            return [];
        }

        $collection = $this->baseCollection($limit ?: 5);
        $collection->addAttributeToFilter([
            ['attribute' => 'name', 'like' => '%' . $query . '%'],
            ['attribute' => 'sku', 'like' => '%' . $query . '%'],
            ['attribute' => 'url_key', 'like' => '%' . $query . '%'],
        ]);
        $collection->setOrder('created_at', 'DESC');
        $products = $this->formatProducts($collection);

        if ($products) {
            return $products;
        }

        $tokens = array_values(array_filter(array_unique(preg_split('/\s+/u', $query) ?: []), static function ($token) {
            return mb_strlen($token) >= 3 && !is_numeric($token);
        }));

        if (!$tokens) {
            return [];
        }

        $collection = $this->baseCollection($limit ?: 5);
        $conditions = [];
        foreach (array_slice($tokens, 0, 5) as $token) {
            $conditions[] = ['attribute' => 'name', 'like' => '%' . $token . '%'];
            $conditions[] = ['attribute' => 'sku', 'like' => '%' . $token . '%'];
            $conditions[] = ['attribute' => 'url_key', 'like' => '%' . $token . '%'];
        }
        $collection->addAttributeToFilter($conditions);
        $collection->setOrder('created_at', 'DESC');

        return $this->formatProducts($collection);
    }

    private function baseCollection(?int $limit)
    {
        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name', 'price', 'special_price', 'small_image', 'thumbnail', 'color', 'shoe_size_eu', 'clothing_size'])
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->setVisibility($this->visibility->getVisibleInSiteIds())
            ->setPageSize($limit ?: $this->config->getProductLimit());

        if (!$this->config->includeOutOfStock()) {
            $collection->joinField(
                'is_in_stock',
                'cataloginventory_stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.is_in_stock=1',
                'left'
            );
            $collection->addFieldToFilter('is_in_stock', 1);
        }

        return $collection;
    }

    private function formatProducts($collection): array
    {
        $products = [];
        foreach ($collection as $product) {
            $stockItem = $this->stockRegistry->getStockItem((int)$product->getId());
            $products[] = [
                'product_id' => (int)$product->getId(),
                'sku' => (string)$product->getSku(),
                'name' => (string)$product->getName(),
                'price' => (float)$product->getPrice(),
                'final_price' => (float)$product->getFinalPrice(),
                'price_text' => $this->priceHelper->currency($product->getFinalPrice(), true, false),
                'image' => $this->getImageUrl($product),
                'url' => $product->getProductUrl(),
                'sizes' => array_values(array_filter([
                    $this->safeAttributeText($product, 'shoe_size_eu'),
                    $this->safeAttributeText($product, 'clothing_size'),
                ])),
                'colors' => array_values(array_filter([$this->safeAttributeText($product, 'color')])),
                'stock_status' => $stockItem->getIsInStock() ? 'in_stock' : 'out_of_stock',
                'salable_quantity' => (float)$stockItem->getQty(),
            ];
        }
        return $products;
    }

    private function getImageUrl($product): string
    {
        $image = (string)($product->getSmallImage() ?: $product->getThumbnail());
        if ($image && $image !== 'no_selection') {
            return rtrim($this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/')
                . '/catalog/product' . $image;
        }
        return '';
    }

    private function safeAttributeText($product, string $code): string
    {
        $value = $product->getAttributeText($code);
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return $value ? (string)$value : '';
    }

    private function extractBudget(string $message): ?float
    {
        if (preg_match('/(?:dưới|duoi|<=|ít hơn|nho hơn)\\s*(\\d+(?:[\\.,]\\d+)?)\\s*(k|nghìn|tr|triệu)?/iu', $message, $match)) {
            $amount = (float)str_replace(',', '.', $match[1]);
            $unit = mb_strtolower($match[2] ?? '');
            if (in_array($unit, ['k', 'nghìn'], true)) {
                return $amount * 1000;
            }
            if (in_array($unit, ['tr', 'triệu'], true)) {
                return $amount * 1000000;
            }
            return $amount;
        }
        return null;
    }

    private function extractKeyword(string $message): ?string
    {
        $map = [
            'áo' => 'áo',
            'giày' => 'giày',
            'quần' => 'quần',
            'bóng đá' => 'bóng đá',
            'chạy' => 'chạy',
            'gym' => 'gym',
        ];
        $lower = mb_strtolower($message);
        foreach ($map as $needle => $keyword) {
            if (mb_strpos($lower, $needle) !== false) {
                return $keyword;
            }
        }
        return null;
    }

    private function normalizeSearchQuery(string $message): string
    {
        $message = trim($message);
        if (preg_match('#https?://[^\\s]+#i', $message, $match)) {
            $path = (string)(parse_url($match[0], PHP_URL_PATH) ?: '');
            $message = basename($path, '.html');
        }

        $message = preg_replace('/\\b(kiểm tra|kiem tra|check|stock|sku|tồn hàng|ton hang|tồn kho|ton kho|còn hàng|con hang|còn không|con khong|còn ko|size|màu|mau|online)\\b/iu', ' ', $message);
        $message = preg_replace('/\\b(EU)?\\s*\\d{2,3}\\b/iu', ' ', (string)$message);
        $message = str_replace(['-', '_', '.html'], ' ', (string)$message);
        $message = trim(preg_replace('/\\s+/u', ' ', (string)$message));

        return $message;
    }

    private function extractExactProductKey(string $message): string
    {
        $message = trim($message);
        if (preg_match('#https?://[^\\s]+#i', $message, $match)) {
            $path = (string)(parse_url($match[0], PHP_URL_PATH) ?: '');
            return trim((string)preg_replace('/\\.html$/i', '', basename($path)));
        }

        if (preg_match('/\\bsku\\s+([a-z0-9][a-z0-9\\-_\\.]+)/iu', $message, $match)) {
            return trim((string)$match[1]);
        }

        return '';
    }
}
