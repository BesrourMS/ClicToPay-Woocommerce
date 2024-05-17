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

add_filter( 'woocommerce_payment_gateways', 'ClicToPay_add_gateway_class' );
function ClicToPay_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_ClicToPay_Gateway';
    return $gateways;
}

add_action( 'plugins_loaded', 'ClicToPay_init_gateway_class' );
function ClicToPay_init_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_ClicToPay_Gateway extends WC_Payment_Gateway {
		
		// Declare properties explicitly
		public $instructions;
		public $order_status;
		public $sandbox;
		public $sandbox_URL;
		public $production_URL;
		public $ClicToPay_sandbox_Login;
		public $ClicToPay_sandbox_Password;
		public $ClicToPay_production_Login;
		public $ClicToPay_production_Password;

		
        public function __construct() {
            $this->id = 'clictopay';
            $this->icon = 'https://www.drupal.org/files/project-images/ClicToPay_logo.png';
            $this->has_fields = false;
            $this->method_title = 'ClicToPay Gateway';
            $this->method_description = 'Accept Tunisian credit card payments on your store.';
            $this->supports = array(
                'products'
            );
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->order_status = $this->get_option( 'order_status', 'processing' );
            $this->sandbox = $this->get_option( 'sandbox' ) === 'yes' ? true : false;
            $this->sandbox_URL = $this->get_option( 'sandbox_URL' );
            $this->production_URL = $this->get_option( 'production_URL' );
            $this->ClicToPay_sandbox_Login = $this->get_option( 'ClicToPay_sandbox_Login' );
            $this->ClicToPay_sandbox_Password = $this->get_option( 'ClicToPay_sandbox_Password' );
            $this->ClicToPay_production_Login = $this->get_option( 'ClicToPay_production_Login' );
            $this->ClicToPay_production_Password = $this->get_option( 'ClicToPay_production_Password' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'check_response' ) );

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
			if ( empty( $_GET['orderId'] ) ) {
				return;
			}

			$order_id = sanitize_text_field( $_GET['orderId'] );
			
			$body = array(
				'userName' => $this->sandbox ? $this->ClicToPay_sandbox_Login : $this->ClicToPay_production_Login,
				'password' => $this->sandbox ? $this->ClicToPay_sandbox_Password : $this->ClicToPay_production_Password,
				'orderId'  => $order_id,
			);
						
			$url = $this->sandbox ? 'https://test.clictopay.com/payment/rest/getOrderStatus.do' : 'https://ipay.clictopay.com/payment/rest/getOrderStatus.do';


			$response = wp_remote_post( $url, array(
				'method'    => 'POST',
				'body'      => $body,
				'timeout'   => 45,
				'sslverify' => true,
			) );

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				echo "Error: $error_message";
			}else {
				$response_body = wp_remote_retrieve_body( $response );
				$response_data = json_decode( $response_body, true );
				$order_number = isset( $response_data['OrderNumber'] ) ? (int) $response_data['OrderNumber'] : 0; // Convert to integer
				$order = wc_get_order( $order_number );
				if ( isset( $response_data['OrderStatus'] ) && $response_data['OrderStatus'] == 2 ) {
					if ( $order->is_paid() ) {
						return;
					}
					$order->payment_complete();
					wc_reduce_stock_levels( $order );
					// Update order status based on the selected option
					$new_status = isset( $this->order_status ) ? $this->order_status : 'wc-completed';
					$order->update_status( $new_status ); // Update order status
					$order->add_order_note('ClicToPay payment completed successfully : '.$order_id.' By : '.$response_data['Pan'] );
				} else {
					echo '<div class="woocommerce-error">Votre paiement n\'a pas été accepté. Veuillez réessayer ou contacter le support.</div>';
					$order->add_order_note('ClicToPay payment failed: '.$order_id.' => ' . $response_data['ErrorMessage'] );
				}
			}
		}
    }
}
