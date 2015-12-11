<?php
/*
 * GetFinancing Payment Module v.1.0.0 for ZenCart
 * Copyright (c) 2015 GetFinancing
 * Contributor @sortegam
 *
 * Portions Copyright (c) 2003 The zen-cart developers
 * Portions Copyright (c) 2003 osCommerce
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or (at
 * your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street - Fifth Floor, Boston, MA  02110-1301, USA.
 *
 */
?>
<?php

define('TABLE_GETFINANCING',  'getfinancing');
define('GF_DEBUG', false);


class getfinancing {

    var $module_version = "1.0.0";

    var $code;
    var $title;
    var $description;
    var $enabled;

    // GF Internal Vars

    var $gf_url;
    var $gf_username;
    var $gf_password;
    var $gf_merchant_id;
    var $gf_environment;

    /**
     * Default constructor
     */
    function __construct() {

        global $order;

        $this->code = 'getfinancing';
        $this->title = 'GetFinancing - Finance Your Purchase Now!';
        $this->description = '';
        $this->enabled = ((MODULE_PAYMENT_GETFINANCING_STATUS == 'True') ? true : false);
        $this->sort_order = MODULE_PAYMENT_GETFINANCING_SORT_ORDER;

        if ((int)MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_ID;
        }

        if (is_object($order)) $this->update_status();

        // Set getFinancing vars

        $this->gf_username = MODULE_PAYMENT_GETFINANCING_USERNAME;
        $this->gf_password = MODULE_PAYMENT_GETFINANCING_PASSWORD;
        $this->gf_merchant_id = MODULE_PAYMENT_GETFINANCING_MERCHANT_ID;
        $this->gf_environment = MODULE_PAYMENT_GETFINANCING_ENVIRONMENT;

        if ($this->gf_environment == "Test") {
            $this->gf_url = 'https://api-test.getfinancing.com/merchant/' . $this->gf_merchant_id . '/requests';
        } else {
            $this->gf_url = 'https://api.getfinancing.com/merchant/' . $this->gf_merchant_id . '/requests';
        }

    }


