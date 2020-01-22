<?php

namespace Thai\Reports\Model;

use Magento\Framework\Option\ArrayInterface;

class HostConfig implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'smtp.gmail.com', 'label' => 'Gmail'],
            ['value' => 'smtp-mail.outlook.com', 'label' => 'Hotmail'],
            ['value' => 'smtp-mail.outlook.com', 'label' => 'Outlook'],
            ['value' => 'smtp.zoho.com', 'label' => 'Zoho Mail']
        ];
    }
}