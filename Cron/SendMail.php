<?php

namespace Thai\Reports\Cron;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Zend_Mail_Transport_Smtp;
use Zend_Mail;
use Thai\Reports\Helper\Data;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Encryption\EncryptorInterface;

class sendMail
{
    protected $storeManager;
    protected $helperData;
    protected $_itemFactory;
    protected $_date;
    protected $orderRepository;
    protected $stockItem;
    protected $encrypt;

    /**
     * sendMail constructor.
     * @param Data $helperData
     * @param \Magento\Sales\Model\Order\ItemFactory $itemFactory
     * @param TimezoneInterface $date
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     * @param StockItemRepository $stockItem
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encrypt
     */
    public function __construct(
        Data $helperData,
        \Magento\Sales\Model\Order\ItemFactory $itemFactory,
        TimezoneInterface $date,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        StockItemRepository $stockItem,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encrypt
    )
    {
        $this->encrypt = $encrypt;
        $this->stockItem = $stockItem;
        $this->orderRepository = $orderRepository;
        $this->_date = $date;
        $this->_itemFactory = $itemFactory;
        $this->helperData = $helperData;
        $this->storeManager = $storeManager;
    }

    /**
     * get order status
     * @param $orderId
     * @return string|null
     */
    public function getOrderStatus($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getStatus();
    }

    /**
     * get Stock for  product
     * @param $productId
     * @return float
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStockQty($productId)
    {
        return $this->stockItem->get($productId)->getQty();
    }

    /**
     * Comment to plus
     * @param $arr
     * @param $sku
     * @param $name
     * @return int|string
     */

    public function findIndex($arr, $sku, $name)
    {
        $index = false;
        foreach ($arr as $key => $value) {
            if ($value[0] === $sku && $value[1] === $name) {
                return $index = $key;
            }
        }
        return $index;
    }

    function arrayToCsv()
    {
        $csvFieldRow = array();
        foreach ($this->getProduct() as $item) {
            $csvFieldRow[] = $this->putDataToCsv($item);
        }
        return implode("\n", $csvFieldRow);
    }

    function putDataToCsv($input, $delimiter = ',', $enclosure = '"')
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $input, $delimiter, $enclosure);
        // Rewind the file
        rewind($fp);
        // File Read
        $data = fread($fp, 1048576);
        fclose($fp);
        // Ad line break and return the data
        return rtrim($data, "\n");
    }

    /**
     * get product sold
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProduct()
    {
        $settingTime = str_replace(',', ':', $this->helperData->getConfig('time'));
        $currentStoreTime = $this->_date->date()->format('Y-m-d ' . $settingTime);
        $convertToUtc = date_create($currentStoreTime, timezone_open("UTC"));
        $currentStoreTimeAfterConvert = date_format($convertToUtc, "Y-m-d h:i:s");
        $lastday = strtotime('-1 day', strtotime($currentStoreTimeAfterConvert));
        $lastday = date('Y-m-d h:i:s', $lastday);

        $orders = $this->_itemFactory->create()->getCollection()
            ->addFieldToFilter('created_at', ['gteq' => $lastday])
            ->addFieldToFilter('created_at', ['lteq' => $currentStoreTime])->getData();

        $data[] = ['SKU', 'NAME', 'QTY ORDER', 'QTY STOCK'];
        if (count($orders) > 0) {
            foreach ($orders as $order) {
                $orderId = $order['order_id'];
                if ($this->getOrderStatus($orderId) === 'processing') {
                    $index = $this->findIndex($data, $order['sku'], $order['name']);
                    if ($index) {
                        $order['qty_ordered'] += $data[$index][2];
                        $data[$index] = [$order['sku'], $order['name'], $order['qty_ordered'], $this->getStockQty($order['product_id'])];
                    } else {
                        $data[] = [$order['sku'], $order['name'], $order['qty_ordered'], $this->getStockQty($order['product_id'])];
                    }
                }
            }
            return $data;
        }
    }

    /**
     * send mail function
     * @throws \Zend_Mail_Exception
     */
    public function sendMail()
    {
        if ($this->helperData->isEnable() ) {
            $prorocol = $this->helperData->getConfig('protocol');
            $smtpHost = $this->helperData->getConfig('smtphost');
            $smtpPost = $this->helperData->getConfig('smtppost');
            $auth = strtolower($this->helperData->getConfig('auth'));
            $username = $this->helperData->getConfig('username');
            $password = $this->helperData->getConfig('password');
            $password = $this->encrypt->decrypt($password);
            $rc = $this->helperData->getConfig('reciever_mail');
            $smtpConf = [
                'auth' => $auth,
                'ssl' => $prorocol,
                'port' => $smtpPost,
                'username' => $username,
                'password' => $password
            ];
            $transport = new Zend_Mail_Transport_Smtp($smtpHost, $smtpConf);
            $mail = new Zend_Mail('utf-8');
            $mail->setFrom($username, 'Adminer');
            $mail->addTo($rc, '');
            if ($this->helperData->getConfig('cc_to') != null) {
                $cc = $this->helperData->getConfig('cc_to');
                $mail->addCc($cc, '');
            }
            $mail->setSubject('Daily Report Product Sold');
            $htmlBody = "<h3>Hello, Please check the attached file for a report of products sold within 24 hours of " . str_replace(',', ':', $this->helperData->getConfig('time')) . " AM yesterday.</h3>";
            $mail->setBodyHtml($htmlBody);
            $attachment = $this->arrayToCsv();
            $mail->createAttachment($attachment,
                \Zend_Mime::TYPE_OCTETSTREAM,
                \Zend_Mime::DISPOSITION_ATTACHMENT,
                \Zend_Mime::ENCODING_BASE64,
                'Reports-' . $this->_date->date()->format('Y-m-d') . '.csv'
            );
            $result = $this->error();
            try {
                if (!$mail->send($transport) instanceof Zend_Mail) {
                }
            } catch (Exception $e) {
                $result = $this->error(true, __($e->getMessage()));
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
