<?php
/**
 * Plugin Name: WooCommerce ClicToPay Payment Gateway
 * Plugin URI: https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
 * Description: Accept Tunisian credit card payments on your store.
 * Author: Mohamed Safouan Besrour
 * Author URI: https://besrourms.github.io
 * Version: 1.1
 * Text Domain: woo-clictopay
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

add_action('plugins_loaded', 'clictopay_init_gateway_class');

function clictopay_init_gateway_class() {
    if (!class_exists('WC_Payment_Gateway')) return;

    class WC_ClicToPay_Gateway extends WC_Payment_Gateway {
        private $sandbox_mode;
        private $api_url;
        private $api_login;
        private $api_password;

        public function __construct() {
            $this->id = 'clictopay';
            $this->icon = 'https://www.drupal.org/files/project-images/ClicToPay_logo.png';
            $this->has_fields = false;
            $this->method_title = 'ClicToPay Gateway';
            $this->method_description = 'Accept Tunisian credit card payments on your store.';
            $this->supports = ['products'];

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);
            $this->order_status = $this->get_option('order_status', 'processing');
            $this->sandbox_mode = 'yes' === $this->get_option('sandbox');

            $this->api_url = $this->sandbox_mode ? $this->get_option('sandbox_URL') : $this->get_option('production_URL');
            $this->api_login = $this->sandbox_mode ? $this->get_option('ClicToPay_sandbox_Login') : $this->get_option('ClicToPay_production_Login');
            $this->api_password = $this->sandbox_mode ? $this->get_option('ClicToPay_sandbox_Password') : $this->get_option('ClicToPay_production_Password');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
            add_action('woocommerce_thankyou_' . $this->id, [$this, 'check_response']);
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title' => __('Enable/Disable', 'woo-clictopay'),
                    'type' => 'checkbox',
                    'label' => __('Enable ClicToPay Payment', 'woo-clictopay'),
                    'default' => 'yes'
                ],
                'title' => [
                    'title' => __('Title', 'woo-clictopay'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woo-clictopay'),
                    'default' => __('ClicToPay Payment', 'woo-clictopay'),
                    'desc_tip' => true,
                ],
                'order_status' => [
                    'title' => __('Order Status', 'woo-clictopay'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => __('Choose which status you wish after checkout.', 'woo-clictopay'),
                    'default' => 'wc-completed',
                    'desc_tip' => true,
                    'options' => wc_get_order_statuses()
                ],
                'description' => [
                    'title' => __('Description', 'woo-clictopay'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'woo-clictopay'),
                    'default' => __('Payment Information', 'woo-clictopay'),
                    'desc_tip' => true,
                ],
                'instructions' => [
                    'title' => __('Instructions', 'woo-clictopay'),
                    'type' => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', 'woo-clictopay'),
                    'default' => '',
                    'desc_tip' => true,
                ],
                'sandbox' => [
                    'title' => __('Sandbox', 'woo-clictopay'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable Sandbox', 'woo-clictopay'),
                    'default' => 'yes'
                ],
                'sandbox_URL' => [
                    'title' => __('Sandbox URL', 'woo-clictopay'),
                    'type' => 'text',
                    'description' => __('Sandbox URL', 'woo-clictopay'),
                    'default' => 'https://test.clictopay.com/payment/rest/register.do',
                    'desc_tip' => true,
                ],
                'production_URL' => [
                    'title' => __('Production URL', 'woo-clictopay'),
                    'type' => 'text',
                    'description' => __('Production URL', 'woo-clictopay'),
                    'default' => 'https://ipay.clictopay.com/payment/rest/register.do',
                    'desc_tip' => true,
                ],
                'ClicToPay_sandbox_Login' => [
                    'title' => __('Sandbox ClicToPay Login', 'woo-clictopay'),
                    'type' => 'text',
                    'description' => __('Merchant login received during integration.', 'woo-clictopay'),
                    'default' => '',
                    'desc_tip' => true,
                ],
                'ClicToPay_sandbox_Password' => [
                    'title' => __('Sandbox ClicToPay Password', 'woo-clictopay'),
                    'type' => 'password',
                    'description' => __('Merchant password received during integration.', 'woo-clictopay'),
                    'default' => '',
                    'desc_tip' => true,
                ],
                'ClicToPay_production_Login' => [
                    'title' => __('Production ClicToPay Login', 'woo-clictopay'),
                    'type' => 'text',
                    'description' => __('Merchant login received during integration.', 'woo-clictopay'),
                    'default' => '',
                    'desc_tip' => true,
                ],
                'ClicToPay_production_Password' => [
                    'title' => __('Production ClicToPay Password', 'woo-clictopay'),
                    'type' => 'password',
                    'description' => __('Merchant password received during integration.', 'woo-clictopay'),
                    'default' => '',
                    'desc_tip' => true,
                ],
            ];
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $amount = $order->get_total() * 1000; // Convert to smallest currency unit

            $request_data = [
                'userName' => $this->api_login,
                'password' => $this->api_password,
                'orderNumber' => $order->get_order_number(),
                'amount' => $amount,
                'currency' => 788, // ISO 4217 currency code for TND
                'returnUrl' => $this->get_return_url($order),
            ];

            $response = wp_remote_post($this->api_url, [
                'body' => $request_data,
                'timeout' => 45,
                'sslverify' => !$this->sandbox_mode,
            ]);

            if (is_wp_error($response)) {
                wc_add_notice(__('Connection error.', 'woo-clictopay'), 'error');
                return;
            }

            $response_data = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($response_data['formUrl'])) {
                return [
                    'result' => 'success',
                    'redirect' => $response_data['formUrl'],
                ];
            } else {
                wc_add_notice(__('Payment error: ', 'woo-clictopay') . $response_data['errorMessage'], 'error');
                return;
            }
        }

        public function check_response() {
            if (empty($_GET['orderId'])) {
                return;
            }

            $order_id = sanitize_text_field($_GET['orderId']);
            
            $response = wp_remote_post($this->get_order_status_url(), [
                'body' => [
                    'userName' => $this->api_login,
                    'password' => $this->api_password,
                    'orderId' => $order_id,
                ],
                'timeout' => 45,
                'sslverify' => !$this->sandbox_mode,
            ]);

            if (is_wp_error($response)) {
                echo "Error: " . $response->get_error_message();
                return;
            }

            $response_data = json_decode(wp_remote_retrieve_body($response), true);
            $order_number = isset($response_data['OrderNumber']) ? (int) $response_data['OrderNumber'] : 0;
            $order = wc_get_order($order_number);

            if (!$order) {
                return;
            }

            if (isset($response_data['OrderStatus']) && $response_data['OrderStatus'] == 2) {
                if ($order->is_paid()) {
                    return;
                }
                $this->complete_order($order, $order_id, $response_data['Pan']);
            } else {
                $this->fail_order($order, $order_id, $response_data['ErrorMessage']);
            }
        }

        private function get_order_status_url() {
            return $this->sandbox_mode
                ? 'https://test.clictopay.com/payment/rest/getOrderStatus.do'
                : 'https://ipay.clictopay.com/payment/rest/getOrderStatus.do';
        }

        private function complete_order($order, $order_id, $pan) {
            $order->payment_complete();
            wc_reduce_stock_levels($order);
            $order->update_status($this->order_status);
            $order->add_order_note(sprintf(
                __('ClicToPay payment completed successfully: %s By: %s', 'woo-clictopay'),
                $order_id,
                $pan
            ));
        }

        private function fail_order($order, $order_id, $error_message) {
            wc_add_notice(__('Your payment was not accepted. Please try again or contact support.', 'woo-clictopay'), 'error');
            $order->add_order_note(sprintf(
                __('ClicToPay payment failed: %s => %s', 'woo-clictopay'),
                $order_id,
                $error_message
            ));
        }
    }
}

add_filter('woocommerce_payment_gateways', 'add_clictopay_gateway_class');
function add_clictopay_gateway_class($gateways) {
    $gateways[] = 'WC_ClicToPay_Gateway';
    return $gateways;
}