    /**
     * Update the module status.
     *
     * This is called after the order instance is set up to allow both payment
     * module and order to synchronise.
     */
    function update_status() {
    global $order, $db;

        if ($this->enabled && ((int)MODULE_PAYMENT_GETFINANCING_ZONE > 0)) {
            $check_flag = false;
            $sql = "select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . "
                    where geo_zone_id = '" . MODULE_PAYMENT_GETFINANCING_ZONE . "'
                      and zone_country_id = :countryId
                    order by zone_id";
            $sql = $db->bindVars($sql, ':countryId', $order->billing['country']['id'], 'integer');

            $results = $db->Execute($sql);
            while (!$results->EOF) {
                if ($results->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($results->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $results->MoveNext();
            }

            if (!$check_flag) {
                $this->enabled = false;
            }
        }
    }


    /**
     * JavaScript form validation.
     *
     * @return JavaScript form validation code for this module.
     */
    function javascript_validation() {
        return false;
    }


    /**
     * Generate form  / Information for this module.
     *
     * @return array Form fields to be displayed during checkout.
     */
    function selection() {
        $selection = array('id' => $this->code, 'module' => $this->title);
        $selection['fields'] = array(
              array(
                     'title' => MODULE_PAYMENT_GETFINANCING_CHECKOUT_ICON,
                     'field' => MODULE_PAYMENT_GETFINANCING_CHECKOUT_TEXT

              ),
        );
        return $selection;
    }


    /**
     * Called before the confirmation page is displayed.
     *
     * This method typically would implement server side validation and would
     * do either of the following:
     * <ul>
     *   <li>add error messages to the <code>$messageStack</code></li>
     *   <li>redirect to <code>FILENAME_CHECKOUT_PAYMENT</code> with a parameter
     *    <code>payment_error</code> set to the payment module.
     *    This will trigger a call to <code>get_error()</code> on the payment
     *    page.</li>
     * </ul>
     *
     */
    function pre_confirmation_check() {
        return false;
    }

    function after_process() {

        global $db, $insert_id;

        // Insert the related data to db to later process the postback.
        $sql_entry = "INSERT INTO " . TABLE_GETFINANCING . " (date_process, zen_order_id, gf_token) ";
        $sql_entry .= "VALUES ('" . mktime() . "','" . $insert_id . "','" . $_SESSION['getfinancing_token'] . "')";
        $db->Execute($sql_entry);

        return false;
    }

    function get_error() {
      return false;
    }

    /**
     * Called during display of the order confirmation page.
     *
     * @return array Payment information that should be displayed on the
     *  order confirmation page.
     */
    /**
     * Generates the confirmation form
     * @return array
     */
    function confirmation() {
        $confirmation = array('title' => $title);
        return $confirmation;
    }


    /**
     * The return value of this method is inserted into the confirmation page form.
     *
     * Usually this method would create hidden fields to be added to the form.
     *
     * @return string Valid HTML.
     */
    function process_button() {

        global $order, $db, $messageStack;
        $gfResponse = $this->_processGFPay();

        $url_ko = zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false);

        $messageStack->add_session('checkout_payment', MODULE_PAYMENT_GETFINANCING_TEXT_NOT_AVAILABLE, 'error');

        $gfjs = '
        <script type="text/javascript">
            var onComplete = function() {
                // Emulates onclick
                $("#btn_submit").click();
            };

            var onAbort = function() {
                window.location.href="' . $url_ko . '";
            };
            $("#btn_submit").hide();
            new GetFinancing("' . $gfResponse->href . '", onComplete, onAbort);
        </script>';

        return $gfjs;
    }


    /**
     * Called before the checkout is actually performed.
     *
     * This is the central method for most payment modules.
     */
    function before_process() {
        if (isset($_SESSION['getfinancing_ok']) && true == $_SESSION['getfinancing_ok']) {
          // Not used since we emulate Confirm Order Button Click --
             // --> zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
        } else {
            $this->goToPaymentFailed();
        }
        return false;
    }

    /**
    * Function to get the cart products
    * @return string up to 255 chars.
    */
    function _getProductsInfo() {
        global $order;
        if (sizeof($order->products)==0) {
        }
        $strRet = '';
        $pluschar = '';
        foreach($order->products as $products) {
            if (!empty($strRet)) { $pluschar = ', '; }
            $strRet.=  $pluschar . $products['name'];
        }

        return substr($strRet, 0 , 255);
    }

    /**
     * Simple log method.
     *
     * @param string msg The message.
     */
    function log($msg) {
        return false;
    }

    /**
     * Create inital GetFinancing request and process response.
     *
     * If successful, this will redirect to the GetFinancing page to process the payment.
     */
    function _processGFPay() {

        global $order, $messageStack;

        $merchant_loan_id = md5(mktime() . $this->gf_merchant_id . $order->customer['firstname'] . $order->info['total']);

        $gf_data = array(
            'amount'           => round($order->info['total'], 2),
            'product_info'     => $this->_getProductsInfo(),
            'first_name'       => $order->customer['firstname'],
            'last_name'        => $order->customer['lastname'],
            'shipping_address' => array(
                'street1'  => $order->delivery['street_address'],
                'city'    => $order->delivery['city'],
                'state'   => $order->delivery['state'],
                'zipcode' => $order->delivery['postcode']
            ),
            'billing_address' => array(
                'street1'  => $order->billing['street_address'],
                'city'    => $order->billing['city'],
                'state'   => $order->billing['state'],
                'zipcode' => $order->billing['postcode']
            ),
            'version'          => '1.9',
            'shipping_amount'  => number_format($order->info['shipping_cost'], 2, '.', ''),
            'email'            => $order->customer['email_address'],
            'merchant_loan_id' => $merchant_loan_id
        );


        $body_json_data = json_encode($gf_data);
        $header_auth = base64_encode($this->gf_username . ":" . $this->gf_password);

        if (GF_DEBUG) {
            echo $body_json_data;
            echo "<br>";
        }

        $post_args = array(
            'body' => $body_json_data,
            'timeout' => 60,     // 60 seconds
            'blocking' => true,  // Forces PHP wait until get a response
            'headers' => array(
              'Content-Type' => 'application/json',
              'Authorization' => 'Basic ' . $header_auth,
              'Accept' => 'application/json'
             )
        );

        if (GF_DEBUG) {
            echo '<pre>' . print_r($post_args, true) . '</pre>';
        }

        $gf_response = $this->_remote_post( $this->gf_url, $post_args );

        if (GF_DEBUG) {
            echo '<br><br><pre>' . print_r($gf_response, true) . '</pre>';
            die();
        }

        if ($gf_response === false) {
            $this->goToPaymentFailed();
        }

        $gf_response = json_decode($gf_response);

        if ((isset($gf_response->href) == FALSE) || (empty($gf_response->href)==TRUE)) {
            unset($_SESSION['getfinancing_payment']);
            $this->goToPaymentFailed();
        } else {
            $_SESSION['getfinancing_ok'] = true;
        }

        $_SESSION['getfinancing_token'] = $merchant_loan_id;

        // If we are here that means that the gateway give us a "created" status.

        return $gf_response;

    }


    function goToPaymentFailed() {
        $messageStack->add_session('checkout_payment', MODULE_PAYMENT_GETFINANCING_TEXT_NOT_AVAILABLE, 'error');
        unset( $_SESSION['getfinancing_token'] );
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', false, false));
        return;
    }

    /**
     * Check if this module is enabled or not.
     *
     * Admin function.
     *
     * @return bool <code>true</code> if this module is enabled, <code>false</code> if not.
     */
    function check() {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . "
                                         where configuration_key = 'MODULE_PAYMENT_GETFINANCING_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        $this->_check_install_gf_table();

        return $this->_check;
    }


    /**
     * Install this module.
     *
     * Admin function.
     *
     * Typically inserts this modules configuration settings into the database.
     */
    function install() {
    global $db;
        $getfinancing_icon = '<img src="/images/getfinancing/btn_getfinancing_checkout.png" style="width:150px;" alt="GetFinancing" />';
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable GetFinancing Module', 'MODULE_PAYMENT_GETFINANCING_STATUS', 'True', '', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('GetFinancing Environment', 'MODULE_PAYMENT_GETFINANCING_ENVIRONMENT', 'Test', '', '6', '1', 'zen_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('GetFinancing Merchant ID', 'MODULE_PAYMENT_GETFINANCING_MERCHANT_ID', '', '', '6', '2', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('GetFinancing Username', 'MODULE_PAYMENT_GETFINANCING_USERNAME', '', '', '6', '3', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('GetFinancing Password', 'MODULE_PAYMENT_GETFINANCING_PASSWORD', '', '', '6', '4', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Text shown at checkout', 'MODULE_PAYMENT_GETFINANCING_CHECKOUT_TEXT', 'GetFinancing provides multiple easy instant credit options for your purchase for all credit types.', '', '6', '5', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Icon shown at checkout', 'MODULE_PAYMENT_GETFINANCING_CHECKOUT_ICON', '" . $getfinancing_icon . "', '', '6', '5', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_GETFINANCING_ZONE', '0', 'If a zone is selected, allow this payment method for that zone only.', '6', '35', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort Order', 'MODULE_PAYMENT_GETFINANCING_SORT_ORDER', '0', 'ZenCart sort order', '6', '35', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Pending Order Status', 'MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_ID', '0', 'Initial order status just made with this payment module.', '6', '40', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Approved Order Status', 'MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_APPROVED_ID', '0', 'Set the status of approved orders by GetFinancing. (On postback request)', '6', '45', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('PreApproved Order Status', 'MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_PREAPPROVED_ID', '0', 'Set the status of pre-approved orders by GetFinancing. (On postback request)', '6', '44', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Rejected Order Status', 'MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_REJECTED_ID', '0', 'Set the status of rejected orders by GetFinancing. (On postback request)', '6', '50', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $this->_check_install_gf_table();
    }


    /**
     * Remove this module and all associated configuration values/files etc.
     *
     * Admin function.
     */
    function remove() {
    global $db;

        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        $db->Execute("drop table " . TABLE_GETFINANCING);
    }

    function _check_install_gf_table() {
        global $sniffer, $db;
        if (!$sniffer->table_exists(TABLE_GETFINANCING)) {
            $sql = "CREATE TABLE " . TABLE_GETFINANCING . " (
                    id int(11) unsigned NOT NULL auto_increment,
                    date_process int(11) NOT NULL default '0',
                    zen_order_id varchar(200) NOT NULL default '0',
                    gf_token varchar(200) NOT NULL default '0',
                    PRIMARY KEY (id),
                    KEY (gf_token))";
            $db->Execute($sql);
        }
    }

    /**
     * Returns the configuration keys used by this module.
     *
     * Admin function.
     *
     * @return array List of configuration keys used by this module.
     */
    function keys() {
    global $db;

        $results = $db->Execute("select configuration_key from " . TABLE_CONFIGURATION . "
                                 where configuration_key like 'MODULE_PAYMENT_GETFINANCING_%' " . "
                                 order by sort_order");
        $keys = array();
        while (!$results->EOF) {
            array_push($keys, $results->fields['configuration_key']);
            $results->MoveNext();
        }

        return $keys;
    }


    /**
     * Set up RemotePost / Curl.
     */
    function _remote_post($url,$args=array()) {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $args['body']);
        curl_setopt($curl, CURLOPT_USERAGENT, 'ZenCart - GetFinancing Payment Module ' . $this->module_version);
        if (defined('CURLOPT_POSTFIELDSIZE')) {
            curl_setopt($curl, CURLOPT_POSTFIELDSIZE, 0);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, $args['timeout']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        $array_headers = array();
        foreach ($args['headers'] as $k => $v) {
            $array_headers[] = $k . ": " . $v;
        }
        if (sizeof($array_headers)>0) {
          curl_setopt($curl, CURLOPT_HTTPHEADER, $array_headers);
        }

        if (strtoupper(substr(@php_uname('s'), 0, 3)) === 'WIN') {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        }

        $resp = curl_exec($curl);
        curl_close($curl);

        if (!$resp) {
          return false;
        } else {
          return $resp;
        }
    }

}
