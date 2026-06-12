<?php

namespace Dalactive\HeroBanner\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class ManageBanners extends Field
{
    protected function _getElementHtml(AbstractElement $element): string
    {
        $url = $this->getUrl('dalactive_herobanner/banner/index');

        return sprintf(
            '<a class="action-default scalable" href="%s"><span>%s</span></a>',
            $this->escapeUrl($url),
            $this->escapeHtml(__('Manage Hero Banners'))
        );
    }

    protected function _renderScopeLabel(AbstractElement $element): string
    {
        return '';
    }

    protected function _isInheritCheckboxRequired(AbstractElement $element): bool
    {
        return false;
    }
}
