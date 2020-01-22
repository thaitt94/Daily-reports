<?php

namespace Thai\Reports\Model;

use Magento\Framework\Option\ArrayInterface;

class ProtocolConfig implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'ssl', 'label' => 'ssl'],
            ['value' => 'tls', 'label' => 'tls']
        ];
    }
}