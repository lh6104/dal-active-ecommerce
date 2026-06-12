<?php

namespace Dalactive\HeroBanner\Controller\Adminhtml\Banner;

use Dalactive\HeroBanner\Model\BannerFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Dalactive_HeroBanner::banners';

    private BannerFactory $bannerFactory;

    public function __construct(Context $context, BannerFactory $bannerFactory)
    {
        $this->bannerFactory = $bannerFactory;
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $result = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('slide_id');
        if ($id) {
            $banner = $this->bannerFactory->create();
            $banner->load($id);
            if ($banner->getId()) {
                $banner->delete();
                $this->messageManager->addSuccessMessage(__('Hero banner was deleted.'));
            }
        }

        return $result->setPath('*/*/');
    }
}
