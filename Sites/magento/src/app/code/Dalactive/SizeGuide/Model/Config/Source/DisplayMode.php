<?php

namespace Dalactive\SizeGuide\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DisplayMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'popup', 'label' => __('Popup')],
            ['value' => 'tab', 'label' => __('Product Tab')],
            ['value' => 'block', 'label' => __('Block')],
        ];
    }
}
