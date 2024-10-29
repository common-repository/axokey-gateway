<?php
/**
 * Copyright 2020-2021 Axokey
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author François-Marie Faure <francois-marie.faure@axokey.com>
 */

if (!defined('ABSPATH')) {
  exit();
}
/**
 * Axokey Standard Payment Gateway
 *
 * Provides a Axokey Standard Payment Gateway.
 */
include_once ('includes/GatewayClient.php');

class WC_Gateway_Axokey extends WC_Payment_Gateway {

  /** @var boolean Whether or not logging is enabled */
  public static $log_enabled = false;

  /** @var WC_Logger Logger instance */
  public static $log = false;

  // Axokey Originator ID
  private $originator_id;
  // Axokey password
  private $password;
  // Axokey url to call the payment page
  private $connect2_url;
  // Axokey url to process refund
  private $api_url;

  // Merchant notifications settings
  private $merchant_notifications;
  private $merchant_notifications_to;
  private $merchant_notifications_lang;
  
  /**
   * Constructor for the gateway.
   */
  public function __construct() {
    $this->id = 'axokey';
    $this->has_fields = false;
    $this->method_title = __('Axokey', 'axokey');
    $this->method_description = __('Accept all major credit and debit cards payments', 'axokey');
    $this->supports = array('products', 'refunds');

    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user set variables
    $this->client_id = $this->get_option('client_id');
    $this->client_secret = $this->get_option('client_secret');

    $this->title = $this->get_option('title');
    $this->description = $this->get_option('description');
    $this->testmode = 'yes';
    /*$this->order_button_text = $this->get_option('pay_button');*/
    $this->debug = 'yes' === $this->get_option('debug', 'no');
    $this->api_url = $this->get_option('axokey_url');
    if($this->api_url) {
      $this->api_url .= (substr($this->api_url, -1) == '/' ? '' : '/');
    }
    $this->client_id = $this->get_option('API_Client_ID');
    $this->client_secret = $this->get_option('API_Client_Secret');


    $this->home_url = is_ssl() ? home_url('/', 'https') : home_url('/'); //set the urls (cancel or return) based on SSL
    $this->relay_response_url = add_query_arg('wc-api', 'WC_Gateway_Axokey_Return_Payment', $this->home_url);
    $this->webhook_url = add_query_arg('wc-api', 'WC_Gateway_Axokey_Webhook_result', $this->home_url);

    self::$log_enabled = $this->debug;

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

    if ($this->is_iframe_on()) {
      add_action('woocommerce_receipt_Axokey', array($this, 'receipt_page'));
    }

    add_action('woocommerce_api_wc_gateway_axokey_return_payment', array($this, 'handle_callback'));
    add_action('woocommerce_api_wc_gateway_axokey_webhook_result', array($this, 'webhook_result'));
  }

  public function updateOptions($options) {
      foreach($options as $key => $value) {
        WC_Settings_API::update_option($key, $value);
      }
  }

  /**
   * Logging method
   *
   * @param string $message
   */
  public static function log($message) {
    if (self::$log_enabled) {
      if (empty(self::$log)) {
        self::$log = new WC_Logger();
      }
      self::$log->add('Axokey', $message);
    }
  }

  /**
   * get_icon function.
   *
   * @return string
   */
  public function get_icon() {
    $icon_html = '';

    return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
  }

  /**
   * Check if this gateway is enabled and available in the user's country
   *
   * @return bool
   */
  public function is_valid_for_use() {
    // We allow to use the gateway from any where
    return true;
  }

  /**
   * Check if iframe mode is on
   *
   * @return bool
   */
  public function is_iframe_on() {
    // We allow to use the gateway from any where
    if ($this->get_option('iframe_mode') == 'yes') {
      return true;
    }
    return false;
  }

  /**
   * Admin Panel Options
   *
   * @since 1.0.0
   */
  public function admin_options() {
    if ($this->is_valid_for_use()) {
      parent::admin_options();
    } else {
      ?>
<div class="inline error">
 <p>
  <strong><?php _e( 'Gateway Disabled', 'axokey' ); ?></strong>: <?php _e( 'Axokey does not support your store currency / country', 'axokey' ); ?></p>
</div>
<?php
    }
  }

