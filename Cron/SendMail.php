<?php

namespace Thai\Reports\Cron;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Thai\Reports\Helper\Data;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Item\Collection;
use Thai\Reports\Model\MailTemplate;

class sendMail
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Data
     */
    protected $helperData;

    /**
     * @var TimezoneInterface
     */
    protected $_date;
    protected $orderRepository;
    protected $stockItem;
    protected $encrypt;
    protected $collection;
    protected $template;

    public function __construct(
        Data $helperData,
        OrderRepository $orderRepository,
        TimezoneInterface $date,
        StockItemRepository $stockItem,
        StoreManagerInterface $storeManager,
        EncryptorInterface $encrypt,
        Collection $collection,
        MailTemplate $template
    )
    {
        $this->template = $template;
        $this->collection = $collection;
        $this->encrypt = $encrypt;
        $this->stockItem = $stockItem;
        $this->_date = $date;
        $this->orderRepository = $orderRepository;
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

    public function getOrderPaid($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        return $order->getTotal_paid();
    }

    /**
     * get product stock
     * @param $productId
     * @return float
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStockQty($productId)
    {
        return $this->stockItem->get($productId)->getQty();
    }

    /**
     * get user timezone
     * @return mixed
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getTimeZone()
     {
         return $this->storeManager->getStore()->getConfig('general/locale/timezone');
     }

    /**
     * Convert user timezone to UTC
     * @return string
     */
    function changeTimeZoneToUTC()
    {
        $settingTime = str_replace(',', ':', $this->helperData->getConfig('time'));
        $currentStoreTime = $this->_date->date()->format('Y-m-d '.$settingTime);
        $date = date_create($currentStoreTime, timezone_open($this->getTimeZone()));
        date_timezone_set($date, timezone_open('UTC'));
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Convert product sold array to csv format
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    function arrayToCsv()
    {
        $products = $this->getProduct();
        $csvFieldRow = array();
        foreach ($products as $item) {
            $checkStock = $item[3];
            if ($checkStock != 0) {
                $csvFieldRow[] = $this->putDataToCsv($item);
            }
        }
        return implode("\n", $csvFieldRow);
    }

    /**
     * config to convert arr to csv
     * @param $input
     * @param string $delimiter
     * @param string $enclosure
     * @return string
     */
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
     * get product sold on 24 hours
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getProduct()
    {
        $currentStoreTime = $this->changeTimeZoneToUTC();
        $lastday = strtotime('-1 day', strtotime($currentStoreTime));
        $lastday = date('Y-m-d h:i:s', $lastday);
        $orders = $this->collection
            ->addFieldToFilter('created_at', ['gteq' => $lastday])
            ->addFieldToFilter('created_at', ['lteq' => $currentStoreTime])
            ->getData();
        $data['heading'] = ['SKU', 'NAME', 'QTY ORDER', 'QTY STOCK', 'PURCHASE TIME'];
        if (count($orders) > 0) {
            foreach ($orders as $order) {
                $order_id = $order['order_id'];
                $status = $this->getOrderStatus($order_id);
                $isPaid = $this->getOrderPaid($order_id);
                if ($status == 'complete' && isset($isPaid)) {
                    $keyToCheck = $order['sku'].$order['name'];
                    if (array_key_exists($keyToCheck, $data)) {
                        $order['qty_ordered'] += $data[$keyToCheck][2];
                        $data[$keyToCheck] = [$order['sku'], $order['name'], $order['qty_ordered'], $this->getStockQty($order['product_id']), $order['created_at']];
                    } else {
                        $data[$keyToCheck] = [$order['sku'], $order['name'], $order['qty_ordered'], $this->getStockQty($order['product_id']), $order['created_at']];
                    }
                }
            }
        } else {
            $data[] = ['SKU', 'NAME', 'QTY ORDER', 'QTY STOCK', 'PURCHASE TIME'];
            $data[] = ['empty', 'empty', 'empty', 'empty', 'empty'];
        }
        return $data;
    }

    /**
     * Send email by ising Zend Mail
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Zend_Mail_Exception
     */
    public function sendMail()
    {
        $prorocol = $this->helperData->getConfig('protocol');
        $smtpHost = $this->helperData->getConfig('smtphost');
        $smtpPost = $this->helperData->getConfig('smtppost');
        $auth = strtolower($this->helperData->getConfig('auth'));
        $sender = trim($this->helperData->getConfig('username'));
        $password = $this->helperData->getConfig('password');
        $password = $this->encrypt->decrypt($password);
        $receiver = $this->helperData->getConfig('receiver_mail');
        $cc = $this->helperData->getConfig('cc_to');
        if ($auth != 'none') {
            $smtpConf = [
                'auth' => $auth,
                'ssl' => $prorocol,
                'port' => $smtpPost,
                'username' => $sender,
                'password' => $password
            ];
        }
        $attachment = $this->arrayToCsv();
        $reportTime = str_replace(',', ':', $this->helperData->getConfig('time'));
        $mailTemplateId = "report_template";
        $var = ["time" => $reportTime];
        $htmlBody = $this->template->getTemplate($mailTemplateId, $var);
//        $htmlBody = "<h2>Hello, Please check the attached file for a report of products sold within 24 hours of " . $reportTime . " AM yesterday.</h2>";
        $subject = __('Daily Report Product Sold');
        $csvFileName = 'Reports-' . $this->_date->date()->format('Y-m-d') . '.csv';
        return $this->helperData->mail_send($smtpHost, $smtpConf, $sender, $receiver, $cc, $htmlBody, $subject, $attachment, $csvFileName);
    }
}
