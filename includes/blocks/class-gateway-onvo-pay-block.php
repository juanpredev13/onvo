<?php

namespace ONVO;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * ONVO Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WC_Gateway_ONVO_Pay_Block extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var \WC_Gateway_ONVO_Pay
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = WC_ONVO_PAY_NAME;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_' . $this->name . '_settings', [] );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		if ( ! \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
			return [];
		}

		wp_register_script( 'onvo-pay-sdk-js', '//sdk.onvopay.com/sdk.js', [], WC_ONVO_PAY_VERSION, true );

		$script_asset_path = WC_ONVO_PAY_PLUGIN_PATH . 'assets/js/build/block.asset.php';
		$script_asset = file_exists( $script_asset_path )
			? require( $script_asset_path )
			: array(
				'dependencies' => array(),
				'version'      => WC_ONVO_PAY_VERSION
			);

		$script_url = WC_ONVO_PAY_PLUGIN_URL . '/assets/js/build/block.js';

		wp_register_script(
			WC_ONVO_PAY_NAME,
			$script_url,
			array_merge( $script_asset['dependencies'], [ 'onvo-pay-sdk-js' ] ),
			$script_asset['version'],
			true
		);

		return [ WC_ONVO_PAY_NAME ];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		if ( ! \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default() ) {
			return [];
		}

		$shopper = null;
		if ( WC()->cart ) {
			$shopper = $this->get_gateway()->get_shopper( WC()->cart->get_customer() );
		}

		return [
			'id'              => WC_ONVO_PAY_NAME,
			'title'           => $this->get_setting( 'title' ),
			'description'     => $this->get_setting( 'description' ),
			'supports'        => $this->get_supported_features(),
			'publishableKey'  => $this->get_gateway()->get_publishable_key(),
			'paymentIntentId' => $this->get_gateway()->get_intent_id(),
			'customerId'      => $this->get_gateway()->get_customer_id(),
			'shopper'         => $shopper,
			'debug'           => $this->get_setting( 'onvo_debug' )
		];
	}

	public function get_supported_features(): array {
		return array_filter( $this->get_gateway()->supports, array( $this->get_gateway(), 'supports' ) );
	}

	protected function get_gateway(): \WC_Gateway_ONVO_Pay {
		if ( ! $this->gateway ) {
			$this->gateway = new \WC_Gateway_ONVO_Pay();
			$this->gateway->maybe_create_intent();
		}

		return $this->gateway;
	}
}