  /**
   * Initialize Gateway Settings Form Fields
   */
  public function init_form_fields() {
    $this->form_fields = array(
        'enabled' => array( /**/
            'title' => __('Enable/Disable', 'axokey'), /**/
            'type' => 'checkbox', /**/
            'label' => __('Enable Axokey payment gateway', 'axokey'), /**/
            'default' => 'yes' /**/
        ),
        'title' => array(/**/
            'title' => __('Title', 'axokey'), /**/
            'type' => 'text', /**/
            'description' => __('This controls the title the user sees during checkout.', 'axokey'), /**/
            'default' => __('Credit Card Payment via Axokey', 'axokey'), /**/
            'desc_tip' => true /**/
        ),
        'pay_button' => array(/**/
            'title' => __('Pay Button', 'axokey'), /**/
            'type' => 'text', /**/
            'description' => __('"Pay Button" text', 'axokey'), /**/
            'default' => __('Proceed to Axokey', 'axokey') /**/
        ),
        'description' => array(/**/
            'title' => __('Description', 'axokey'), /**/
            'type' => 'text', /**/
            'desc_tip' => true, /**/
            'description' => __('This controls the description the user sees during checkout.', 'axokey'), /**/
            'default' => __('Pay via Axokey: you can pay with your credit / debit card', 'axokey') /**/
        ),
        'API_Client_ID' => array(/**/
            'title' => __('API Client ID', 'axokey'), /**/
            'type' => 'text', /**/
            'description' => __('Your API Client ID. You can find it on the backoffice of Axokey', 'axokey'), /**/
        ),
        'API_Client_Secret' => array(/**/
            'title' => __('API Client Secret', 'axokey'), /**/
            'type' => 'text', /**/
            'description' => __('Your API Client Secret. You can find it on the backoffice of Axokey', 'axokey'), /**/
        ),
        'axokey_url' => array(/**/
            'title' => __('Payment Gateway URL', 'axokey'), /**/
            'type' => 'text', /**/
        ),
        'debug' => array(/**/
            'title' => __('Debug Log', 'axokey'), /**/
            'type' => 'checkbox', /**/
            'label' => __('Enable logging', 'axokey'), /**/
            'default' => 'no', /**/
            'description' => __('Log Axokey events, such as Callback', 'axokey') /**/
        )
    );
  }

  public function getCompanyDetails()
  {
    $client = new AxokeyGatewayTransaction($this->api_url, $this->client_id, $this->client_secret);
    return $client->getCompanyDetails();
  }

  /**
   * Process the payment and return the result
   *
   * @param int $order_id
   * @return array
   */
  public function process_payment($order_id) {
    $order = new WC_Order($order_id);

    $client = new AxokeyGatewayTransaction($this->api_url, $this->client_id, $this->client_secret);
    $products = [];
    foreach($order->get_items() as $product) {
      $temp = [
        'name' => $product->get_name(),
        'unitPrice' => $product->get_total() / $product->get_quantity() * 100,
        'qty' => $product->get_quantity()
      ];
      $products[] = $temp;
    }

    $response = $client->makeDirectPayment($products, [
      'returnUrl' => $this->relay_response_url . '&order_id=' . $order_id,
      'webhookUrl' => $this->webhook_url
    ]);

    // Save the merchant token for callback verification
    update_post_meta($order_id, '_axokey_cart_id', $response->cartId);

    return array('result' => 'success', 'redirect' => $response->paymentURL);
  }

  public function webhook_result()
  {
    $my_query = new WP_Query( 
      array(
        'post_type' => 'shop_order', 
        'meta_key' => '_axokey_cart_id', 
        'meta_value' => sanitize_text_field($_GET['cart_id']), 
        'post_status'   => 'wc_pending'
      )
    );
    $order_id = null;
    while ( $my_query->have_posts() ) {
        $my_query->next_post();
        $order_id = $my_query->post->ID;
        break;
    }
    $order = new WC_Order($order_id);

    $client = new AxokeyGatewayTransaction($this->api_url, $this->client_id, $this->client_secret);
    $response = $client->getCartDetails(sanitize_text_field($_GET['cart_id']));
    if(!$response->isPayed) {
      $order->update_status( 'cancelled', 'Order Cancelled for the following raison: Order Not payed' );
      exit;
    }

    $message = 'Order ' . $order_id . ' successfully payed!';
    $this->payment_complete($order, $response->transaction->transactionID, $message, 'axokey');
    $order->update_status('completed', $message);
    $this->log($message);
    exit;
  }

  /**
   * Can the order be refunded via PayPal?
   *
   * @param WC_Order $order
   * @return bool
   */
  public function can_refund_order($order) {
    return $order && $order->get_transaction_id();
  }

  /**
   * Process a refund if supported
   *
   * @param int $order_id
   * @param float $amount
   * @param string $reason
   * @return boolean True or false based on success, or a WP_Error object
   */
  public function process_refund($order_id, $amount = null, $reason = '') {
  }

  /**
   * Complete order, add transaction ID and note
   *
   * @param WC_Order $order
   * @param string $txn_id
   * @param string $note
   */
  protected function payment_complete($order, $txn_id = '', $note = '') {
    $order->add_order_note($note);
    $order->payment_complete($txn_id);
  }

  /**
   * Check for Axokey Callback Response
   */
  public function handle_callback() {

    $order_id = sanitize_text_field($_GET['order_id']);
    $order = new WC_Order($order_id);
    $transactionId = get_post_meta($order_id, '_transaction_id', true);
    $message = "Successful transaction by customer redirection. Transaction Id: " . $transactionId;
    $this->log($message);
    $this->redirect_to($order->get_checkout_order_received_url());

    exit;
  }

  public function receipt_page($order_id) {

      //define the url
      $Axokey_customer_url = get_post_meta($order_id, '_Axokey_customer_url', true);

      //display the form
      ?>
      <iframe id="Axokey_for_woocommerce_iframe" src="<?php echo $Axokey_customer_url; ?>" width="100%" height="700" scrolling="no" frameborder="0" border="0" allowtransparency="true"></iframe>

      <?php
  }

  public function redirect_to($redirect_url) {
      // Clean
      @ob_clean();

      // Header
      header('HTTP/1.1 200 OK');

      echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
      
      exit;
  }
}
