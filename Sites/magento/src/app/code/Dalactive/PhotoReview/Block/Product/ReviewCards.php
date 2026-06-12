<?php

namespace Dalactive\PhotoReview\Block\Product;

use Dalactive\PhotoReview\Model\ResourceModel\PhotoReview\CollectionFactory as PhotoReviewCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Review\Block\Product\View\ListView;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ReviewCards extends ListView
{
    private ?array $reviewImages = null;

    public function isPhotoReviewEnabled(): bool
    {
        return ObjectManager::getInstance()
            ->get(ScopeConfigInterface::class)
            ->isSetFlag('dalactive_photoreview/general/enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getMaxImages(): int
    {
        $value = ObjectManager::getInstance()
            ->get(ScopeConfigInterface::class)
            ->getValue('dalactive_photoreview/general/max_images', ScopeInterface::SCOPE_STORE);

        return max(1, (int) ($value ?: 3));
    }

    public function getMaxImageSizeMb(): int
    {
        $value = ObjectManager::getInstance()
            ->get(ScopeConfigInterface::class)
            ->getValue('dalactive_photoreview/general/max_image_size', ScopeInterface::SCOPE_STORE);

        return max(1, (int) ceil(((int) ($value ?: 2048)) / 1024));
    }

    public function getImagesForReview(int $reviewId): array
    {
        if ($this->reviewImages === null) {
            $this->reviewImages = [];

            if (!$this->isPhotoReviewEnabled() || !$this->getProductId()) {
                return [];
            }

            $collection = ObjectManager::getInstance()
                ->get(PhotoReviewCollectionFactory::class)
                ->create();
            $collection->addFieldToFilter('product_id', (int) $this->getProductId());
            $collection->addFieldToFilter('status', 2);
            $collection->addFieldToFilter('review_id', ['notnull' => true]);
            $collection->setOrder('created_at', 'DESC');

            foreach ($collection as $image) {
                $imageReviewId = (int) $image->getData('review_id');
                if (!$imageReviewId) {
                    continue;
                }

                $this->reviewImages[$imageReviewId][] = $this->getMediaUrl((string) $image->getData('image_path'));
            }
        }

        return $this->reviewImages[$reviewId] ?? [];
    }

    private function getMediaUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return rtrim(ObjectManager::getInstance()
            ->get(StoreManagerInterface::class)
            ->getStore()
            ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/') . '/' . $path;
    }
}
