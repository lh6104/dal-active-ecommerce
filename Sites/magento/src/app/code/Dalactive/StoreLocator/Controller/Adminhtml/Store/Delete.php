<?php

namespace Dalactive\StoreLocator\Controller\Adminhtml\Store;

use Dalactive\StoreLocator\Model\StoreFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action
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
        $entityId = (int) $this->getRequest()->getParam('entity_id');

        if ($entityId <= 0) {
            $this->messageManager->addErrorMessage(__('Missing store ID.'));
            return $result->setPath('*/*/');
        }

        try {
            $store = $this->storeFactory->create();
            $store->load($entityId);
            if (!$store->getId()) {
                throw new \RuntimeException((string) __('Store no longer exists.'));
            }
            $store->delete();
            $this->messageManager->addSuccessMessage(__('Store was deleted.'));
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Unable to delete store: %1', $exception->getMessage()));
        }

        return $result->setPath('*/*/');
    }
}
