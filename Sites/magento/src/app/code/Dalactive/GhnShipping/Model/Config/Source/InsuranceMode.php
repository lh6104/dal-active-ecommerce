<?php

declare(strict_types=1);

namespace Dalactive\GhnShipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InsuranceMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'subtotal', 'label' => __('Cart Subtotal')],
            ['value' => 'fixed', 'label' => __('Fixed Value')],
            ['value' => 'none', 'label' => __('No Insurance')],
        ];
    }
}
