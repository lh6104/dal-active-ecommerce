<?php

namespace Dalactive\SizeGuide\Block\Product;

use Dalactive\SizeGuide\Model\ResourceModel\Chart\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;

class SizeGuide extends Template
{
    private const XML_ENABLED = 'dalactive_sizeguide/general/enabled';
    private const XML_SHOW_PRODUCT = 'dalactive_sizeguide/general/show_on_product';
    private const XML_DISPLAY_MODE = 'dalactive_sizeguide/general/display_mode';
    private const XML_DEFAULT_CHART = 'dalactive_sizeguide/general/default_chart_type';

    private ScopeConfigInterface $scopeConfig;
    private Registry $registry;
    private CollectionFactory $chartCollectionFactory;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        Registry $registry,
        CollectionFactory $chartCollectionFactory,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->registry = $registry;
        $this->chartCollectionFactory = $chartCollectionFactory;
        parent::__construct($context, $data);
    }

    public function canShow(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_ENABLED, ScopeInterface::SCOPE_STORE)
            && $this->scopeConfig->isSetFlag(self::XML_SHOW_PRODUCT, ScopeInterface::SCOPE_STORE)
            && (bool) $this->registry->registry('current_product');
    }

    public function getDisplayMode(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_DISPLAY_MODE, ScopeInterface::SCOPE_STORE) ?: 'popup');
    }

    public function getDefaultChartType(): string
    {
        return (string) ($this->scopeConfig->getValue(self::XML_DEFAULT_CHART, ScopeInterface::SCOPE_STORE) ?: 'none');
    }

    public function getChartContent(): string
    {
        $chartType = $this->getDefaultChartType();
        if ($chartType === 'none') {
            return '';
        }

        $collection = $this->chartCollectionFactory->create();
        $collection->addFieldToFilter('status', 1);
        $collection->addFieldToFilter('product_type', $chartType);
        $collection->setPageSize(1);

        $chart = $collection->getFirstItem();
        return (string) $chart->getData('content');
    }
}
