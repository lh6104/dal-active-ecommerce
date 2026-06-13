<?php

namespace Dalactive\GhtkShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Transport implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'road', 'label' => __('Road')],
            ['value' => 'fly', 'label' => __('Fly')],
        ];
    }
}
