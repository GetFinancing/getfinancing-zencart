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
  define('TABLE_GETFINANCING_ORDERS',  'getfinancing_orders');
  define('TABLE_GETFINANCING_ORDERS_TOTAL',  'getfinancing_orders_total');
  define('TABLE_GETFINANCING_ORDERS_PRODUCTS',  'getfinancing_orders_prodcuts');
  define('TABLE_GETFINANCING_ORDERS_PRODUCTS_ATTRIBUTES',  'getfinancing_orders_products_attributes');
  define('TABLE_GETFINANCING_ORDERS_PRODUCTS_DOWNLOAD',  'getfinancing_orders_products_download');

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

  $sql_get_transaction = "select zen_order_id from " . TABLE_GETFINANCING . " where gf_token = '" . $merchant_transaction_id . "' and new_zen_order_id = 0";

  # lookup corresponding transaction:
  $gf_transaction = $db->Execute($sql_get_transaction);
  $orderId = (int) $gf_transaction->fields['zen_order_id'];
  // If no order id getted then exit process.
  if ($orderId == 0){
    throw new Exception('Order not found');
  }

  # lookup for the order.
  $order = $db->Execute("select * from " . TABLE_GETFINANCING_ORDERS . " where orders_id = " . $orderId);

  $orderIdCheck = (int) $order->fields['orders_id'];
  $orderStatus = (int) $order->fields['orders_status'];
  // If no order matching then exit process.
  if ($orderIdCheck != $orderId) {
     throw new Exception('No order maching');
  }


    $set_order_to = "";
    $msg_history = "";

    if ($updates->status == "preapproved") {
      if ($orderStatus != MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_APPROVED_ID ){
        $set_order_to = MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_PREAPPROVED_ID;
        $msg_history = "GetFinancing Pre-approved the order";
      }
      die("Order preapproved. Skipping.");

    }
    if ($updates->status == "rejected") {
        $set_order_to = MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_REJECTED_ID;
        $msg_history = "GetFinancing Rejected the order";
        die("Order rejected. Skipping.");
    }
    if ($updates->status == "approved") {
      $set_order_to = MODULE_PAYMENT_GETFINANCING_ORDER_STATUS_POSTBACK_APPROVED_ID;
      $msg_history = "GetFinancing Approved the order";
    }

  //insert order
  $gf_orderId = $order->fields['orders_id'];
  unset($order->fields['orders_id']);
  zen_db_perform(TABLE_ORDERS, (array) $order->fields);
  $new_orderId = $db->insert_ID();
  $order_total = $db->Execute("select * from " . TABLE_GETFINANCING_ORDERS_TOTAL . " where orders_id = " . $orderId);
  unset($order_total->fields['orders_total_id']);
  $order_total->fields['orders_id']=$new_orderId;

  zen_db_perform(TABLE_ORDERS_TOTAL, (array) $order_total->fields);
  $orderTotalId = $db->insert_ID();


  $sql="select * from " . TABLE_GETFINANCING_ORDERS_PRODUCTS . " where orders_id = " .$orderId;
  $results = $db->Execute($sql);
  while (!$results->EOF) {
      $results->fields['orders_id']=$new_orderId;
      unset($results->fields['orders_products_id']);


      zen_db_perform(TABLE_ORDERS_PRODUCTS, (array) $results->fields);
      $orderProductsID = $db->insert_ID();

          $sql="select * from " . TABLE_GETFINANCING_ORDERS_PRODUCTS_ATTRIBUTES . " where orders_id = " . $orderId;
          $results2 = $db->Execute($sql);
          while (!$results2->EOF) {

              $results2->fields['orders_id']=$new_orderId;
              //$results2->fields['orders_products_id	']=$orderProductsID;
              unset($results2->fields['orders_products_attributes_id']);

              zen_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, (array) $results2->fields);
              $order_products_attr_id = $db->insert_ID();
              $order_products_down = $db->Execute("update " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " set orders_products_id = ".$orderProductsID." where orders_products_attributes_id = " .$order_products_attr_id);
                  $results2->MoveNext();
          }


          $sql="select * from " . TABLE_GETFINANCING_ORDERS_PRODUCTS_DOWNLOAD . " where orders_id = " .$orderId;
          $results3 = $db->Execute($sql);
          while (!$results3->EOF) {
              $results3->fields['orders_id']=$new_orderId;
              //$results3->fields['orders_products_id	']=$orderProductsID;
              unset($results3->fields['orders_products_download_id']);

              zen_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, (array) $results3->fields);
              $order_products_down_id = $db->insert_ID();
              $order_products_down = $db->Execute("update " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set orders_products_id = ".$orderProductsID." where orders_products_download_id = " .$order_products_down_id);

                  $results3->MoveNext();
          }

          $results->MoveNext();
  }




  $order_products_down = $db->Execute("update " . TABLE_GETFINANCING . " set new_zen_order_id = ".$new_orderId." where zen_order_id = " .$orderId);

  # update order:
  if (empty($set_order_to) == FALSE) {

    # update order status to reflect Completed status and store transaction ID in field cc_number:
    $new_order_status_id = $set_order_to;
    $db->Execute("update " . TABLE_ORDERS . " set orders_status = '" . $new_order_status_id . "', last_modified = now() where orders_id = '" . $new_orderId . "'");

    # update order status history:
    $sql_data_array = array('orders_id' => $new_orderId,
                            'orders_status_id' => $new_order_status_id,
                            'date_added' => 'now()',
                            'customer_notified' => '0',
                            'comments' => $msg_history);
    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
  }

  require('includes/application_bottom.php');
?>
