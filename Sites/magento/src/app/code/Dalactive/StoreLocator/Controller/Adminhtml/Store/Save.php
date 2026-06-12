<?php

namespace Dalactive\StoreLocator\Controller\Adminhtml\Store;

use Dalactive\StoreLocator\Model\StoreFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Dalactive_StoreLocator::stores';

    private StoreFactory $storeFactory;

    public function __construct(
        Context $context,
        StoreFactory $storeFactory
    ) {
        $this->storeFactory = $storeFactory;
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $result = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $result->setPath('*/*/');
        }

        try {
            $store = $this->storeFactory->create();
            if (!empty($data['entity_id'])) {
                $store->load((int) $data['entity_id']);
            }

            $identifier = trim((string) ($data['identifier'] ?? ''));
            if ($identifier === '') {
                $identifier = $this->slugify((string) ($data['name'] ?? ''));
            }

            $store->addData([
                'name' => trim((string) ($data['name'] ?? '')),
                'identifier' => $identifier,
                'address' => trim((string) ($data['address'] ?? '')),
                'city' => trim((string) ($data['city'] ?? '')),
                'region' => trim((string) ($data['region'] ?? '')),
                'country' => trim((string) ($data['country'] ?? 'VN')) ?: 'VN',
                'latitude' => $this->nullableDecimal($data['latitude'] ?? null),
                'longitude' => $this->nullableDecimal($data['longitude'] ?? null),
                'google_maps_url' => trim((string) ($data['google_maps_url'] ?? '')),
                'phone' => trim((string) ($data['phone'] ?? '')),
                'email' => trim((string) ($data['email'] ?? '')),
                'opening_hours' => trim((string) ($data['opening_hours'] ?? '')),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
                'is_active' => (int) ($data['is_active'] ?? 0),
            ]);

            if ($store->getData('name') === '' || $store->getData('address') === '') {
                throw new \RuntimeException((string) __('Store name and address are required.'));
            }

            $store->save();
            $this->messageManager->addSuccessMessage(__('Store was saved.'));
            return $result->setPath('*/*/edit', ['entity_id' => $store->getId()]);
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Unable to save store: %1', $exception->getMessage()));
            return $result->setPath('*/*/edit', ['entity_id' => (int) ($data['entity_id'] ?? 0)]);
        }
    }

    private function nullableDecimal($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (float) str_replace(',', '.', (string) $value);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: 'store';
        return trim($value, '-') ?: 'store';
    }
}
