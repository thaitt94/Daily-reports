<?php

namespace Thai\Reports\Model;

use Magento\Framework\Option\ArrayInterface;

class PortConfig implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => '465', 'label' => '465'],
            ['value' => '587', 'label' => '587']
        ];
    }
}