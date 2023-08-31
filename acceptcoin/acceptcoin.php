<?php

use Joomla\CMS\Log\Log;

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . 'is not allowed.');

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

if (!class_exists('AcceptcoinApi')) {
    require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'acceptcoin' . DS . 'api' . DS . 'AcceptcoinApi.php');
}

if (!class_exists('Signature')) {
    require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'acceptcoin' . DS . 'api' . DS . 'Services' . DS . 'Signature.php');
}

if (!class_exists('ACUtils')) {
    require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'acceptcoin' . DS . 'api' . DS . 'Services' . DS . 'ACUtils.php');
}

if (!class_exists('MailHelper')) {
    require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'acceptcoin' . DS . 'api' . DS . 'Services' . DS . 'MailHelper.php');
}

/**
 * Acceptcoin payment plugin:
 * @author Softile Limited
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (c) Softile Limited. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * https://acceptcoin.io/plugins/joomla
 * https://softile.limited
 */
class plgVmPaymentAcceptCoin extends vmPSPlugin
{
    public $version = "1.0";
    protected $_isInList = false;

    protected $imgPath = "https://acceptcoin.io/assets/images/logo50.png";

    protected const STABLE_CURRENCY = "USD";

    protected const RESPONSE_STATUSES = [
        "PENDING"        => 'P',
        "PROCESSED"      => "U",
        "FAIL"           => "X",
        "FROZEN_DUE_AML" => "X"
    ];

    protected const STATUS_PROCESSED = "PROCESSED";

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';

        $varsToPush = $this->getVarsToPush();
        $this->addVarsToPushCore($varsToPush);
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
        $this->setConvertable(array('cost_per_transaction', 'cost_min_transaction'));
        $this->setConvertDecimal(array('cost_per_transaction', 'cost_min_transaction'));
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     */
    public function getVmPluginCreateTableSQL(): string
    {
        return $this->createTableSQL('Payment Acceptcoin Table');
    }

    /**
     * Fields to create the payment table
     *
     * @return string[] SQL Filed
     */
    public function getTableSQLFields(): array
    {
        return [
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'int(1) UNSIGNED',
            'order_number'                => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name'                => 'varchar(5000)',
            'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'            => 'char(3)',
            'email_currency'              => 'char(3)',
            'cost_per_transaction'        => 'decimal(10,2)',
            'cost_min_transaction'        => 'decimal(10,2)',
            'cost_percent_total'          => 'decimal(10,2)',
            'tax_id'                      => 'smallint(1)'
        ];
    }

