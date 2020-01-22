<?php

namespace Thai\Reports\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_SECTION = 'report/';
    const XML_PATH_GROUP = 'configurable_cron/';

    // get config value
	public function getConfigValue($field, $storeId = null)
	{
		return $this->scopeConfig->getValue(
			$field, ScopeInterface::SCOPE_STORE, $storeId
		);
	}

	public function getConfig($code, $storeId = null)
	{
		return $this->getConfigValue(self::XML_PATH_SECTION .self::XML_PATH_GROUP. $code, $storeId);
	}

    // check enable extension
    public function isEnable ()
    {
        $enable = true;
        if ($this->getConfig('enable_module') != 0)
        {
            return $enable;
        }
    }
}