<?php

namespace Dalactive\PhotoReview\Controller\Adminhtml\Review;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Dalactive_PhotoReview::reviews';

    private PageFactory $pageFactory;

    public function __construct(Context $context, PageFactory $pageFactory)
    {
        $this->pageFactory = $pageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Dalactive_PhotoReview::reviews');
        $page->getConfig()->getTitle()->prepend(__('Photo Reviews'));
        return $page;
    }
}
