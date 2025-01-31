<?php
/**
 * Plugin Name: ONVO Pay
 * Plugin URI: https://wordpress.org/plugins/onvo-pay-gateway/
 * Description: ONVO Pay es una solución integrada de pagos en línea que ayuda a los comercios a vender más y mejor, mientras optimiza la experiencia de compra de los clientes.
 * Version: 0.21.0
 * Requires at least: 6.2
 * Tested up to: 6.7.1
 * WC requires at least: 7.9.0
 * WC tested up to: 9.5.2
 * Author:      ONVO
 * Author URI:  https://onvopay.com/
 * Text Domain: wc-onvo-payment-gateway
 *
 * @package ONVO
 */

namespace ONVO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_ONVO_PAY_NAME', 'wc-onvo-payment-gateway' );
define( 'WC_ONVO_PAY_VERSION', '0.21.0' );
define( 'WC_ONVO_PAY_MAIN_FILE', __FILE__ );
define( 'WC_ONVO_PAY_PLUGIN_PATH', plugin_dir_path( WC_ONVO_PAY_MAIN_FILE ) );
define( 'WC_ONVO_PAY_PLUGIN_URL', untrailingslashit( plugins_url( '/', WC_ONVO_PAY_MAIN_FILE ) ) );

function init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once WC_ONVO_PAY_PLUGIN_PATH . 'vendor/autoload.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/functions.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/functions-dev.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/compatibility/woocommerce-enable-hpos.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/abtract-class-enum.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/currency.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-api-exception.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-api.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-intent.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-intent-builder.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-product.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-price.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-customer-builder.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-gateway-onvo-pay.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/blocks/class-gateway-onvo-pay-block.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-intent-service.php';
	require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/class-event-scheduler.php';
}

add_action( 'plugins_loaded', 'ONVO\init' );


function add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Gateway_ONVO_Pay';

	return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'ONVO\add_gateway_class' );

// Reset ONVO Intent ID early on
function maybe_reset_onvo_intent_id() {
	if ( WC()->cart->is_empty() ) {
		\ONVO\set_intent_id_for_wc_session( null );
	}
}

add_action( 'woocommerce_cart_updated', 'ONVO\maybe_reset_onvo_intent_id' );
add_action( 'woocommerce_cart_emptied', 'ONVO\maybe_reset_onvo_intent_id' );

function sync_onvo_customer_with_wp_user( int $order_id ) {
	$gateway = new \WC_Gateway_ONVO_Pay();
	$api     = $gateway->get_onvo_api();

	try {
		$order = wc_get_order( $order_id );
		$onvo_customer_id_in_order = \ONVO\get_customer_id_from_order( $order_id );
		if ( ! $onvo_customer_id_in_order ) {
			return;
		}

		if ( $order->get_customer_id() ) {
			$customer = Customer_Builder::from( new \WC_Customer( $order->get_customer_id() ) );
		} else {
			$customer = Customer_Builder::from( $order );
		}

		$api->update_customer( $onvo_customer_id_in_order, $customer->get_array() );
	} catch ( \Exception $exception ) {
		\ONVO\error( $exception->getMessage() );
	}
}

add_action( 'sync_onvo_customer_with_wp_user', 'ONVO\sync_onvo_customer_with_wp_user' );


// Enable support for Woocommerce Blocks
function register_blocks_payment_method( $payment_method_registry ) {
	$payment_method_registry->register( new \ONVO\WC_Gateway_ONVO_Pay_Block() );
}

function enable_blocks_support() {
	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		add_action( 'woocommerce_blocks_payment_method_type_registration', 'ONVO\register_blocks_payment_method' );
	}
}

add_action( 'woocommerce_blocks_loaded', 'ONVO\enable_blocks_support' );

/**
 * Schedule a retry after checkout to check the status of the payment intent.
 *
 * @param int $order_id
 * @param int $in_minutes The number of minutes to wait before retrying, defaults to 1 minute.
 *
 * @return void
 */
function schedule_retry_after_checkout( int $order_id, int $in_minutes = 1 ): void {
	as_schedule_single_action(
		gmdate( 'U' ) + ( MINUTE_IN_SECONDS * $in_minutes ),
		'ONVO\onvo_retry_after_checkout',
		[ $order_id ]
	);
}

/**
 * Retry after checkout to check the status of the payment intent, and update the order status accordingly.
 *
 * @param int $order_id
 *
 * @return void
 */
function onvo_retry_after_checkout( int $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	$gateway = new \WC_Gateway_ONVO_Pay();
	$api     = $gateway->get_onvo_api();

	try {
		$intent_id = \ONVO\get_intent_id_for_order( $order_id );
		if ( ! $intent_id ) {
			throw new \Exception( 'No intent ID found for order' );
		}

		try {
			$api_intent = $api->get_intent( $intent_id );
			$intent     = \ONVO\Intent_Builder::from_get_intent_response( $api_intent );
		} catch ( \Exception $exception ) {
			schedule_retry_after_checkout( $order_id, 3 ); // Retry in 3 minutes
			\ONVO\error( $exception->getMessage() );
			throw $exception;
		}

		if ( $intent->has_succeeded() ) {
			$gateway->update_order_status_based_on_intent( $order, $intent );

			return;
		}
	} catch ( \Exception $exception ) {
		\ONVO\error( $exception->getMessage() );
		throw $exception;
	}
}

add_action( 'ONVO\onvo_retry_after_checkout', 'ONVO\onvo_retry_after_checkout' );

function sync_order_metadata( int $order_id ) {
	$gateway = new \WC_Gateway_ONVO_Pay();
	$api     = $gateway->get_onvo_api();
	$service = new \ONVO\IntentService( $api );

	$service->sync_metadata_from_order( $order_id );
}

add_action( 'ONVO\sync_order_metadata', 'ONVO\sync_order_metadata' );
