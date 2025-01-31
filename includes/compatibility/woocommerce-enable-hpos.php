<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * ONVO_hpos_compatibility_plugin
 *
 * @return void
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			WC_ONVO_PAY_MAIN_FILE,
			true
		);
	}
} );
