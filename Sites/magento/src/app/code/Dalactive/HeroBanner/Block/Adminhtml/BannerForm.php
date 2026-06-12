<?php

namespace Dalactive\HeroBanner\Block\Adminhtml;

use Dalactive\HeroBanner\Model\BannerFactory;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class BannerForm extends Template
{
    private BannerFactory $bannerFactory;

    public function __construct(Context $context, BannerFactory $bannerFactory, array $data = [])
    {
        $this->bannerFactory = $bannerFactory;
        parent::__construct($context, $data);
    }

    public function getBanner()
    {
        $banner = $this->bannerFactory->create();
        $id = (int) $this->getRequest()->getParam('slide_id');
        if ($id) {
            $banner->load($id);
        }
        return $banner;
    }
}
