<?php

namespace Dalactive\GhtkShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Environment implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'staging', 'label' => __('Staging / Sandbox')],
            ['value' => 'production', 'label' => __('Production')],
        ];
    }
}
