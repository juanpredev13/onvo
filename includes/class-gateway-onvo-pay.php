<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Onvo Pay Gateway
 */
class WC_Gateway_ONVO_Pay extends \WC_Payment_Gateway {

	/**
	 *  gateways can support subscriptions, refunds, saved payment methods,
	 *
	 * @var string[]
	 */
	public $supports = [
		'products',
		'subscriptions',
		'multiple_subscriptions',
		'subscription_cancellation',
		'subscription_suspension',
		'subscription_reactivation',
		'subscription_amount_changes',
		'subscription_date_changes',
	];

	/**
	 * @var ONVO\API
	 */
	private \ONVO\API $onvo_api;

	/**
	 * @var null|string
	 */
	private ?string $intent_id = null;

	/**
	 * ONVO customer id
	 *
	 * @var null|string
	 */
	private ?string $customer_id = null;

	/**
	 * @var string|null
	 */
	private $store_currency;

	/**
	 * @var bool
	 */
	protected $cart_has_subscription = false;

	/**
	 * @var bool
	 */
	protected $is_cart_renewal = false;

	protected \ONVO\IntentService $intent_service;

	/**
	 *
	 */
	public function __construct() {
		$this->id                 = WC_ONVO_PAY_NAME; // payment gateway plugin ID
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields         = true; // in case you need a custom credit card form
		$this->method_title       = 'ONVO Payment';
		$this->method_description = 'Acepta pagos con tarjeta, SINPE y SINPE Movil.'; // will be displayed on the options page

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title              = $this->get_option( 'title', $this->method_title );
		$this->description        = $this->get_option( 'description' );
		$this->enabled            = $this->get_option( 'enabled' );
		$this->merchant_key       = $this->get_option( 'merchant_key' );
		$this->testmode           = 'yes' === $this->get_option( 'testmode' );
		$this->private_key        = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
		$this->publishable_key    = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
		$this->onvo_debug         = $this->get_option( 'onvo_debug' );
		$this->spinner_background = $this->get_option( 'spinner_background' );
		$this->spinner_opacity    = $this->get_option( 'spinner_opacity' );
		$this->init_api();
		$this->intent_service = new \ONVO\IntentService( $this->onvo_api );

		// This action hook saves the plugin's settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// ONVO customer id
		$this->set_customer_id( \ONVO\get_customer_id( get_current_user_id(), $this->testmode )  );

		// Define currency.
		try {
			$this->store_currency = \ONVO\Currency::from( get_woocommerce_currency() )->getValue();
		} catch ( \UnexpectedValueException $e ) {
			\ONVO\error( $e->getMessage(), [
				'method' => __METHOD__
			] );
		}

		// Subscriptions
		$this->store_has_wc_subscriptions = class_exists( 'WC_Subscriptions_Order' );
		if ( $this->store_has_wc_subscriptions ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'woocommerce_scheduled_subscription_payment_retry', array( $this, 'retry_subscription_order' ), 1 );
			add_action( 'woocommerce_subscriptions_changed_failing_payment_method_' . $this->id, array( $this, 'failing_payment_method' ), 10, 2 );
			add_filter( 'wcs_view_subscription_actions', array( $this, 'wcs_view_subscription_actions' ) );
		}

		// Product Bundles
		$this->store_has_wc_product_bundles = class_exists( 'WC_Bundles' );
		if ( $this->store_has_wc_product_bundles ) {
			require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/compatibility/woocommerce-product-bundles.php';
		}

		// add support for WooCommerce All Products For Subscriptions
		$this->store_has_wc_all_products_for_subscriptions = class_exists( 'WCS_ATT' );
		if ( $this->store_has_wc_all_products_for_subscriptions ) {
			require_once WC_ONVO_PAY_PLUGIN_PATH . 'includes/compatibility/woocommerce-all-products-for-subscriptions.php';
		}

