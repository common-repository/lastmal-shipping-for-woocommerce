<?php

/**
 * Plugin Name: Lastmal Shipping for Woocommerce
 * Plugin URI: https://lastmal.com
 * Version: 0.8.19
 * Author: Lastmal Technologies Ltd
 * Author URI: https://lastmal.com
 * Developer: Lastmal
 * Developer URI: https://lastmal.com
 * Description: Lastmal helps businesses to complete shipping and delivery activities simply and efficiently anywhere within West Africa with one integration.
 * Text Domain: lastmal-shipping
 *
 * WC requires at least: 2.2
 * WC tested up to: 8.0.3
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * prefix: LMSFW
 */


if (!defined('ABSPATH')) exit;

if (!defined('WPINC')) exit;

/*
* Check if WooCommerce is active
*/
if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    global $API_KEY, $API_URL;

    $API_KEY = '';
    $API_URL = 'https://app.lastmal.com/api';
    function lastmal_shipping_method()
    {
        global $API_KEY;

        if (!class_exists('Lastmal_Shipping_Method')) {

            class Lastmal_Shipping_Method extends WC_Shipping_Method
            {
                /**
                 * Lastmal API Instance
                 *
                 * @var null
                 */
                private $api = null;
                private $URL = "https://app.lastmal.com/api";

                /**

                 * Constructor for your shipping class

                 *

                 * @access public

                 * @return void

                 */

                public function __construct()
                {
                    $this->id                 = 'lastmal';
                    // $this->instance_id                 = 'lastmal';
                    $this->method_title       = __('Lastmal Shipping', 'lastmal');
                    $this->method_description = __('Lastmal helps businesses to complete shipping and delivery activities simply and efficiently anywhere within West Africa with one integration.', 'lastmal');

                    // Supports
                    // $this->supports             = array(
                    //     'shipping-zones',
                    //     'instance-settings',
                    //     'instance-settings-modal',
                    // );

                    // Availability & Countries
                    $this->availability = 'including';
                    $this->countries = array('GH');

                    $this->init();
                }

                /**
                 * Init your settings
                 *
                 * @access public

                 * @return void

                 */

                function init()
                {
                    // Load the settings API
                    $this->init_form_fields();

                    $this->init_settings();
                    $this->enabled = ($this->get_option('api_key') != '' &&
                        $this->get_option('api_key') != null &&
                        $this->get_option('is_enabled') == 'yes'
                    ) ? 'yes' : 'no';
                    $this->is_enabled = isset($this->settings['is_enabled']) ? $this->settings['is_enabled'] : 'no';
                    $this->handle_payment = isset($this->settings['handle_payment']) ? $this->settings['handle_payment'] : 'yes';
                    $this->disable_urgent = 'no';

                    $this->title = 'Lastmal Shipping';
                    $this->api_key = isset($this->settings['api_key']) ? $this->settings['api_key'] : null;
                    $this->wc_key = isset($this->settings['wc_key']) ? $this->settings['wc_key'] : null;
                    $this->wc_secret = isset($this->settings['wc_secret']) ? $this->settings['wc_secret'] : null;
                    $this->hangUp = false;
                    $this->siteUrl = get_option('siteurl');

                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

                    require_once 'includes/api/class-lastmal-api.php';

                    $this->LMSFW_api();
                    $this->LMSFW_init_inventory_sync();
                }

                /**
                 * @return null|Lastmal_API
                 */
                public function LMSFW_api()
                {
                    if (is_object($this->api)) {
                        return $this->api;
                    }

                    $apiOption = [
                        'api_key' => $this->settings['api_key'],
                    ];

                    $this->api = new LastmalAPI($apiOption);

                    return $this->api;
                }

                // Initialise Inventory Sync
                public function LMSFW_init_inventory_sync()
                {
                    LMSFW_log('Inventory Sync Started');
                    if (!$this->hangUp && $this->settings['wc_key'] != null && $this->settings['wc_secret'] != null) {
                        $this->LMSFW_api()->connectWC(['key' => $this->settings['wc_key'], 'secret' => $this->settings['wc_secret'], 'url' => $this->siteUrl]);
                    }
                    $this->hangUp = true;
                }

                /** 

                 * Define settings field for this shipping

                 * @return void

                 */

                function init_form_fields()
                {
                    $this->form_fields = array(
                        'is_enabled' => array(
                            'title' => __('Enable', 'lastmal'),
                            'type' => 'checkbox',
                            'description' => __(
                                'Enable Lastmal Shipping',
                                'lastmal'
                            ),
                            'default' => 'no'
                        ),
                        'api_key' => array(
                            'title' => __('API Key', 'lastmal'),
                            'type' => 'text',
                            'description' => __(
                                'Obtain API key from your Lastmal DIP account',
                                'lastmal'
                            ),
                            'default' => null
                        ),
                        'wc_key' => array(
                            'title' => __('WC Consumer Key', 'lastmal'),
                            'type' => 'text',
                            'description' => __(
                                'Consumer Key for WooCommerce',
                                'lastmal'
                            ),
                            'default' => null
                        ),
                        'wc_secret' => array(
                            'title' => __('WC Consumer Secret', 'lastmal'),
                            'type' => 'text',
                            'description' => __(
                                'Consumer Secret for WooCommerce',
                                'lastmal'
                            ),
                            'default' => null
                        ),
                        'handle_payment' => array(
                            'title' => __('Add Payment Method', 'lastmal'),
                            'type' => 'checkbox',
                            'description' => __(
                                'By checking this box, you can easily receive payments from your buyers via this integration.',
                                'lastmal'
                            ),
                            'default' => 'yes'
                        ),
                    );
                }

                function generate_sku($name)
                {
                    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $salt = '';
                    for ($i = 0; $i < 5; $i++) {
                        $salt .= $characters[rand(0, strlen($characters) - 1)];
                    }
                    return str_replace(" ", "-", strtolower($name)) . '_' . $salt;
                }

                /**
                 * Converts an amount from Ghanaian Cedis (GHS) to US Dollars (USD) using an external currency exchange API.
                 *
                 * This function fetches the current exchange rate from the ExchangeRate-API and uses it to convert
                 * the provided amount in Cedis to Dollars. The result is formatted to 2 decimal places.
                 *
                 * @access public
                 * @param float $amountInCedis The amount in Ghanaian Cedis to be converted.
                 * @return string|null The converted amount in US Dollars formatted to 2 decimal places, or null if the conversion fails.
                 */
                function convertCedisToDollars($amountInCedis) {
                    $apiKey = '2094aa833b58234c046cc7fd';
                    $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/pair/GHS/USD";
        
                    // Initialize cURL session
                    $ch = curl_init();
        
                    // Set cURL options
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
                    // Execute the API request
                    $response = curl_exec($ch);
        
                    // Close the cURL session
                    curl_close($ch);
        
                    // Decode the JSON response
                    $data = json_decode($response, true);
        
                    // Check if the API call was successful
                    if ($data['result'] == 'success') {
                        $conversionRate = $data['conversion_rate'];
        
                        // Convert the amount using the fetched conversion rate
                        $amountInDollars = $amountInCedis * $conversionRate;
        
                        // Format the amount to 2 decimal places
                        return number_format($amountInDollars, 2, '.', '');
                    } else {
                        // Handle the error (returning null or an error message)
                        return null;
                    }
                }

                /**
                 * This function is used to calculate the shipping cost. Within this function, we can check for weights, dimensions, and other parameters.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping($package = array())
                {
                    error_log('Lastmal Shipping: Running calculate_shipping()');
                    $destination = $package['destination'];
                    $contents = $package['contents'];
                    $destination['sku'] = [];

                    $totalWeight = 0;
                    foreach ($contents as $key => $item) {
                        $qty = $item['quantity'];
                        $data = $item['data'];

                        $totalWeight += $qty * floatval($data->get_weight() ?? 0.5);

                        $pId = $item['product_id'];
                        $product = wc_get_product($pId);
                        $pSku = $product->get_sku();

                        if (empty($pSku)) {
                            $pSku = $this->generate_sku($product->get_title());
                            $product->set_sku($pSku);
                            $product->save();
                        }

                        // $sku = $data->sku . '-' . $pId ;
                        $sku = $pSku . '-' . $pId;
                        array_push($destination['sku'], $sku);
                    }

                    //prevent rate fetch on empty sku -> indicator for non-tangible goods
                    if (!isset($destination['sku']) || sizeof($destination['sku']) == 0) {
                        return;
                    }

                    // Prevent re-fetching rate on place order
                    if (!isset($package['serviceType']) && !empty(WC()->session->get('ORIGINAL_RATE'))) {
                        $package['serviceType'] = WC()->session->get('ORIGINAL_SERVICE_TYPE');
                    }

                    $serviceType = isset($package['serviceType']) ? $package['serviceType'] : 'Other';
                    $orderAmount = isset($package['orderAmount']) ? $package['orderAmount'] : $package['cart_subtotal'];

                    $destination['weight'] = $totalWeight == 0 ? 1 : $totalWeight;
                    $destination['serviceType'] = $serviceType;

                    $isValidArr = true;

                    WC()->session->set('ORIGINAL_SERVICE_TYPE', $serviceType);

                    // check full details
                    foreach ($destination as $key => $value) {
                        if ($key != 'address_2' && $key != 'postcode' && empty($destination[$key])) {
                            $isValidArr = false;
                        }
                    }
                    $destination['orderAmount'] = $orderAmount;

                    wc_print_notices();

                    // if ($destination['address'] == '' || $destination['address'] == null || empty($destination['address'])) {
                    if (!$isValidArr) {
                        if (is_checkout()) {
                            error_log('Lastmal Shipping: Invalid Address or Product Details');
                            // wc_add_notice('Lastmal Shipping: Invalid Address or Product Details!', 'error');

                            return false;
                        }
                    } else {
                        $API = $this->LMSFW_api();
                        $shippingResponse = $API->getRate($destination);

                        if (!$this->api_key) {
                            error_log('Lastmal Shipping not configured properly!');
                            wc_add_notice('Lastmal Shipping not configured properly!', 'error');
                            return false;
                        } else {
                            if ($shippingResponse->status == 201) {
                                WC()->session->set('ORIGINAL_RATE', $shippingResponse->originalRate);

                                if ($shippingResponse->rate == '0') {
                                    WC()->session->set('IS_FREE', true);
                                } else {
                                    WC()->session->set('IS_FREE', false);
                                }

                                $default_currency = get_option('woocommerce_currency');

								if ($default_currency == "USD") {
									$convertedRate = $this->convertCedisToDollars($shippingResponse->rate);
									$shippingRate = $convertedRate;
								} else {
									$shippingRate = $shippingResponse->rate;
								}

								$rate = array(
									'id' => $this->id,
									'label' => $this->title,
									'cost' => $shippingRate,
								);

								$this->add_rate($rate);
								$this->rate = $rate;
                            } else {
                                if ($shippingResponse->status == 500) {
                                    wc_add_notice('Lastmal Shipping not configured properly!', 'error');
                                } else {
                                    wc_add_notice('Out of service area!', 'error');
                                }
                            }
                        }
                    }
                }

                function LMSFW_handleWebhooks()
                {
                    $nameList = [
                        'Lastmal order.created',
                        'Lastmal order.updated', 'Lastmal order.deleted',
                        'Lastmal product.created', 'Lastmal product.updated', 'Lastmal customer.created'
                    ];
                    if ($this->settings['api_key'] != null && $this->settings['is_enabled'] == true) {
                        $data_store = \WC_Data_Store::load('webhook');
                        $webhooks   = $data_store->search_webhooks(['status' => 'active', 'paginate' => true]);
                        $_items     = array_map('wc_get_webhook', $webhooks->webhooks);

                        $hooksList = [];
                        foreach ($_items as $_item) {
                            $hooksList[] = [
                                'id'            => $_item->get_id(),
                                'name'          => $_item->get_name(),
                                'user'          => $_item->get_user_id(),
                                'topic'         => $_item->get_topic(),
                                'delivery_url'  => $_item->get_delivery_url(),
                                'secret'        => $_item->get_secret(),
                                'status'        => $_item->get_status(),
                            ];
                        }

                        foreach ($nameList as $hook) {
                            $found = false;
                            foreach ($hooksList as $hookObj) {
                                if ($hook == $hookObj['name']) {
                                    $found = true;

                                    $wh = new WC_Webhook();
                                    $wh->set_id($hookObj['id']);
                                    $wh->set_name($hookObj['name']);
                                    $wh->set_user_id($hookObj['user']);
                                    $wh->set_topic($hookObj['topic']);
                                    $wh->set_secret($this->api_key);
                                    $wh->set_delivery_url($hookObj['delivery_url']);
                                    $wh->set_status($hookObj['status']);
                                    $wh->set_secret($this->settings['api_key']);
                                    $save = $wh->save();
                                }
                            }

                            if (!$found) {
                                $this->LMSFW_createWebhooks($hook);
                            }
                        }
                    }
                }

                function LMSFW_createWebhooks($name)
                {
                    $user = wp_get_current_user();
                    $userID = (int) $user->ID;
                    $deliveryURL = $this->URL . "/stores/hook-wydcmqioxcut";
                    $split = explode(" ", $name);
                    $topic = $split[1];

                    $webhook = new WC_Webhook();
                    $webhook->set_name($name);
                    $webhook->set_user_id($userID); // User ID used while generating the webhook payload.
                    $webhook->set_topic($topic); // Event used to trigger a webhook.
                    $webhook->set_secret($this->api_key); // Secret to validate webhook when received.
                    $webhook->set_delivery_url($deliveryURL); // URL where webhook should be sent.
                    $webhook->set_status('active'); // Webhook status.
                    $save = $webhook->save();
                }

                /**
                 * Check if settings are not empty
                 */
                public function admin_options()
                {
                    $this->LMSFW_handleWebhooks();

                    // Check users environment supports this method
                    $this->LMSFW_environment_check();

                    // Show settings
                    parent::admin_options();
                }

                /**
                 * Show error in case of config missing
                 */
                private function LMSFW_environment_check()
                {
                    if (
                        (!$this->api_key) &&
                        $this->is_enabled == 'yes' &&
                        $this->enabled
                    ) {
                        $this->add_error("This is an error!");
                        echo '<div class="error">
                            <p>' .
                            __(
                                'You need to specify the API Key in order to use this plugin',
                                'lastmal'
                            ) .
                            '</p>
                        </div>';
                    }
                }
            }
        }

        $WC_Lastmal_Shipping_Method = new Lastmal_Shipping_Method();
        $currentAPI = $WC_Lastmal_Shipping_Method->get_option('api_key');
        $handlePayment = $WC_Lastmal_Shipping_Method->get_option('handle_payment');

        if ($handlePayment == 'yes') {
            add_filter('woocommerce_cart_needs_payment', '__return_false');
        }

        $API_KEY = $currentAPI;

        // Save custom fields to order meta data
        function LMSFW_update_order_meta($order_id)
        {
            global $API_KEY, $API_URL;

            $order = wc_get_order($order_id);
            $selected_payment = WC()->session->get('chosen_payment_method');
            $selected_shipping = $order->get_shipping_method();
            $originalRate = WC()->session->get('ORIGINAL_RATE');
            $isFree = WC()->session->get('IS_FREE');

            $WC_Lastmal_Shipping_Method = new Lastmal_Shipping_Method();
            $handlePayment = $WC_Lastmal_Shipping_Method->get_option('handle_payment');

            if (!empty($_POST['service_type'])) {
                // update_post_meta($order_id, 'service_type', sanitize_text_field($_POST['service_type']));

                // HPOS Compatibility
                $order->update_meta_data('service_type', sanitize_text_field($_POST['service_type']));
            }

            // if ($isFree == true) {
            //     foreach ($order->get_items('shipping') as $item_id => $item) {
            //         $item->set_method_title('Lastmal Shipping');
            //         $item->set_method_id('lastmal'); // set an existing Shipping method rate ID
            //         $item->set_total(0);

            //         $item->save();
            //     }

            //     $order->calculate_totals(); // the save() method is included
            // }

            if ($selected_shipping == null) {
                $order->update_meta_data('virtual_order', TRUE);
            }

            if (
                $handlePayment == 'yes'
                && ($selected_shipping == 'Lastmal Shipping' || $selected_shipping == null)
            ) {
                // Payload to generate payment links
                $payload = array(
                    "orderid" => $order_id,
                    "key" => $API_KEY,
                    "totalCost" => $order->get_total(),
                    "shipping" => $selected_shipping == null ? 0 : $order->get_shipping_total(),
                    "itemsCost" => $order->get_subtotal(),
                    "return_url" => $order->get_checkout_order_received_url(),
                );
                // LMSFW_log($payload);
                $response = wp_remote_post($API_URL . '/stores/hook-payment-custom', array(
                    'method'    => 'POST',
                    'body'      => http_build_query($payload),
                    'timeout'   => 90,
                    'sslverify' => false,
                ));

                // Retrieve the body's response if no errors found
                $response_body = json_decode(wp_remote_retrieve_body($response));
                if ($response_body->status == 201) {
                    // update_post_meta($order_id, 'paymentMode', 'Pay with LM');
                    // update_post_meta($order_id, 'paymentUrl', $response_body->url);
                    // update_post_meta($order_id, 'paymentRef', $response_body->ref);

                    // HPOS Compatibility
                    $order->update_meta_data('paymentMode', 'Pay with LM');
                    $order->update_meta_data('paymentUrl', $response_body->url);
                    $order->update_meta_data('paymentRef', $response_body->ref);
                }
            }

            // update_post_meta($order_id, 'vendor', $API_KEY);
            // update_post_meta($order_id, 'originalRate', $originalRate);
            // update_post_meta($order_id, 'isFree', $isFree);

            // HPOS Compatibility
            $order->update_meta_data('vendor', $API_KEY);
            $order->update_meta_data('originalRate', $originalRate);
            $order->update_meta_data('isFree', $isFree);

            $order->save();
        }
        function LMSFW_BLK_update_order_meta($order)
        {
            global $API_KEY, $API_URL;
            $selected_payment = WC()->session->get('chosen_payment_method');
            $selected_shipping = $order->get_shipping_method();
            $originalRate = WC()->session->get('ORIGINAL_RATE');
            $isFree = WC()->session->get('IS_FREE');
            $order_id = $order->get_id();

            $WC_Lastmal_Shipping_Method = new Lastmal_Shipping_Method();
            $handlePayment = $WC_Lastmal_Shipping_Method->get_option('handle_payment');

            if (!empty($_POST['service_type'])) {
                // HPOS Compatibility
                $order->update_meta_data('service_type', sanitize_text_field($_POST['service_type']));
            }

            if ($selected_shipping == null) {
                $order->update_meta_data('virtual_order', TRUE);
            }

            if (
                $handlePayment == 'yes'
                && ($selected_shipping == 'Lastmal Shipping' || $selected_shipping == null)
            ) {
                // Payload to generate payment links
                $payload = array(
                    "orderid" => $order_id,
                    "key" => $API_KEY,
                    "totalCost" => $order->get_total(),
                    "shipping" => $selected_shipping == null ? 0 : $order->get_shipping_total(),
                    "itemsCost" => $order->get_subtotal(),
                    "return_url" => $order->get_checkout_order_received_url(),
                );
                // LMSFW_log($payload);
                $response = wp_remote_post($API_URL . '/stores/hook-payment-custom', array(
                    'method'    => 'POST',
                    'body'      => http_build_query($payload),
                    'timeout'   => 90,
                    'sslverify' => false,
                ));

                // Retrieve the body's response if no errors found
                $response_body = json_decode(wp_remote_retrieve_body($response));
                if ($response_body->status == 201) {
                    // HPOS Compatibility
                    $order->update_meta_data('paymentMode', 'Pay with LM');
                    $order->update_meta_data('paymentUrl', $response_body->url);
                    $order->update_meta_data('paymentRef', $response_body->ref);
                }
            }

            $order->update_meta_data('vendor', $API_KEY);
            $order->update_meta_data('originalRate', $originalRate);
            $order->update_meta_data('isFree', $isFree);

            $order->save();
        }
        add_action('woocommerce_checkout_update_order_meta', 'LMSFW_update_order_meta', 30, 1);
        add_action('woocommerce_store_api_checkout_update_order_meta', 'LMSFW_BLK_update_order_meta', 30, 1);

        add_action('woocommerce_thankyou', 'LMSFW_custom_redirection');
        function LMSFW_custom_redirection($order_id)
        {
            $WC_Lastmal_Shipping_Method = new Lastmal_Shipping_Method();
            $handlePayment = $WC_Lastmal_Shipping_Method->get_option('handle_payment');

            if ($handlePayment == 'yes') {
                $order = wc_get_order($order_id);
                $url = 'https://www.lastmal.com';

                $status = $order->get_status();
                $selected_payment_method_id = WC()->session->get('chosen_payment_method');
                $selected_shipping = $order->get_shipping_method();

                if (
                    !$order->has_status('failed') && $status == 'processing'
                    && ($selected_payment_method_id == 'cod' || $selected_payment_method_id == null)
                    && ($selected_shipping == 'Lastmal Shipping' || $selected_shipping == null)
                ) {
                    $order->set_status('pending');
                    $order->save();

                    $url = $order->get_meta('paymentUrl');

                    wp_redirect($url);
                    exit;
                }
            }
        }

        add_action('woocommerce_after_checkout_billing_form', 'LMSFW_add_service_type_field');
        function LMSFW_add_service_type_field($checkout)
        {
            global $API_KEY;
            $apiOption = [
                'api_key' => $API_KEY,
            ];
            $theAPI = new LastmalAPI($apiOption);
            $configResponse = $theAPI->getConfig();
            $gotConfig = false;
            if ($configResponse != null && $configResponse->status == 201) {
                $gotConfig = true;
                $options = [];
                foreach ($configResponse->config as $key => $value) {
                    $options[$value] = $value;
                }
            } else {
                $options = array(
                    'Urgent' => 'Urgent',
                    'Same Day' => 'Same Day',
                    'Next Day' => 'Next Day',
                    'Other' => 'Other',
                );
            }

            woocommerce_form_field('service_type', array(
                'type'          => 'select', // text, textarea, select, radio, checkbox, password, about custom validation a little later
                'required'    => true, // actually this parameter just adds "*" to the field
                'class'         => array('form-row-wide', 'address-field', 'update_totals_on_change', 'lm-shipping-option', 'hide-field'),
                'label'         => 'Lastmal Shipping Options',
                'label_class'   => 'lm-service-label',
                'options'    => $options,
                'default'   => $gotConfig ? $configResponse->config[count($configResponse->config) - 1] : 'Other'
            ), $checkout->get_value('service_type'));
        }

        function LMSFW_css_styles()
        {
            global $API_URL, $API_KEY;
            $cart_total_weight = WC()->cart->get_cart_contents_weight();
            $isVirtualProduct = 0;
            $sub = 0;
            foreach (WC()->cart->get_cart() as $cart_item) {
                $sub += $cart_item['line_subtotal'];
                $_product =  wc_get_product($cart_item['data']->get_id());
                if ($_product->is_virtual()) {
                    $isVirtualProduct = 1;
                }
            }
            if (is_checkout() == true) {
?>
                <style>
                    .lm-shipping-option select {
                        width: -webkit-fill-available !important;
                        height: 40px;
                        border-radius: 5px;
                    }

                    .hide-field {
                        display: none !important;
                    }

                    .lastmal_brand_section {
                        border: 1px solid #e9e9e9;
                        margin-top: 10px;
                        border-radius: 5px;
                    }

                    .brand_heading {
                        border-bottom: 1px solid #e9e9e9;
                        margin-bottom: 5px;
                        padding-left: 1em;
                        padding-left: 1em;
                        padding-top: 5px;
                        padding-bottom: 5px;
                        background-color: #212121;
                        border-top-left-radius: 5px;
                        border-top-right-radius: 5px;
                    }

                    .brand_heading p {
                        color: white !important;
                        margin-bottom: 5px;
                        font-size: 1em;
                    }

                    #delivery_list_box {
                        padding-left: 20px;
                        padding-right: 20px;
                        padding-top: 0.6em;
                        /* margin-left: 20px; */
                        text-align: left;
                    }

                    .delivery_rate_item {
                        display: flex;
                        align-items: center;
                        justify-content: start;
                    }

                    .rate_service_type {
                        color: #009900;
                        flex: auto;
                        margin-bottom: 0;
                        padding-left: 10px;
                    }

                    .brand_footer {
                        display: flex;
                        margin-top: 10px;
                        padding: 0.5em 1em;
                        align-items: center !important;
                        background-color: #f9f9f9;
                        border-top: 1px solid #e9e9e9;
                    }
                </style>
                <script>
                    jQuery(function($) {
                        // woocommerce_params is required
                        if (typeof woocommerce_params === "undefined") {
                            return false;
                        }

                        $(document).ready(function() {
                            if ($("#shipping_method_0_lastmal").length > 0) {
                                $("#lastmal_brand_section").removeClass('hide-field');
                                setTimeout(() => {
                                    $('form.checkout .woocommerce-billing-fields').trigger('change');
                                }, 1500);
                            }

                            $('form.checkout').on('change', 'input[name^="shipping_method"]', function() {
                                if ($('input[name^="shipping_method"]:checked').val() == 'lastmal') {
                                    $("#lastmal_brand_section").removeClass('hide-field');
                                } else {
                                    $("#lastmal_brand_section").addClass('hide-field');
                                }
                            });

                            $('form.checkout').on('change', '.woocommerce-billing-fields,.woocommerce-shipping-fields', function() {
                                var url = '<?= $API_URL; ?>' + '/stores/get-rate';
                                var payload;
                                var isValidObj = true;

                                if ($('input[name^="ship_to_different_address"]').is(':checked')) {
                                    payload = {
                                        address: $('#shipping_address_1').val(),
                                        address_1: $('#shipping_address_1').val(),
                                        country: $('#shipping_country').val(),
                                        state: $('#shipping_state').val(),
                                        city: $('#shipping_city').val(),
                                        weight: '<?= $cart_total_weight; ?>',
                                    };
                                } else {
                                    payload = {
                                        address: $('#billing_address_1').val(),
                                        address_1: $('#billing_address_1').val(),
                                        country: $('#billing_country').val(),
                                        state: $('#billing_state').val(),
                                        city: $('#billing_city').val(),
                                        weight: '<?= $cart_total_weight; ?>',
                                    };
                                }

                                // set serviceType
                                if ($('input[name="lm_radio_service_type"]').length > 0) {
                                    payload.serviceType = $('input[name^="lm_radio_service_type"]:checked').val();
                                } else {
                                    payload.serviceType = 'default';
                                }

                                // add cart total
                                payload.orderAmount = '<?= $sub; ?>';

                                // check virtual products
                                payload.isVirtual = '<?= $isVirtualProduct; ?>';

                                // check valid
                                for (var key in payload) {
                                    if (payload[key] === "") {
                                        isValidObj = false;
                                    }
                                }

                                // console.log('ship to bill -> ', payload);
                                if (isValidObj && payload.isVirtual == 0) {
                                    $("#lastmal_brand_section").removeClass('hide-field');
                                    var request = $.ajax({
                                        url: url,
                                        method: "POST",
                                        data: payload,
                                        dataType: "json",
                                        headers: {
                                            'Authorization': 'Bearer ' + '<?= $API_KEY; ?>',
                                        }
                                    });

                                    request.done(function(data) {
                                        // console.log("Data -> ", data);
                                        localStorage.setItem('rates_list', JSON.stringify(data.deliveryList));
                                        localStorage.setItem('service_type', data.serviceType);
                                        $('#lastmal_brand_section').trigger('refresh');
                                    });

                                    request.fail(function(jqXHR, textStatus) {
                                        console.log("Error -> " + textStatus);
                                    });
                                } else {
                                    $("#lastmal_brand_section").addClass('hide-field');
                                }
                            });

                            $('#lastmal_brand_section').on('refresh', function() {
                                var rates = JSON.parse(localStorage.getItem('rates_list'));


                                var selectedType = localStorage.getItem('service_type');

                                if (rates.length) {
                                    $('#delivery_list_box').empty();
                                    rates.forEach(rate => {
                                        if (rate.isFree) {
                                            $('#delivery_list_box').append("<div class='delivery_rate_item'><input type='radio' id='" + rate.service + "' name='lm_radio_service_type' value='" + rate.service + "' > <label for='" + rate.service + "' class='rate_service_type'> " + rate.service + ', ' + rate.date + ": <span style='float:right;'> <span style='color: #999999; text-decoration:line-through;'> GH₵" + parseFloat(rate.cost).toFixed(2) + "</span> <span style='font-size: 0.8em;'>(Free Shipping)</span></span></label></div>");
                                        } else {
                                            $('#delivery_list_box').append("<div class='delivery_rate_item'><input type='radio' id='" + rate.service + "' name='lm_radio_service_type' value='" + rate.service + "' > <label for='" + rate.service + "' class='rate_service_type'> " + rate.service + ', ' + rate.date + ": <span style='color: #444; text-decoration:none;float:right;'> GH₵" + parseFloat(rate.cost).toFixed(2) + "</span></label></div>");
                                        }

                                        if (selectedType == rate.service) {
                                            $("input:radio[value='" + rate.service + "'][name='lm_radio_service_type']").prop('checked', true);
                                        }
                                    });
                                }
                            });

                            $("#lastmal_brand_section").on("change", "input[name='lm_radio_service_type']", function() {
                                var sel = $('input[name^="lm_radio_service_type"]:checked').val();
                                $('#service_type').val(sel).change();
                            });

                            // $('form.checkout #customer_details').trigger('change');
                        });
                    });
                </script>
            <?php
            }
        }
        add_action('wp_head', 'LMSFW_css_styles');

        function LMSFW_add_selected_service_type_to_package($array)
        {
            if (isset($_POST['post_data'])) {
                parse_str($_POST['post_data'], $params);
                $serviceType = sanitize_text_field($params['service_type']);

                $sub = 0;
                foreach (WC()->cart->get_cart() as $cart_item) {
                    $sub += $cart_item['line_subtotal'];
                }

                $array[0]['serviceType'] = $serviceType;
                $array[0]['orderAmount'] = $sub;
            }
            return $array;
        };
        add_filter('woocommerce_cart_shipping_packages', 'LMSFW_add_selected_service_type_to_package', 10, 1);

        // set Lastmal as default shipping
        function LMSFW_set_shipping_default()
        {
            if (isset(WC()->session) && !WC()->session->has_session())
                WC()->session->set_customer_session_cookie(true);

            if (WC()->session->get('chosen_shipping_methods') == null)
                return;

            if (count(WC()->session->get('chosen_shipping_methods'))  > 0 && strpos(WC()->session->get('chosen_shipping_methods')[0], 'lastmal') !== false)
                return;

            // Loop through shipping methods
            foreach (WC()->session->get('shipping_for_package_0')['rates'] as $key => $rate) {
                if ($rate->method_id === 'lastmal') {
                    WC()->session->set('chosen_shipping_methods', array($rate->id));
                    return;
                }
            }
        }
        add_action('woocommerce_before_checkout_form', 'LMSFW_set_shipping_default');

        function LMSFW_add_0_to_shipping_label($label, $method)
        {
            if (!($method->cost > 0)) {
                $label .= ': ' . wc_price(0);
            }

            return $label;
        }
        add_filter('woocommerce_cart_shipping_method_full_label', 'LMSFW_add_0_to_shipping_label', 10, 2);

        function LMSFW_sort_shipping_methods($rates, $package)
        {
            $all_free_rates = array();
            $sorted_rates = array();

            foreach ($rates as $rate_id => $rate) {
                if ('lastmal' === $rate->method_id) {
                    $sorted_rates[$rate_id] = $rate;
                    break;
                }
            }
            foreach ($rates as $rate_id => $rate) {
                if ('lastmal' !== $rate->method_id) {
                    $sorted_rates[$rate_id] = $rate;
                }
            }

            if (empty($all_free_rates)) {
                return $sorted_rates;
            } else {
                return $all_free_rates;
            }
        }
        add_filter('woocommerce_package_rates', 'LMSFW_sort_shipping_methods', 9999, 2);

        // Add branding info to checkout page
        function LMSFW_add_branding()
        {
            ?>
            <div id="lastmal_brand_section" class="lastmal_brand_section hide-field">
                <form>
                    <div class="brand_heading">
                        <p>Lastmal Shipping - Choose a delivery option:</p>
                    </div>
                    <div id="delivery_list_box" class="update_totals_on_changes">
                        <!-- <script>
                            jQuery(function($) {
                                $('#lastmal_brand_section').on('refresh', function() {
                                    var rates = JSON.parse(localStorage.getItem('rates_list'));


                                    var selectedType = localStorage.getItem('service_type');

                                    if (rates.length) {
                                        $('#delivery_list_box').empty();
                                        rates.forEach(rate => {
                                            if (rate.isFree) {
                                                $('#delivery_list_box').append("<div class='delivery_rate_item'><input type='radio' id='" + rate.service + "' name='lm_radio_service_type' value='" + rate.service + "' > <label for='" + rate.service + "' class='rate_service_type'> " + rate.service + ', ' + rate.date + ": <span style='float:right;'> <span style='color: #999999; text-decoration:line-through;'> GH₵" + parseFloat(rate.cost).toFixed(2) + "</span> <span style='font-size: 0.8em;'>(Free Shipping)</span></span></label></div>");
                                            } else {
                                                $('#delivery_list_box').append("<div class='delivery_rate_item'><input type='radio' id='" + rate.service + "' name='lm_radio_service_type' value='" + rate.service + "' > <label for='" + rate.service + "' class='rate_service_type'> " + rate.service + ', ' + rate.date + ": <span style='color: #444; text-decoration:none;float:right;'> GH₵" + parseFloat(rate.cost).toFixed(2) + "</span></label></div>");
                                            }

                                            if (selectedType == rate.service) {
                                                $("input:radio[value='" + rate.service + "'][name='lm_radio_service_type']").prop('checked', true);
                                            }
                                        });
                                    }
                                });

                                $("#lastmal_brand_section").on("change", "input[name='lm_radio_service_type']", function() {
                                    var sel = $('input[name^="lm_radio_service_type"]:checked').val();
                                    $('#service_type').val(sel).change();
                                });
                            });
                        </script> -->
                    </div>
                </form>

                <div class="brand_footer">
                    <img class="alignLeft" style="float:left; margin-right:10px;vertical-align:middle;" height="40px" width="40px" src="https://lastmal.com/assets/images/logo.svg" />
                    <p style="font-size:13px; line-height:1.5; margin-bottom:0; vertical-align:middle;">
                        Shipping powered by Lastmal. See <span><a href="https://lastmal.com/terms" target="blank">Terms</a>.</span>
                    </p>
                </div>
            </div>
<?php
        }
        add_action('woocommerce_review_order_before_payment', 'LMSFW_add_branding');
    }

    // add meta data to process product
    function LMSFW_save_product_meta($product_id)
    {
        if (!class_exists('Lastmal_Shipping_Method')) {
            lastmal_shipping_method();
        }
        $WC_Lastmal_Shipping_Method = new Lastmal_Shipping_Method();
        $currentAPI = $WC_Lastmal_Shipping_Method->get_option('api_key');

        // $product = wc_get_product($product_id);
        update_post_meta($product_id, 'vendor', $currentAPI);

        // HPOS Compatibility
        // $product->update_meta_data('paymentMode', 'Pay with LM');
    }

    add_action('before_woocommerce_init', function () {
        if (
            class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)
        ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    });

    // actions
    add_action('woocommerce_update_product', 'LMSFW_save_product_meta', 10, 1);
    add_action('woocommerce_new_product', 'LMSFW_save_product_meta', 10, 1);
    add_action('woocommerce_new_product_variation', 'LMSFW_save_product_meta', 10, 1);
    add_action('woocommerce_update_product_variation', 'LMSFW_save_product_meta', 10, 1);


    // Set the plugin slug
    define('LMSFW_PLUGIN_SLUG', 'wc-settings');
    add_action('woocommerce_shipping_init', 'lastmal_shipping_method');

    function add_lastmal_shipping_method($methods)
    {
        $methods[] = 'Lastmal_Shipping_Method';
        // $methods['lastmal'] = 'Lastmal_Shipping_Method';

        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'add_lastmal_shipping_method');

    function LMSFW_allow_unsafe_urls($args)
    {
        $args['reject_unsafe_urls'] = false;
        return $args;
    }
    add_filter('http_request_args', 'LMSFW_allow_unsafe_urls');

    /*
    * Add settings link to plugin list
    */
    function add_lastmal_shipping_method_action_links($links)
    {
        $links[] = '<a href="' . menu_page_url(LMSFW_PLUGIN_SLUG, false) . '&tab=shipping&section=lastmal">Settings</a>';
        return $links;
    }

    // Setting action for plugin
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_lastmal_shipping_method_action_links');

    if (!function_exists('LMSFW_log')) {

        function LMSFW_log($log)
        {
            if (true === WP_DEBUG) {
                if (is_array($log) || is_object($log)) {
                    error_log(print_r($log, true));
                } else {
                    error_log($log);
                }
            }
        }
    }
}
