<?php
/*
 * 
 * $Id$
 * 
 * GetFinancing payment gateway implementation for ZenCart
 * https://www.getfinancing.com
 * 
 * Copyright (c) 2015 GetFinancing
 * @contributor @sortegam
 * 
 */

  chdir('../../../../');
  require('includes/application_top.php');
  global $db;

  define("GF_DEBUG", false);
  define('TABLE_GETFINANCING', DB_PREFIX . 'getfinancing');

  # parse JSON:
  $rawPOSTBody = file_get_contents("php://input");
  $parsed_data = json_decode($rawPOSTBody);

  if (GF_DEBUG) {
    echo (PHP_EOL . " Raw Post Body". PHP_EOL);        
    echo ("---------------------------------". PHP_EOL);
    print_r($rawPOSTBody);
    echo (PHP_EOL . " JSON Parsed Body". PHP_EOL);        
    echo ("---------------------------------". PHP_EOL);
    print_r($parsed_data);

  }

  # extract data:
  $request_token = (int)$parsed_data->request_token;
  $version = $parsed_data->version;
  $updates = $parsed_data->updates;
  $merchant_transaction_id = $parsed_data->merchant_transaction_id;

  $sql_get_transaction = "select zen_order_id from " . TABLE_GETFINANCING . " where gf_token = '" . $merchant_transaction_id . "'";

  # lookup corresponding transaction:
  $gf_transaction = $db->Execute($sql_get_transaction);
  
  $orderId = (int) $gf_transaction->fields['zen_order_id'];

  // If no order id getted then exit process.
  if ($orderId == 0){
    die();     
  }

  # lookup for the order.
  $order = $db->Execute("select orders_id from " . TABLE_ORDERS . " where orders_id = '" . $gf_transaction->fields['zen_order_id'] . "'");

  $orderIdCheck = (int) $order->fields['orders_id'];

  // If no order matching then exit process.
  if ($orderIdCheck != $orderId) {
    die();     
  }
  
  # What to do.

  $set_order_to = "";
  $msg_history = "";

  if ($updates->status == "preapproved") {
    $set_order_to = MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_PREAPPROVED_ID;
    $msg_history = "GetFinancing Pre-approved the order: " . $orderId;
  }
  if ($updates->status == "approved") {
    $set_order_to = MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_APPROVED_ID;
    $msg_history = "GetFinancing Approved the order: " . $orderId;
  }
  if ($updates->status == "rejected") {
    $set_order_to = MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_REJECTED_ID;
    $msg_history = "GetFinancing Rejected the order: " . $orderId;
  }


  # update order:
   
  if (empty($set_order_to) == FALSE) {
    # update order status to reflect Completed status and store transaction ID in field cc_number:
    $new_order_status_id = $set_order_to;
    $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . $new_order_status_id . "', last_modified = now() where orders_id = '" . $orderId . "'");

    # update order status history:
    $sql_data_array = array('orders_id' => $orderId,
                            'orders_status_id' => $new_order_status_id,
                            'date_added' => 'now()',
                            'customer_notified' => '0',
                            'comments' => $msg_history);
    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
  }

  require('includes/application_bottom.php'); 
?>