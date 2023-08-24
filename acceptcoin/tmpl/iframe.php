<?php
defined('_JEXEC') or die();

vmJsApi::css('acceptcoin', 'plugins/vmpayment/acceptcoin/assets');

if (isset($viewData['error'])) {
  JFactory::getApplication()->enqueueMessage($viewData['error'], 'error');
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

if (isset($viewData['iframeLink'])) {
  vmJsApi::css('acceptcoin', 'plugins/vmpayment/acceptcoin/assets');
  ?>

  <div id="acceptcoin-frame" class="frame-body">
    <iframe
        class="acceptcoin-iframe"
        sandbox="allow-top-navigation allow-scripts allow-same-origin"
        src=<?=$viewData['iframeLink'] ?>></iframe>
  </div>

  <?php
}
?>
<div></div>
