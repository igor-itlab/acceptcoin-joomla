<?php

use Joomla\CMS\Http\HttpFactory;

defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . 'is not allowed.');

if (!class_exists('JWT')) {
    require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'acceptcoin' . DS . 'api' . DS . 'Services' . DS . 'JWT.php');
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
class AcceptcoinApi
{

    /**
     * @param $orderNumber
     * @param $projectId
     * @param $projectSecret
     * @param $amount
     * @param $paymentMethodId
     * @return mixed
     * @throws Exception
     */
    public static function createPayment(
        $orderNumber,
        $projectId,
        $projectSecret,
        $amount,
        $paymentMethodId,
        $returnUrlSuccess,
        $returnUrlFailed
    ): mixed
    {
        if (!$orderNumber || !$projectId || !$projectSecret || !$amount || !$paymentMethodId) {
            throw new Exception("Acceptcoin payment method is not available at this moment.");
        }

        $http = HttpFactory::getHttp();
        $callback = JURI::base() . 'index.php?option=com_virtuemart&view=vmplg&task=pluginresponsereceived&pm=' . $paymentMethodId;

//        $acceptcoinUrl = "https://acceptcoin.io";
        $acceptcoinUrl = "https://dev7.itlab-studio.com";

        $body = [
            "amount"      => (string)$amount,
            "referenceId" => $orderNumber,
            "callBackUrl" => $callback
        ];

        if ($returnUrlSuccess) {
            $body ["returnUrlSuccess"] = $returnUrlSuccess;
        }

        if ($returnUrlFailed) {
            $body ["returnUrlFail"] = $returnUrlFailed;
        }

        $response = $http->post("$acceptcoinUrl/api/iframe-invoices",
            json_encode($body),
            [
                "Accept"        => "application/json",
                "Content-Type"  => "application/json",
                "Authorization" => "JWS-AUTH-TOKEN " . JWT::createToken($projectId, $projectSecret)
            ]
        );

        $data = json_decode($response->body, true);

        if (!$data) {
            throw new Exception('Acceptcoin payment method is not available at this moment.');
        }

        if (isset($data['status']) && $data['status'] !== 200) {
            throw new Exception('Acceptcoin payment method is not available at this moment.');
        }

        if (!isset($data['link'])) {
            throw new Exception('Acceptcoin payment method is not available at this moment.');
        }

        return $data['link'];
    }


    /**
     * @param object $order
     * @param string $type
     * @param array $responseContent
     * @param bool $isHtml
     * @return bool|void
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public static function sendMessage(
        object $order,
        string $type,
        array  $responseContent,
        bool   $isHtml = true
    )
    {
        $userEmail = $order->email;
        $vendorId = $order->virtuemart_vendor_id;
        $vendorModel = VmModel::getModel('vendor');
        $vendorEmail = $vendorModel->getVendorEmail($vendorId);
        $data = self::getEmailBody($type, $order, $responseContent);

        if (!$data) {
            return;
        }

        return JFactory::getMailer()
            ->setSender($vendorEmail)
            ->addRecipient($userEmail)
            ->setSubject($data['subject'])
            ->setBody($data['body'])
            ->isHTML($isHtml)
            ->send();
    }


    /**
     * @param string $type
     * @param object $order
     * @param array $responseContent
     * @return string[]|null
     */
    private static function getEmailBody(string $type, object $order, array $responseContent): ?array
    {
        switch ($type) {
            case "FROZEN_DUE_AML":
            {
                return [
                    "subject" => "Dirty coins were identified through AML checks",
                    "body"    => "
                       <div>
                          <p>
                            Dear, " . $order->first_name . " " . $order->last_name . ". Your transaction for " . $responseContent['amount'] . " " . $responseContent['projectPaymentMethods']['paymentMethod']['currency']['asset'] . " was blocked. 
                            Transaction ID " . $responseContent['referenceId'] . ".
                          </p>
                          <p><b>To confirm the origin of funds, we ask that you fully answer the following questions:</b></p>
                          <ol>
                            <li>Through which platform did the funds come to you? If possible, please provide screenshots from the wallet/sender platform's withdrawal history, as well as links to both transactions on the explorer.</li>
                            <li>For what service did you receive the funds? - What was the transaction amount, as well as the date and time it was recieved?</li>
                            <li>Through which contact person did you communicate with the sender of the funds? If possible, please provide screenshots of your correspondence with the sender, where we can see confirmation of the transfer of funds.</li>
                          </ol>
  
                          <br>
                          <p>Additionally, we ask that you provide the following materials:</p>
                          <ul>
                            <li>Photo of one of your documents (passport, ID card or driver's license).</li>
                            <li>A selfie with this document and a sheet of paper on which today's date and signature will be handwritten.</li>
                          </ul>
                          <p><b>Please carefully write down the answers to these questions and email to support@acceptcoin.io</b></p>
                          <hr>
                          NOTE: <i>Please, don’t answer this mail, send your answer only to support@acceptcoin.io</i>.
                          </div>"
                ];
            }
            default:
            {
                return null;
            }
        }
    }

}