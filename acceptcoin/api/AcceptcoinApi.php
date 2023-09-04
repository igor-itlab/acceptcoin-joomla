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
     * @param $returnUrlSuccess
     * @param $returnUrlFailed
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

        $acceptcoinUrl = "https://acceptcoin.io";

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

}