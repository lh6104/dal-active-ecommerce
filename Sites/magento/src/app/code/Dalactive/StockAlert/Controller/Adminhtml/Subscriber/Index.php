<?php

namespace Dalactive\StockAlert\Controller\Adminhtml\Subscriber;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Dalactive_StockAlert::subscribers';

    private PageFactory $pageFactory;

    public function __construct(Context $context, PageFactory $pageFactory)
    {
        $this->pageFactory = $pageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Dalactive_StockAlert::subscribers');
        $page->getConfig()->getTitle()->prepend(__('Stock Alert Subscribers'));
        return $page;
    }
}
