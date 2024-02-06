<?php
defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . 'is not allowed.');

if (!class_exists('JWT')) {
    require(VMPATH_ROOT . DS . 'plugins' . DS . 'vmpayment' . DS . 'acceptcoin' . DS . 'api' . DS . 'Services' . DS . 'ACUtils.php');
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
class JWT
{
    /**
     * @param $projectId
     * @param $secret
     * @return string
     */
    public static function createToken($projectId, $secret): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

        $payload = json_encode([
            'iat'       => time(),
            'exp'       => time() + 3600,
            "projectId" => $projectId
        ]);

        $urlHeader = str_replace(['+', '/', '='], ['-', '_', ''], ACUtils::transform($header));

        $urlPayload = str_replace(['+', '/', '='], ['-', '_', ''], ACUtils::transform($payload));

        $signature = hash_hmac('sha256', $urlHeader . "." . $urlPayload, $secret, true);

        $formattedSignature = str_replace(['+', '/', '='], ['-', '_', ''], ACUtils::transform($signature));

        return $urlHeader . "." . $urlPayload . "." . $formattedSignature;
    }

}