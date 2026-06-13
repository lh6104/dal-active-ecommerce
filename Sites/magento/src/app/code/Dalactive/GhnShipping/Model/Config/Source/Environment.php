<?php

namespace Dalactive\GhnShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sandbox', 'label' => __('Sandbox / Staging')],
            ['value' => 'production', 'label' => __('Production')],
        ];
    }
}
