<?php
defined('_JEXEC') or die('Direct Access to ' . basename(__FILE__) . 'is not allowed.');

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
class ACUtils
{
    public const FLOW_DATA_PROCESSED_AMOUNT = "processedAmountInUSD";

    /**
     * @param array $data
     * @return float
     */
    public static function getProcessedAmount(array $data): float
    {
        if (!isset($data['flowData'])) {
            return 0;
        }

        $processedAmount = array_filter($data['flowData'], function ($item) {
            return isset($item['name']) && $item['name'] === self::FLOW_DATA_PROCESSED_AMOUNT;
        });

        if (!count($processedAmount)) {
            return 0;
        }

        return $processedAmount[array_key_first($processedAmount)]['value'];
    }
}