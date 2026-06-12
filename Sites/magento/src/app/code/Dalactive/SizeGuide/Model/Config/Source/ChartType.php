<?php

namespace Dalactive\SizeGuide\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ChartType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => __('None')],
            ['value' => 'shoes', 'label' => __('Shoes')],
            ['value' => 'apparel', 'label' => __('Apparel')],
        ];
    }
}