		// We need custom JavaScript to obtain a token
		add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ], 100 );

		// Maybe create intent for current cart. We give priority 0 to make sure we execute before `wp_enqueue_scripts` is executed.
		add_action( 'wp_head', [ $this, 'maybe_create_intent' ], 0 );

		// calculate subscription stuff
		add_action( 'wp_head', [ $this, 'on_page_change' ] );

		// Save payment data during checkout
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'maybe_add_intent_to_order' ], 0 );

		// On update cart totals, update the intent
		add_action( 'shutdown', [ $this, 'maybe_update_intent_on_cart_totals_update' ] );

		// On payment completed, schedule an action to sync the ONVO customer with the WP user
		add_action( 'onvo_pay_order_payment_confirmed', [ $this, 'maybe_schedule_sync_onvo_customer_with_wp_user' ] );
	}

	/**
	 * @return void
	 */
	public function maybe_schedule_sync_onvo_customer_with_wp_user( int $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		as_schedule_single_action(
			time() + 10,
			'sync_onvo_customer_with_wp_user',
			[ 'order_id' => $order->get_id() ],
			WC_ONVO_PAY_NAME,
			true
		);
	}

	public function init_api() {
		$this->onvo_api = ONVO\API::get_instance();
		$this->onvo_api->set_private_key( $this->private_key );
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled'              => [
				'title'       => __( 'Enable/Disable', 'onvo-pay' ),
				'label'       => __( 'Enable Onvo Pay', 'onvo-pay' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			],
			'title'                => [
				'title'       => __( 'Title', 'onvo-pay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'onvo-pay' ),
				'default'     => __( 'Tarjeta de crédito o débito y SINPE Móvil', 'onvo-pay' ),
				'desc_tip'    => true,
			],
			'description'          => [
				'title'       => __( 'Description', 'onvo-pay' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'onvo-pay' ),
				'default'     => __( 'Cancelá utilizando tu tarjeta de crédito o débito y SINPE Móvil.', 'onvo-pay' ),
			],
			'merchant_key'         => [
				'title'       => __( 'Merchant key', 'onvo-pay' ),
				'type'        => 'text',
				'description' => __( 'Set a merchant key for order description prefix', 'onvo-pay' ),
				'default'     => __( 'woocommerce', 'onvo-pay' ),
			],
			'testmode'             => [
				'title'       => __( 'Test mode', 'onvo-pay' ),
				'label'       => __( 'Enable Test Mode', 'onvo-pay' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'onvo-pay' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'test_publishable_key' => [
				'title' => __( 'Test Publishable Key', 'onvo-pay' ),
				'type'  => 'text'
			],
			'test_private_key'     => [
				'title' => __( 'Test Private Key', 'onvo-pay' ),
				'type'  => 'password',
			],
			'publishable_key'      => [
				'title' => __( 'Live Publishable Key', 'onvo-pay' ),
				'type'  => 'text'
			],
			'private_key'          => [
				'title' => __( 'Live Private Key', 'onvo-pay' ),
				'type'  => 'password'
			],
			'spinner_background' => [
				'title'       => __( 'Spinner background color', 'onvo-pay' ),
				'type'        => 'text',
				'description' => __( 'Spinner background color in hexadecimal.', 'onvo-pay' ),
				'default'     => __( '#000000', 'onvo-pay' ),
				'desc_tip'    => true,
			],
			'spinner_opacity'    => [
				'title'       => __( 'Spinner background opacity', 'onvo-pay' ),
				'type'        => 'text',
				'description' => __( 'Spinner background opacity a value between 0 and 1.', 'onvo-pay' ),
				'default'     => __( '0.6', 'onvo-pay' ),
				'desc_tip'    => true,
			],
			'onvo_debug'         => [
				'title'       => __( 'Debug', 'onvo-pay' ),
				'label'       => __( 'Enable debugging', 'onvo-pay' ),
				'type'        => 'checkbox',
				'description' => __( 'Show every shopper trail in the browser console.', 'onvo-pay' ),
				'default'     => 'no',
				'desc_tip'    => true,
			],
		];
	}

	public function on_page_change() {
		// Subscriptions
		$this->store_has_wc_subscriptions = class_exists( 'WC_Subscriptions_Order' );
		if ( $this->store_has_wc_subscriptions ) {
			$this->cart_has_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
			$this->is_cart_renewal       = ! empty( wcs_cart_contains_renewal() );
		}

		if ( $this->is_checkout() ) {
			if ( ! $this->store_currency ) {
				wc_add_notice( 'La moneda de la tienda no es compatible con las opciones habilitadas por ONVO Pay, CRC o USD', 'error' );

				return [
					'result' => 'failure',
				];
			}
		}
	}

	/**
	 * @return void
	 */
	public function payment_fields() {
		// ok, let's display some description before the payment form
		if ( $this->description ) {
			// display the description with <p> tags etc.
			echo wpautop( wp_kses_post( $this->description ) );
		}

		// I will echo() the form, but you can close PHP tags and print it directly in HTML
		echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

		// Add this action hook if you want your custom payment gateway to support it
		do_action( 'woocommerce_credit_card_form_start', $this->id );
		do_action( 'woocommerce_credit_card_form_end', $this->id );

		echo '</fieldset>';
		echo '<input type="hidden" id="onvo_intent_id" name="onvo_intent_id" value="" />';
		echo '<input type="hidden" id="onvo_method_id" name="onvo_method_id" value="" />';
		echo '<input type="hidden" id="onvo_error_msg" name="onvo_error_msg" value="" />';
	}

	/**
	 * @return void
	 */
	public function payment_scripts() {
		if ( ( ! $this->is_checkout() || ! $this->store_currency ) || \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
			return;
		}

		wp_register_script( 'onvo-pay-sdk-js', '//sdk.onvopay.com/sdk.js', [], WC_ONVO_PAY_VERSION, true );
		wp_register_script( WC_ONVO_PAY_NAME,
			WC_ONVO_PAY_PLUGIN_URL . '/assets/js/build/shortcode.js',
			[ 'onvo-pay-sdk-js' ],
			WC_ONVO_PAY_VERSION,
			true
		);
		$this->localize_script();

		wp_enqueue_script( WC_ONVO_PAY_NAME );
	}

	/**
	 * @return void
	 */
	public function maybe_create_intent() {
		if ( ! $this->is_checkout() || ! $this->store_currency ) {
			return;
		}

		if ( WC()->cart->needs_payment() ) {
			$cart   = WC()->cart;
			$intent = \ONVO\Intent_Builder::from_cart(
				$cart,
				$this->store_currency,
				$this->customer_id
			);
		} else {
			global $wp;
			if ( ! isset( $wp->query_vars['order-pay'] ) ) {
				return;
			}

			$order    = wc_get_order( absint( $wp->query_vars['order-pay'] ) );
			if ( ! $order || ! $order->needs_payment() ) {
				return;
			}

			$onvo_customer_id = \ONVO\get_customer_id( $order->get_user_id(), $this->testmode );
			$intent           = \ONVO\Intent_Builder::from_order( $order, $onvo_customer_id );
		}

		if ( $intent->get_id() ) {
			$this->set_intent_id( $intent->get_id() );
			\ONVO\set_intent_id_for_wc_session( $this->get_intent_id() );

			return;
		}

		if ( $intent->get_amount() <= 0 ) {
			return;
		}

		$this->set_customer_id( $intent->get_customer_id() );

		// validate tha the customer id actually exist in onvo
		if ( $this->get_customer_id() ) {
			try {
				$api_customer = $this->onvo_api->get_customer( $this->get_customer_id() );
				$this->set_customer_id( $api_customer['id'] );
			} catch ( \Exception $e ) {
				\ONVO\error( $e->getMessage(), [
					'method' => __METHOD__
				] );

				$this->set_customer_id( null );
			}
		}

		if ( ! $this->get_customer_id() && is_user_logged_in() ) {
			try {
				$customer     = \ONVO\Customer_Builder::from( $cart->get_customer() );
				$api_customer = $this->onvo_api->create_customer( $customer->get_array() );

				$this->set_customer_id( $api_customer['id'] );
				\ONVO\set_customer_id_for_wp_user( $this->get_customer_id(), $cart->get_customer()->get_id(), $this->testmode );
				$intent->set_customer_id( $this->get_customer_id() );
			} catch ( \Exception $e ) {
				\ONVO\error( $e->getMessage(), [
					'method' => __METHOD__
				] );
			}
		}

		try {
			$api_intent = $this->onvo_api->create_intent( $intent );
			if ( array_key_exists( 'id', $api_intent ) ) {
				$this->set_intent_id( $api_intent['id'] );
				\ONVO\set_intent_id_for_wc_session( $this->get_intent_id() );
			}
		} catch ( \Exception $e ) {
			\ONVO\error( 'Error processing intent: ' . $e->getMessage(),
				[
					'intent_id' => $intent->get_id(),
					'intent_amount' => $intent->get_amount(),
				]
			);

			wc_add_notice( __( 'Hubo un error al procesar el intento de pago.', 'onvo-pay' ), 'error' );
		}
	}

	/**
	 * @param int $order_id
	 *
	 * @return array|string[]|void
	 * @throws WC_Data_Exception
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;

		$this->maybe_add_intent_to_order( $order_id );

		// we need it to get any order details
		$order             = wc_get_order( $order_id );
		$payment_intent_id = ONVO\get_intent_id_for_order( $order );
		$payment_method_id = ONVO\get_payment_method_id_from_order( $order );
		$onvo_error_msg    = $order->get_meta( '_onvo_error_msg' );

		// We got an error from the SDK
		if ( ! empty( $onvo_error_msg ) ) {
			$this->handle_sdk_error_on_checkout( $order, $payment_intent_id, $onvo_error_msg );

			return [
				'result' => 'failure',
				'redirect' => ''
			];
		}

		if ( ! $payment_intent_id ) {
			$this->handle_invalid_payment_intent_id_on_checkout( $order, $payment_intent_id );

			return [
				'result' => 'failure',
				'redirect' => ''
			];
		}

		// If we don't have a payment method id, we can't proceed, but the order is created and is set as pending.
		if ( ! $payment_method_id ) {
			// directly send the response before WC marks the order as failed.
			wp_send_json( [
				'result'   => 'pending',
				'messages' => [],
			] );

			exit;
		}

		try {
			$api_intent = $this->onvo_api->get_intent( $payment_intent_id );
			$intent     = \ONVO\Intent_Builder::from_get_intent_response( $api_intent );
		} catch ( \Exception $e ) {
			$this->on_get_intent_error( $order, $payment_intent_id, $e->getMessage() );

			return [
				'result' => 'failure',
			];
		}

		$this->update_order_status_based_on_intent( $order, $intent );
		if ( ! $intent->has_succeeded() ) {
			return [ 'result' => 'failure' ];
		}

		// Save customer_id
		ONVO\set_customer_id_for_wp_user( $intent->get_customer_id(), $order->get_customer_id(), $this->testmode );
		update_user_meta( $order->get_customer_id(), '_onvo_payment_method_id', $intent->get_payment_method_id() );

		wc_reduce_stock_levels( $order_id );
		// Empty cart
		$woocommerce->cart->empty_cart();

		\ONVO\EventScheduler::order_metadata_sync( $order_id );

		// Redirect to the `thank you` page
		return array(
			'order_id'     => (string) $order_id,
			'result'       => 'success',
			'redirect_url' => $order->get_checkout_order_received_url(),
			'redirect'     => $order->get_checkout_order_received_url()
		);
	}

	public function maybe_update_intent_on_cart_totals_update() {
		if ( ! did_action( 'woocommerce_after_calculate_totals' ) ) {
			return;
		}

		$this->update_intent_on_cart_totals_update();
	}

	public function update_intent_on_cart_totals_update() {
		if ( ! $this->is_available() ) {
			return;
		}

		$intent_id = \ONVO\get_intent_id_from_wc_session();
		if ( ! $intent_id ) {
			return;
		}

		try {
			$intent = $this->onvo_api->get_intent( $intent_id );
			$intent = \ONVO\Intent_Builder::from_get_intent_response( $intent );
		} catch ( \Exception $e ) {
			\ONVO\error( 'Failed to obtain Intent',
				[
					'error_message' => $e->getMessage(),
					'method'        => __METHOD__,
					'intent'        => $intent_id
				]
			);

			return;
		}

		if ( ! $intent->requires_payment() ) {
			\ONVO\set_intent_id_for_wc_session( null );
			return;
		}

		$cart_total = WC()->cart->get_total( false );
		$intent->set_amount( \ONVO\wc_number_to_onvo( $cart_total ) );

		try {
			$this->onvo_api->update_intent( $intent );
		} catch (\Exception $e) {
			\ONVO\error( 'Failed to update Intent',
				[
					'error_message' => $e->getMessage(),
					'method'        => __METHOD__,
					'intent'        => $intent_id
				]
			);
		}
	}

	/**
	 * @return bool
	 */
	protected function is_checkout() {
		// if our payment gateway is disabled, we do not have to enqueue JS too
		if ( 'no' === $this->enabled ) {
			return false;
		}

		// we need JavaScript to process a token only on cart/checkout pages, right?
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return false;
		}

		// no reason to enqueue JavaScript if API keys are not set
		if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
			return false;
		}

		// do not work with card details without SSL unless your website is in a test mode
		if ( ! $this->testmode && ! is_ssl() ) {
			return false;
		}

		return true;
	}

	/**
	 * @param WC_Order|\int $order
	 *
	 * @return void
	 */
	public function maybe_add_intent_to_order( $order ) {
		if ( ! isset( $_POST['onvo_intent_id'] ) || ! isset( $_POST['onvo_method_id'] ) ) {
			return;
		}

		$order = wc_get_order( $order );
		if ( ! $order ) {
			return;
		}

		$this->add_intent_and_payment_method_to_order(
			$order,
			wc_sanitize_textarea( $_POST['onvo_intent_id'] ),
			wc_sanitize_textarea( $_POST['onvo_method_id'] ),
			wc_sanitize_textarea( $_POST['onvo_error_msg'] )
		);
	}

	public function add_intent_and_payment_method_to_order(
		WC_Order $order,
		string $intent_id,
		string $method_id,
		string $error_msg = ''
	) {
		$order->update_meta_data( '_onvo_payment_intent_id', wc_sanitize_textarea( $intent_id ) );
		$order->update_meta_data( '_onvo_payment_method_id', wc_sanitize_textarea( $method_id ) );
		$order->update_meta_data( '_onvo_error_msg', wc_sanitize_textarea( $error_msg ) );
		$order->save_meta_data();
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param double $amount_to_charge float The amount to charge.
	 * @param object $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		$this->process_subscription( $renewal_order );
	}

	/**
	 * Retry subscription payment trigger
	 *
	 * WC_Order|int The order on which the payment failed
	 * @return void
	 */
	public function retry_subscription_order( $last_order ) {
		$this->process_subscription( $last_order );
	}

	/**
	 * Process the subscription payments.
	 * Check if renewal_order is an object before to check for renewal
	 *
	 * @param object $renewal_order WC_Order A WC_Order object created to record the renewal payment.
	 *
	 */
	public function process_subscription( $renewal_order ) {
		$renewal_order = wc_get_order( $renewal_order );
		if ( ! $renewal_order ) {
			return;
		}

		$order_id = $renewal_order->get_id();
		if ( ! wcs_order_contains_renewal( $order_id ) ) {
			return;
		}

		$onvo_payment_method_id = $renewal_order->get_meta( '_onvo_payment_method_id' );
		$onvo_customer_id       = \ONVO\get_customer_id( $renewal_order->get_user_id(), $this->testmode );
		try {
			$api_intent = \ONVO\Intent_Builder::from_get_intent_response( $this->onvo_api->create_intent( \ONVO\Intent_Builder::from_renewal( $renewal_order, $onvo_customer_id ) ) );
			$confirmed_intent = \ONVO\Intent_Builder::from_get_intent_response( $this->onvo_api->confirm_intent( $api_intent->get_id(), $onvo_payment_method_id ) );

			$this->add_intent_and_payment_method_to_order( $renewal_order, $confirmed_intent->get_id(), $confirmed_intent->get_payment_method_id() );
			$this->update_order_status_based_on_intent( $renewal_order, $confirmed_intent );
		} catch ( \Exception $exception ) {
			$message = sprintf( 'Pago de la orden #%d ha fallado. Error: %s', $order_id, $exception->getMessage() );
			\ONVO\error( $message );
			$renewal_order->update_status( 'wc-failed', $message );
		}
	}

	private function confirm_order_payment(
		WC_Order $order,
		string $intent_id,
		string $payment_method_id,
		string $customer_id,
		float $amount
	): bool {
		if ( ! $order->payment_complete( $intent_id ) ) {
			return false;
		}

		try {
			$order->set_payment_method( $this->id );
			$order->set_payment_method_title( $this->method_title );
		} catch ( WC_Data_Exception $e ) {
			\ONVO\error( $e->getErrorCode(), [
				'method'            => __METHOD__,
				'order_id'          => $order->get_id(),
				'payment_method_id' => $payment_method_id
			] );

			return false;
		}

		$order->update_meta_data( '_onvo_payment_method_id', $payment_method_id );
		$order->update_meta_data( '_onvo_customer_id', $customer_id );

		$order_note = array(
			__( 'Pago procesado por ONVO.', 'onvo-pay-gateway' ),
			'',
			sprintf( __( 'Intent ID: %s.', 'onvo-pay-gateway' ), $intent_id ),
			sprintf( __( 'Amount: %d.', 'onvo-pay-gateway' ), \ONVO\wc_number_from_onvo( $amount ) ),
		);
		$note       = implode( '<br>', $order_note );
		$order->add_order_note( $note );

		$saved = ! ! $order->save();
		if ( $saved ) {
			/**
			 * @since 0.14.0
			 */
			do_action( 'onvo_pay_order_payment_confirmed', $order->get_id() );
		}

		return $saved;
	}

	public function update_order_status_based_on_intent( WC_Order $order, \ONVO\Intent $intent ) {
		if ( $intent->requires_confirmation() ) {
			return $order->update_status(
				'pending',
				sprintf( __( 'Intent ID %s requiere confirmacion.', 'onvo-pay-gateway' ), $intent->get_id() )
			);
		}

		if ( $intent->requires_payment_method() ) {
			return $order->update_status(
				'failed',
				sprintf( __( 'Intent ID %s requiere metodo de pago.', 'onvo-pay-gateway' ), $intent->get_id() )
			);
		}

		if ( $intent->refunded() ) {
			return $order->update_status(
				'refunded',
				sprintf( __( 'Intent ID %s fue reembolsado.', 'onvo-pay-gateway' ), $intent->get_id() )
			);
		}

		if ( $intent->is_canceled() ) {
			return $order->update_status(
				'cancelled',
				sprintf( __( 'Intent ID %s fue cancelado.', 'onvo-pay-gateway' ), $intent->get_id() )
			);
		}

		if ( $intent->has_succeeded() ) {
			return $this->confirm_order_payment(
				$order,
				$intent->get_id(),
				$intent->get_payment_method_id(),
				$intent->get_customer_id(),
				$intent->get_amount(),
			);
		}
	}

	/**
	 * Remove unused Change payment method button from subscription view on the account page
	 */
	public function wcs_view_subscription_actions( $actions ) {
		unset( $actions['change_payment_method'] );

		return $actions;
	}

  /**
	 * Condition: Base payment gateway validation (parent::is_available())
	 * Condition: Users must be logged in
	 * Condition: Shopping Cart total must be greater than 0
	 * Condition: Store currency must be supported by ONVO (CRC and USD only)
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		if ( ! parent::is_available() ) {
			return false;
		}

		if ( ! \ONVO\Currency::isValid( get_woocommerce_currency() ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function needs_setup(): bool {
		return ( ! $this->publishable_key || ! $this->private_key );
	}

	public function get_onvo_api(): \ONVO\API {
		return $this->onvo_api;
	}

	/**
	 * @param WC_Order $order
	 * @param $payment_intent_id
	 *
	 * @return void
	 */
	protected function handle_invalid_payment_intent_id_on_checkout( WC_Order $order, $payment_intent_id ): void {
		$message = sprintf( 'Payment intent %s inválido', $payment_intent_id );

		$order->add_order_note( $message );
		wc_add_notice( $message, 'error' );
		ONVO\debug(
			$message,
			array(
				'order_id' => $order->get_id(),
				'intent_id' => $payment_intent_id,
			)
		);
	}

	/**
	 * @param WC_Order $order
	 * @param string $payment_intent_id
	 * @param string $error_message
	 *
	 * @return void
	 */
	protected function handle_sdk_error_on_checkout( WC_Order $order, $payment_intent_id, string $error_message ): void {
		$message = sprintf( __( 'El pago de la orden #%s ha fallado. Error: %s', WC_ONVO_PAY_NAME ), $order->get_id(), $error_message );

		wc_add_notice( $message, 'error' );
		$order->add_order_note( $message );
		ONVO\debug(
			$message,
			array(
				'order_id'      => $order->get_id(),
				'intent_id'     => $payment_intent_id,
				'error_message' => $error_message,
			)
		);
	}

	/**
	 * @param WC_Order $order
	 * @param string $payment_intent_id
	 * string string $error_message
	 *
	 * @return void
	 */
		protected function on_get_intent_error( WC_Order $order, string $payment_intent_id, string $error_message ): void {
		$message = sprintf( 'No se pudo obtener el payment intent: %s. Error: %s', $payment_intent_id, $error_message );

		\ONVO\schedule_retry_after_checkout( $order->get_id() );

		wc_add_notice( $message, 'error' );
		$order->add_order_note( $message );
		\ONVO\error( $message,
			[
				'error_message' => $error_message,
				'intent_id'     => $payment_intent_id,
				'order_id'      => $order->get_id()
			]
		);
	}

	/**
	 * @param WC_Customer $customer
	 *
	 * @return array|null
	 */
	public function get_shopper( WC_Customer $customer ): ?array {
		$shopper = null;

		if ( is_user_logged_in() ) {
			$shopper                   = [
				'email'    => $customer->get_email(),
				'phone'    => $customer->get_billing_phone() ?: $customer->get_shipping_phone(),
				'fullName' => $customer->get_first_name() . ' ' . $customer->get_last_name(),
			];
		}

		return $shopper;
	}

	/**
	 * @return void
	 */
	public function localize_script(): void {
		global $wp;

		$cart     = WC()->cart;
		$customer = $cart->get_customer();
		$shopper  = $this->get_shopper( $customer );

		wp_localize_script( WC_ONVO_PAY_NAME, 'onvo_pay_params', [
			'publishableKey'      => $this->publishable_key,
			'apiURL'              => $this->onvo_api->get_api_url(),
			'paymentIntentId'     => $this->get_intent_id(),
			'customerId'          => $this->get_customer_id(),
			'isCheckout'          => $this->is_checkout() ? 'yes' : 'no',
			'id'                  => WC_ONVO_PAY_NAME,
			'cartHasSubscription' => $this->cart_has_subscription,
			'shopper'             => $shopper,
			'debug'               => $this->onvo_debug === 'yes',
			'spinnerBackground'   => $this->spinner_background,
			'spinnerOpacity'      => $this->spinner_opacity,
			'needsPayment'        => WC()->cart->needs_payment() || ( isset( $wp->query_vars['order-pay'] ) && absint( $wp->query_vars['order-pay'] ) ),
		] );
	}

	private function get_private_key(): string {
		return $this->private_key;
	}

	public function get_publishable_key(): string {
		return $this->publishable_key;
	}

	public function get_intent_id(): ?string {
		return $this->intent_id;
	}

	private function set_intent_id( string $intent_id ): void {
		$this->intent_id = $intent_id;
	}

	/**
	 * @return string|null
	 */
	public function get_customer_id(): ?string {
		return $this->customer_id;
	}

	/**
	 * @param string|null $customer_id
	 */
	private function set_customer_id( ?string $customer_id ): void {
		$this->customer_id = $customer_id;
	}
}