    /**
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param $paymentCounter
     * @return array|null
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices, &$paymentCounter): ?array
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * @param VirtueMartCart $cart
     * @param $msg
     * @return bool|null
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg): ?bool
    {
        return $this->OnSelectCheck($cart);
    }

    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     * @return false|void|null
     */
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
    }

    /**
     * @param VirtueMartCart $cart
     * @param $selected
     * @param $htmlIn
     * @return bool
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn): bool
    {
        $this->_isInList = true;

        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * @param $virtuemart_order_id
     * @param $virtuemart_paymentmethod_id
     * @param $payment_name
     * @return void
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name): void
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * Selected payment showing
     *
     * @param VirtueMartCart $cart
     * @param array $cart_prices
     * @param $cart_prices_name
     * @return bool|null
     */
    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name): ?bool
    {
        $result = $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);

        $cart_prices_name = '<div style="display: flex; align-items: center;">
                  <img style="position: absolute; max-width: 30px;" src="' . $this->imgPath . '" alt="Acceptcoin"/>
                  <span style="margin-left: 35px">' . $cart_prices_name . "</span></div>";

        return $result;
    }

    /**
     * @param $cart
     * @param $order
     * @return bool|null
     */
    public function plgVmConfirmedOrder($cart, $order): ?bool
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        vRequest::setVar('display_title', "Acceptcoin pay");

        $projectId = $this->params->get('projectId');
        $secretKey = $this->params->get('secretKey');
        $returnUrlSuccess = $this->params->get('returnUrlSuccess');
        $returnUrlFailed = $this->params->get('returnUrlFail');

        if (!$projectId || !$secretKey) {
            $this->setTemplateData(['error' => "You have to set up Acceptcoin plugin configuration"]);
            return null;
        }

        $emailCurrency = $this->getEmailCurrency($method);
        $totalInPaymentCurrency = vmPSPlugin::getAmountValueInCurrency(
            $order['details']['BT']->order_total,
            $method->payment_currency
        );

        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = (int)$order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency;
        $dbValues['payment_currency'] = $method->payment_currency;
        $dbValues['email_currency'] = $emailCurrency;

        $this->storePSPluginInternalData($dbValues);

        $modelOrder = VmModel::getModel('orders');
        $order['order_status'] = $this->getNewStatus($method);

        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);

        $stableCurrencyId = $this->getCurrencyId(self::STABLE_CURRENCY);

        $paymentCurrency = CurrencyDisplay::getInstance($stableCurrencyId);
        $paymentCurrency->_vendorCurrency = $stableCurrencyId;

        $amountInUSD = $paymentCurrency->convertCurrencyTo($order['details']['BT']->payment_currency_id, $totalInPaymentCurrency);

        $vendorModel = VmModel::getModel('vendor');
        $vendorName = $vendorModel->getVendorName($order['details']['BT']->virtuemart_vendor_id);

        try {
            $link = AcceptcoinApi::createPayment(
                $order['details']['BT']->order_number,
                $projectId,
                $secretKey,
                $amountInUSD,
                $order['details']['BT']->virtuemart_paymentmethod_id,
                $returnUrlSuccess,
                $returnUrlFailed
            );

            $this->setTemplateData(['iframeLink' => $link]);

            MailHelper::sendMessage($order['details']['BT']?->email, MailHelper::TYPE_NEW, [
                'name'     => $order['details']['BT']->first_name,
                'lastname' => $order['details']['BT']->last_name,
                'amount'   => $totalInPaymentCurrency,
                'currency' => $paymentCurrency->ensureUsingCurrencyCode($order['details']['BT']->payment_currency_id),
                "link"     => $link,
                'vendorId' => $order['details']['BT']->virtuemart_vendor_id,
                'vendorName' => $vendorName
            ]);

            $cart->emptyCart();
        } catch (Throwable $exception) {
            $this->setTemplateData(['error' => $exception->getMessage()]);
            return null;
        }

        return true;
    }

    /**
     * @param array $args
     * @return void
     */
    public function setTemplateData(array $args): void
    {
        vRequest::setVar('html', $this->renderByLayout('iframe', $args));
    }

    /**
     * @param $html
     * @return bool|null
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function plgVmOnPaymentResponseReceived(&$html): ?bool
    {
        vmLanguage::loadJLang('com_virtuemart_orders', true);

        if (!class_exists('shopFunctionsF')) {
            require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }

        $virtuemart_paymentmethod_id = vRequest::getInt('pm');

        if (!($this->_currentMethod = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            return null;
        }

        $body = file_get_contents("php://input");
        $response = json_decode($body, true);

        if (!isset($response['data'])) {
            return null;
        }

        if (!is_array($response['data'])) {
            $response['data'] = json_decode($response['data'], true);
        }

        if (!isset($response['data']['referenceId'])) {
            return null;
        }

        if (!($order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($response['data']['referenceId']))) {
            return null;
        }

        if (!Signature::check(
            json_encode($response['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $response['signature'],
            $this->params->get('secretKey')
        )) {
            return null;
        }

        vmLanguage::loadJLang('com_virtuemart');
        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($order_id);

        if (!$order || $order['details']['BT']->order_status !== self::RESPONSE_STATUSES['PENDING']) {
            return null;
        }

        $order['order_status'] = self::RESPONSE_STATUSES[$response['data']['status']['value']];

        MailHelper::sendMessage(
            $order['details']['BT']?->email,
            $response['data']['status']['value'],
            [
                'name'          => $order['details']['BT']->first_name,
                'lastname'      => $order['details']['BT']->last_name,
                'transactionId' => $response['data']['id'],
                'date'          => date("Y-m-d H:i:s", $response['data']['createdAt']),
                'vendorId'      => $order['details']['BT']->virtuemart_vendor_id
            ]
        );

        if ($response['data']['status']['value'] === self::STATUS_PROCESSED) {
            $orderCD = CurrencyDisplay::getInstance($order['details']['BT']->payment_currency_id);
            $order['paid'] = $orderCD->convertCurrencyTo(
                $this->getCurrencyId(self::STABLE_CURRENCY),
                ACUtils::getProcessedAmount($response['data'])
            );
        }

        $orderModel->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);

        return true;
    }

    /**
     * @param $method
     * @return string
     */
    public function getNewStatus($method): string
    {
        if (isset($method->status_pending) and $method->status_pending != "") {
            return $method->status_pending;
        } else {
            return 'P';
        }
    }

    /**
     * get current Virtue mart version
     * @return string|int
     */
    public function getVirtuemartVersions(): string|int
    {
        return vmVersion::$RELEASE;
    }

    /**
     * get current Joomla version
     * @return mixed
     */
    public function getJoomlaVersions()
    {
        jimport('joomla.version');
        $version = new JVersion();

        return $version->RELEASE;
    }

    /**
     * @param $order_number
     * @param $method_id
     * @return mixed|string|null
     */
    public function plgVmOnShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * @param $data
     * @return bool
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data): bool
    {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * @param $name
     * @param $id
     * @param $table
     * @return bool
     */
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table): bool
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    private function getCurrencyId($curr)
    {

        $currInt = '';
        if (!empty($curr)) {
            $this->_db = JFactory::getDBO();
            $q = 'SELECT `virtuemart_currency_id` FROM `#__virtuemart_currencies` WHERE `currency_code_3`="' . $this->_db->escape($curr) . '"';
            $this->_db->setQuery($q);
            $currInt = $this->_db->loadResult();
            if (empty($currInt)) {
                vmWarn('Attention, couldnt find currency id in the table for id = ' . $curr);
            }
        }

        return $currInt;
    }
}