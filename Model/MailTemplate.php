<?php

namespace Thai\Reports\Model;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\Template\Factory;

class MailTemplate
{
    /**
     * @var TransportBuilder
     */
    protected $_transportBuilder;
    protected $templateFactory;
    private $templateModel;

    public function __construct(
        TransportBuilder $transportBuilder,
        Factory $templateFactory
    )
    {
        $this->_transportBuilder = $transportBuilder;
        $this->templateFactory = $templateFactory;
    }

    public function getTemplate($mailTemplateId, $var)
    {
        return $this->templateFactory
            ->get($mailTemplateId, '')
            ->setOptions(
                [
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                ]
            )
            ->setVars($var)
            ->processTemplate();
    }
}
