<?php

class TotalCoinAPI {
  const version = "0.1";

  private $api_key;
  private $client_email;

  function __construct($client_email, $api_key) {
      $this->client_email = $client_email;
      $this->api_key = $api_key;
  }

  public function perform_checkout($params) {

      $access_token = $this->get_access_token();

      $result = TotalCoinClient::post("Checkout/" . $access_token, $params);

      return $result;
  }

  public function get_access_token() {
      $app_client_values = Array(
        'Email' => $this->client_email,
        'ApiKey' => $this->api_key,
      );

      $access_data = TotalCoinClient::post("Security", $app_client_values);

      return $access_data['Response']['TokenId'];
  }

  public function get_ipn_info($reference_id) {
      $data = TotalCoinClient::get("Ipn/" . $this->api_key . "/" . $reference_id);

      return $data;
  }

}

class TotalCoinClient {

  const API_BASE_URL = "https://api.totalcoin.com/ar/";

  private static function get_connect($uri = '', $method = 'GET', $content_type = 'application/json') {
      $connect = curl_init(self::API_BASE_URL . $uri);

      curl_setopt($connect, CURLOPT_USERAGENT, "TotalCoin PHP v1");
      curl_setopt($connect, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($connect, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($connect, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($connect, CURLOPT_HTTPHEADER, array("Accept: application/json", "Content-Type: " . $content_type));

      return $connect;
  }

  private static function set_data(&$connect, $data, $content_type) {
      if ($content_type == "application/json") {
        if (gettype($data) == "string") {
          json_decode($data, true);
        } else {
          $data = json_encode($data);
        }

        if(function_exists('json_last_error')) {
          $json_error = json_last_error();
          if ($json_error != JSON_ERROR_NONE) {
            throw new Exception("JSON Error [{$json_error}] - Data: {$data}");
          }
        }
      }

      curl_setopt($connect, CURLOPT_POSTFIELDS, $data);
  }

  private static function exec($method, $uri, $data, $content_type) {
      $connect = self::get_connect($uri, $method, $content_type);

      if ($data) {
        self::set_data($connect, $data, $content_type);
      }

      $api_result = curl_exec($connect);
      $api_http_code = curl_getinfo($connect, CURLINFO_HTTP_CODE);

      $response = array(
      "status" => $api_http_code,
      "response" => json_decode($api_result, true)
    );
      if ($response['status'] >= 400) {
        throw new Exception ('Error Interno', $response['status']);
      }

      curl_close($connect);

      return $response['response'];
  }

  public static function get($uri, $content_type = "application/json") {
      return self::exec("GET", $uri, null, $content_type);
  }

  public static function post($uri, $data, $content_type = "application/json") {
      return self::exec("POST", $uri, $data, $content_type);
  }

  public static function put($uri, $data, $content_type = "application/json") {
      return self::exec("PUT", $uri, $data, $content_type);
  }

}

class totalcoin {
    var $code, $title, $description, $enabled;

    function totalcoin() {
      global $order;

      $this->signature = 'totalcoin|1.0';
      $this->code = 'totalcoin';
      $this->title = "TotalCoin";
      $this->public_title = "TotalCoin";
      $this->description = "TotalCoin";
      $this->sort_order = MODULE_PAYMENT_TOTALCOIN_SORT_ORDER;
      $this->enabled = 1;
      //((MODULE_PAYMENT_TOTALCOIN_STATUS == 'True') ? true : false);

      /*
      orders_status_id 1 | orders_status_name Pending
      orders_status_id 2 | orders_status_name Processing
      orders_status_id 3 | orders_status_name Delivered
      */
      $this->order_status = 1;

      if (is_object($order))
          $this->update_status();
    }

    function after_process() {
        global $insert_id, $order;

        $data = array();
        $data['sucess'] = MODULE_PAYMENT_TOTALCOIN_SUCESS_URL;
        $data['pending'] = MODULE_PAYMENT_TOTALCOIN_PENDING_URL;
        $data['Email'] = MODULE_PAYMENT_TOTALCOIN_CLIENT_EMAIL;
        $data['ApiKey'] = MODULE_PAYMENT_TOTALCOIN_CLIENT_APIKEY;
        $data['Currency'] = "ARS";
        $data['Country'] = "ARG";
        $data['Reference'] = $insert_id;
        $data['Site'] = 'Oscommerce';
        $data['Quantity'] = 1;
        $data['MerchantId'] = MODULE_PAYMENT_TOTALCOIN_MERCHANTID;
        $data['PaymentMethods'] = MODULE_PAYMENT_TOTALCOIN_METHODS;
        $data['Amount'] = number_format($order->info['total'], 2, '.', '');

        $description = 'Nombre del Producto: ' . $order->products[0]['name'];
        $description .= ' - Cantidad: ' . $order->products[0]['qty'];
        $data['Description'] = $description;

        $tc = new TotalCoinAPI($data['Email'], $data['ApiKey']);
        $results = $tc->perform_checkout($data);
        if ($results['HasError']) {
            $url = '/';
            $content = 'Se ha producido un error Interno';
        } else {
            $url = $results['Response']['URL'];
        }

        tep_redirect(tep_href_link('totalcoin.php', 'url=' . urlencode($url)));
    }

    function addStatus($descStatus) {
        $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
        $status = tep_db_fetch_array($status_query);
        $status_id = $status['status_id'] + 1;
        $languages = tep_get_languages();

        foreach ($languages as $lang) {

            tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', '" . $descStatus . "')");
        }

        return $status_id;
    }

    function update_status() {
      return true;
    }

    function selection() {
    }

    function check() {
    }

    function install() {
        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, set_function, date_added)
        values
        ('Habilitar TotalCoin', 'MODULE_PAYMENT_TOTALCOIN_STATUS', 'False',
        'Desea aceptar pagos con TotalCoin?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, date_added)
        values
        ('Email', 'MODULE_PAYMENT_TOTALCOIN_CLIENT_EMAIL', '',
        'Tu login en TotalCoin', '6', '4', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, date_added)
        values
        ('Api Key', 'MODULE_PAYMENT_TOTALCOIN_CLIENT_APIKEY', '',
        'Tu Api Key de TotalCoin', '6', '4', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, date_added)
        values
        ('Url Exitosa', 'MODULE_PAYMENT_TOTALCOIN_SUCESS_URL', '',
        '', '6', '4', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, date_added)
        values
        ('Url de Pago Pendiente', 'MODULE_PAYMENT_TOTALCOIN_PENDING_URL', '',
        '', '6', '4', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, date_added)
        values ('Orden de aparición', 'MODULE_PAYMENT_TOTALCOIN_SORT_ORDER', '0',
        'Orden de aparición. El 0 se mostrará primero', '6', '0', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, set_function, date_added)
        values
        ('País', 'MODULE_PAYMENT_TOTALCOIN_COUNTRY','',
        '', '6', '0',
        'tep_cfg_select_option(array(\'argentina\'), ', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, set_function, date_added)
        values
        ('Moneda', 'MODULE_PAYMENT_TOTALCOIN_CURRENCY','',
        '', '6', '0',
        'tep_cfg_select_option(array(\'pesos\'), ', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, set_function, date_added)
        values
        ('Típo de checkout', 'MODULE_PAYMENT_TOTALCOIN_TYPE_CHECKOUT','',
        '', '6', '0',
        'tep_cfg_select_option(array(\'iframe\', \'redirect\'), ', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, date_added)
        values
        ('Métodos de Pago', 'MODULE_PAYMENT_TOTALCOIN_METHODS','TOTALCOIN',
        'Clave de Métodos de Pago separados por |, Existen 3 opciones disponibles (CREDITCARD, CASH y TOTALCOIN)
        Ejemplo: CREDITCARD|CASH|TOTALCOIN', '6', '4', now())");

        tep_db_query("insert into configuration
        (configuration_title, configuration_key, configuration_value, configuration_description,
        configuration_group_id, sort_order, date_added)
        values
        ('Merchant ID', 'MODULE_PAYMENT_TOTALCOIN_MERCHANTID', '',
        'Merchant ID de tu comercio', '6', '4', now())");
    }

    function remove() {
      tep_db_query("delete from configuration where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array(
      'MODULE_PAYMENT_TOTALCOIN_STATUS',
      'MODULE_PAYMENT_TOTALCOIN_CLIENT_EMAIL',
      'MODULE_PAYMENT_TOTALCOIN_CLIENT_APIKEY',
      'MODULE_PAYMENT_TOTALCOIN_SORT_ORDER',
      'MODULE_PAYMENT_TOTALCOIN_SUCESS_URL',
      'MODULE_PAYMENT_TOTALCOIN_PENDING_URL',
      'MODULE_PAYMENT_TOTALCOIN_COUNTRY',
      'MODULE_PAYMENT_TOTALCOIN_METHODS',
      'MODULE_PAYMENT_TOTALCOIN_CURRENCY',
      'MODULE_PAYMENT_TOTALCOIN_TYPE_CHECKOUT',
      'MODULE_PAYMENT_TOTALCOIN_MERCHANTID'
      );
    }

    function javascript_validation() {
      return false;
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
      return false;
    }

    function process_button() {
      return false;
    }

    function before_process() {
      return false;
    }

    function output_error() {
      return true;
    }

    function tc_get_last_order_status_from_transaction_history($transaction_histories) {
      $ordered_transaction_histories = Array();
      foreach ($transaction_histories as $transaction_history) {
        $date_created = date_create($transaction_history['Date']);
        $history = Array();
        $history['date'] = date_format($date_created, 'Y-m-d H:i:s');
        $history['status'] = $transaction_history['TransactionState'];
        $ordered_transaction_histories[] = $history;
      }

      if (count($ordered_transaction_histories) > 1) {
        usort($ordered_transaction_histories, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        $ordered_transaction_histories = end($ordered_transaction_histories);
        $last_status = $ordered_transaction_histories['status'];
      } else {
        $last_status = $ordered_transaction_histories[0]['status'];
      }

      return $last_status;
    }

    function tc_ipn_callback($reference_id, $merchant_id) {
      $tc = new TotalCoinAPI(MODULE_PAYMENT_TOTALCOIN_CLIENT_EMAIL, MODULE_PAYMENT_TOTALCOIN_CLIENT_APIKEY);
      $data = $tc->get_ipn_info($reference_id);
      if ($data['IsOk']) {
          $order_status = $this->tc_get_last_order_status_from_transaction_history($data['Response']['TransactionHistories']);
          $order_id = $data['Response']['MerchantReference'];
          switch ($order_status) {
              case 'Approved':
                  $status = 1;
                  $comments = 'La orden ha sido autorizada, se está esperando la liberación del pago.';
                  break;
              case 'Rejected':
                  $status = 1;
                  $comments = 'Pago rechazado por TotalCoin, contactar al cliente.';
                  break;
              case 'Available':
                  $status = 3;
                  $comments = 'La orden ha sido pagada. El dinero ya se encuentra disponible.';
                  break;
              default:
                  $status = 2;//In process
                  $comments = 'La orden está siendo procesada.';
                  break;
          }
          /*
          orders_status_id 1 | orders_status_name Pending
          orders_status_id 2 | orders_status_name Processing
          orders_status_id 3 | orders_status_name Delivered
          */
          $data = array('orders_status' => $status);
          tep_db_perform('orders', $data, 'update', "orders_id = '" . $order_id . "'");

          $data_history = array(
              'orders_id' => $order_id,
              'orders_status_id' => $status,
              'date_added' => 'now()',
              'customer_notified' => '0',
              'comments' => $comments);
          tep_db_perform('orders_status_history', $data_history);
      }
    }
}

?>
