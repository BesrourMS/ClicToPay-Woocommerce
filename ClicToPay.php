<?php
/*
 * Plugin Name: WooCommerce ClicToPay Payment Gateway
 * Plugin URI: https://rudrastyh.com/woocommerce/payment-gateway-plugin.html
 * Description: Accept tunisian credit card payments on your store.
 * Author: Mohamed Safouan Besrour
 * Author URI: https://besrourms.github.io
 * Version: 1.0
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
			$this->icon = 'https://www.drupal.org/files/project-images/ClicToPay_logo.png'; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields = false; // in case you need a custom credit card form
			$this->method_title = 'ClicToPay Gateway';
			$this->method_description = 'Description of ClicToPay payment gateway'; // will be displayed on the options page
		 
			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			
			$this->title = $this->get_option( 'title' );
            		$this->description = $this->get_option( 'description' );
            		$this->instructions = $this->get_option( 'instructions', $this->description );
            		$this->order_status = $this->get_option( 'order_status', 'processing' );
            		$this->sandbox = $this->get_option( 'sandbox' );
            		$this->sandbox_URL = $this->get_option( 'sandbox_URL' );
            		$this->production_URL = $this->get_option( 'production_URL' );
           		$this->ClicToPay_sandbox_Login = $this->get_option( 'ClicToPay_sandbox_Login' );
            		$this->ClicToPay_sandbox_Password = $this->get_option( 'ClicToPay_sandbox_Password' );
		 
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			// We need custom JavaScript to obtain a token
			//add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		 
			// You can also register a webhook here
			// add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
		public function init_form_fields() {

		    $this->form_fields = array(
			'enabled' => array(
			    'title'   => __( 'Enable/Disable', $this->domain ),
			    'type'    => 'checkbox',
			    'label'   => __( 'Enable ClicToPay Payment', $this->domain ),
			    'default' => 'yes'
			),
			'title' => array(
			    'title'       => __( 'Title', $this->domain ),
			    'type'        => 'text',
			    'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
			    'default'     => __( 'ClicToPay Payment', $this->domain ),
			    'desc_tip'    => true,
			),
			'order_status' => array(
			    'title'       => __( 'Order Status', $this->domain ),
			    'type'        => 'select',
			    'class'       => 'wc-enhanced-select',
			    'description' => __( 'Choose whether status you wish after checkout.', $this->domain ),
			    'default'     => 'wc-completed',
			    'desc_tip'    => true,
			    'options'     => wc_get_order_statuses()
			),
			'description' => array(
			    'title'       => __( 'Description', $this->domain ),
			    'type'        => 'textarea',
			    'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
			    'default'     => __('Payment Information', $this->domain),
			    'desc_tip'    => true,
			),
			'instructions' => array(
			    'title'       => __( 'Instructions', $this->domain ),
			    'type'        => 'textarea',
			    'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
			    'default'     => '',
			    'desc_tip'    => true,
			),
			'sandbox' => array(
			    'title'   => __( 'Sandbox', $this->domain ),
			    'type'    => 'checkbox',
			    'label'   => __( 'Enable/Disable Sandbox', $this->domain ),
			    'default' => 'yes'
			),
			'sandbox_URL' => array(
			    'title'       => __( 'Sandbox URL', $this->domain ),
			    'type'        => 'text',
			    'description' => __( 'Sandbox URL', $this->domain ),
			    'default'     => 'https://test.clictopay.com/payment/rest/register.do',
			    'desc_tip'    => true,
			),
			'production_URL' => array(
			    'title'       => __( 'Production URL', $this->domain ),
			    'type'        => 'text',
			    'description' => __( 'Production URL', $this->domain ),
			    'default'     => 'https://production.clictopay.com/payment/rest/register.do',
			    'desc_tip'    => true,
			),
			'ClicToPay_sandbox_Login' => array(
			    'title'       => __( 'Sandbox ClicToPay Login', $this->domain ),
			    'type'        => 'text',
			    'description' => __( 'Login marchand reçu lors de l\'inscription.', $this->domain ),
			    'default'     => '',
			    'desc_tip'    => true,
			),
			'ClicToPay_sandbox_Password' => array(
			    'title'       => __( 'Sandbox ClicToPay Password', $this->domain ),
			    'type'        => 'password',
			    'description' => __( 'Password marchand reçu lors de l\'inscription.', $this->domain ),
			    'default'     => '',
			    'desc_tip'    => true,
			),
		    );
		}

 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
 
			global $woocommerce;
 
			// we need it to get any order detailes
			$order = wc_get_order( $order_id );
 
            		// Variables To Send


			if($this->sandbox = true){

				$variables_to_send = array(
				    'userName'          =>  $this->$ClicToPay_sandbox_Login,
				    'password'          =>  $this->$ClicToPay_sandbox_Password,
				    'orderNumber'       =>  $this->$order_id,
				    'amount'            =>  $order->get_total();
							'currency'			=>	788;
				);

				$redirect = $this->sandbox_URL;

			}elseif($this->sandbox = false){

				$variables_to_send = array(
				    'userName'          =>  $this->$ClicToPay_production_Login,
				    'password'          =>  $this->$ClicToPay_production_Password,
				    'orderNumber'       =>  $this->$order_id,
				    'amount'            =>  $order->get_total();
							'currency'			=>	788;
				);

				$redirect = $this->production_URL;

			};
			
			$response = wp_remote_post( $redirect, $variables_to_send );
			if( !is_wp_error( $response ) ) {
 
				$body = json_decode( $response);
 
				// it could be different depending on your payment processor
				if ( $body['orderId']) {
 
					// we received the payment
					$order->set_transaction_id($body['orderId']);
					
 
					// Redirect to the thank you page
					return array(
						'result'    => 'success',
						'redirect' => $this->get_return_url( $body["formUrl"] )
					);
 
				} else {
					wc_add_notice(  'Please try again.', 'error' );
					return;
				}
 
			} else {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			} 
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
			$variables_to_send = array(
				'userName'          =>  $this->$ClicToPay_sandbox_Login,
				'password'          =>  $this->$ClicToPay_sandbox_Password,
				'orderId'       =>  $_GET["orderId"]
			);

            		$redirect = 'https://test.clictopay.com/payment/rest/getOrderStatus.do';
			$response = wp_remote_post( $redirect, $variables_to_send );	
			$args = array(
				'transaction_id' => $_GET["orderId"],
			);
			$orders = wc_get_orders( $args );
			$order = $orders[0];
			$order->payment_complete();
			$order->reduce_order_stock();
		 
			update_option('webhook_debug', $_GET);
	 	}
 	}
}
