<?php

namespace Thai\Reports\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Zend_Mail_Transport_Smtp;
use Zend_Mail;

/**
 * Class Data
 * @package Thai\Reports\Helper
 */
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

    /**
     * send mail function
     * @throws \Zend_Mail_Exception
     */
    public function mail_send(
        $smtpHost = null,
        $smtpConf = null,
        $sender = null,
        $receiver = null,
        $cc = null,
        $htmlBody = null,
        $subject = null,
        $attachment = null,
        $attachmentName = null
    )
    {
        if ($this->isEnable()) {
            $transport = new Zend_Mail_Transport_Smtp($smtpHost, $smtpConf);
            $mail = new Zend_Mail('utf-8');
            $mail->setFrom($sender, 'Admin');
            $mail->addTo($receiver, '');
            if ($cc) {
                $mail->addCc($cc, '');
            }
            $mail->setSubject($subject);
            $mail->setBodyHtml($htmlBody);
            $mail->createAttachment($attachment,
                \Zend_Mime::TYPE_OCTETSTREAM,
                \Zend_Mime::DISPOSITION_ATTACHMENT,
                \Zend_Mime::ENCODING_BASE64,
                $attachmentName
            );
            try {
                if (!$mail->send($transport) instanceof Zend_Mail) {
                }
            } catch (Exception $e) {
                $this->error(true, __($e->getMessage()));
            }
        }
    }

    /**
     * @param bool $hasError
     * @param string $msg
     * @return array
     */
    public function error($hasError = false, $msg = '')
    {
        return [
            'has_error' => (bool)$hasError,
            'msg' => (string)$msg
        ];
    }
}
