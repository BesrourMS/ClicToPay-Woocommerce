<?php
/*
 * Plugin Name: WooCommerce ClicToPay Payment Gateway
 * Plugin URI: https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
 * Description: Accept Tunisian credit card payments on your store.
 * Author: Mohamed Safouan Besrour
 * Author URI: https://besrourms.github.io
 * Version: 1.0
 * Text Domain: woo-clictopay
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'ClicToPay_add_gateway_class' );
function ClicToPay_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_ClicToPay_Gateway';
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'ClicToPay_init_gateway_class' );
function ClicToPay_init_gateway_class() {

    class WC_ClicToPay_Gateway extends WC_Payment_Gateway {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct() {

            $this->id = 'ClicToPay'; // payment gateway plugin ID
            $this->icon = 'https://www.drupal.org/files/project-images/ClicToPay_logo.png'; // URL of the icon that will be displayed on the checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'ClicToPay Gateway';
            $this->method_description = 'Description of ClicToPay payment gateway'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial, we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'processing' );
            $this->sandbox = $this->get_option( 'sandbox' );
            $this->sandbox_URL = $this->get_option( 'sandbox_URL' );
            $this->production_URL = $this->get_option( 'production_URL' );
            $this->ClicToPay_sandbox_Login = $this->get_option( 'ClicToPay_sandbox_Login' );
            $this->ClicToPay_sandbox_Password = $this->get_option( 'ClicToPay_sandbox_Password' );
            $this->ClicToPay_production_Login = $this->get_option( 'ClicToPay_production_Login' );
            $this->ClicToPay_production_Password = $this->get_option( 'ClicToPay_production_Password' );

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // You can also register a webhook here
            add_action( 'woocommerce_api_wc_gateway_' . $this->id, array( $this, 'check_response' ) );
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'woo-clictopay' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable ClicToPay Payment', 'woo-clictopay' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woo-clictopay' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woo-clictopay' ),
                    'default'     => __( 'ClicToPay Payment', 'woo-clictopay' ),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __( 'Order Status', 'woo-clictopay' ),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __( 'Choose which status you wish after checkout.', 'woo-clictopay' ),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __( 'Description', 'woo-clictopay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-clictopay' ),
                    'default'     => __( 'Payment Information', 'woo-clictopay' ),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', 'woo-clictopay' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woo-clictopay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'sandbox' => array(
                    'title'   => __( 'Sandbox', 'woo-clictopay' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable/Disable Sandbox', 'woo-clictopay' ),
                    'default' => 'yes'
                ),
                'sandbox_URL' => array(
                    'title'       => __( 'Sandbox URL', 'woo-clictopay' ),
                    'type'        => 'text',
                    'description' => __( 'Sandbox URL', 'woo-clictopay' ),
                    'default'     => 'https://test.clictopay.com/payment/rest/register.do',
                    'desc_tip'    => true,
                ),
                'production_URL' => array(
                    'title'       => __( 'Production URL', 'woo-clictopay' ),
                    'type'        => 'text',
                    'description' => __( 'Production URL', 'woo-clictopay' ),
                    'default'     => 'https://ipay.clictopay.com/payment/rest/register.do',
                    'desc_tip'    => true,
                ),
                'ClicToPay_sandbox_Login' => array(
                    'title'       => __( 'Sandbox ClicToPay Login', 'woo-clictopay' ),
                    'type'        => 'text',
                    'description' => __( 'Merchant login received during integration.', 'woo-clictopay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'ClicToPay_sandbox_Password' => array(
                    'title'       => __( 'Sandbox ClicToPay Password', 'woo-clictopay' ),
                    'type'        => 'password',
                    'description' => __( 'Merchant password received during integration.', 'woo-clictopay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'ClicToPay_production_Login' => array(
                    'title'       => __( 'Production ClicToPay Login', 'woo-clictopay' ),
                    'type'        => 'text',
                    'description' => __( 'Merchant login received during integration.', 'woo-clictopay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'ClicToPay_production_Password' => array(
                    'title'       => __( 'Production ClicToPay Password', 'woo-clictopay' ),
                    'type'        => 'password',
                    'description' => __( 'Merchant password received during integration.', 'woo-clictopay' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        /*
         * Process the payment
         */
        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );

            // Prepare variables for the request
            $order_number = $order->get_order_number();
            $amount = $order->get_total() * 1000; // Convert to smallest currency unit
            $currency = 788; // ISO 4217 currency code for TND
            $return_url = $this->get_return_url( $order );

            $request_data = array(
                'userName'    => $this->sandbox ? $this->ClicToPay_sandbox_Login : $this->ClicToPay_production_Login,
                'password'    => $this->sandbox ? $this->ClicToPay_sandbox_Password : $this->ClicToPay_production_Password,
                'orderNumber' => $order_number,
                'amount'      => $amount,
                'currency'    => $currency,
                'returnUrl'   => $return_url,
            );

            $url = $this->sandbox ? $this->sandbox_URL : $this->production_URL;

            $response = wp_remote_post( $url, array(
                'method'    => 'POST',
                'body'      => $request_data,
                'timeout'   => 45,
                'sslverify' => false,
            ) );

            if ( is_wp_error( $response ) ) {
                wc_add_notice( 'Connection error.', 'error' );
                return;
            }

            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body, true );

            if ( isset( $response_data['formUrl'] ) ) {
                return array(
                    'result'   => 'success',
                    'redirect' => $response_data['formUrl'],
                );
            } else {
                wc_add_notice( 'Payment error: ' . $response_data['errorMessage'], 'error' );
                return;
            }
        }

        /*
         * Webhook for handling payment status
         */
        public function check_response() {
            $order_id = $_GET['orderId'];
            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                return;
            }

            // Verify payment status
            $request_data = array(
                'userName' => $this->sandbox ? $this->ClicToPay_sandbox_Login : $this->ClicToPay_production_Login,
                'password' => $this->sandbox ? $this->ClicToPay_sandbox_Password : $this->ClicToPay_production_Password,
                'orderId'  => $order_id,
            );

            $url = $this->sandbox ? 'https://test.clictopay.com/payment/rest/getOrderStatus.do' : 'https://ipay.clictopay.com/payment/rest/getOrderStatus.do';

            $response = wp_remote_post( $url, array(
                'method'    => 'POST',
                'body'      => $request_data,
                'timeout'   => 45,
                'sslverify' => false,
            ) );

            if ( is_wp_error( $response ) ) {
                return;
            }

            $response_body = wp_remote_retrieve_body( $response );
            $response_data = json_decode( $response_body, true );

            if ( isset( $response_data['orderStatus'] ) && $response_data['orderStatus'] == 2 ) {
                $order->payment_complete();
                $order->reduce_order_stock();
                wc_add_order_note( $order->get_id(), 'ClicToPay payment completed successfully.' );
            } else {
                wc_add_order_note( $order->get_id(), 'ClicToPay payment failed: ' . $response_data['errorMessage'] );
            }
        }
    }
}
