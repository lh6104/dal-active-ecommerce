<?php

namespace Dalactive\HeroBanner\Controller\Adminhtml\Banner;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
{
    public const ADMIN_RESOURCE = 'Dalactive_HeroBanner::banners';

    private PageFactory $pageFactory;

    public function __construct(Context $context, PageFactory $pageFactory)
    {
        $this->pageFactory = $pageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Dalactive_HeroBanner::banners');
        $page->getConfig()->getTitle()->prepend(__('Edit Hero Banner'));
        return $page;
    }
}
