<?php

namespace ONVO\Compatibility\WooCommerce_Product_Bundles;

use ONVO\Price;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Allow only one bundle in cart
 *
 * @param bool $allow
 * @param \WC_Cart $cart
 *
 * @return bool
 */
function allow_bundle_in_cart( bool $allow, \WC_Cart $cart ): bool {
	$cart_items    = $cart->get_cart_contents();
	$bundles_count = 0;
	foreach ( $cart_items as $cart_item ) {
		if ( $cart_item['data']->is_type( 'bundle' ) && $cart_item['quantity'] === 1 ) {
			$bundles_count ++;
			break;
		}
	}

	return $bundles_count === 1 ? true : $allow;
}

add_filter( 'onvo_allow_multiple_products_in_cart', __NAMESPACE__ . '\allow_bundle_in_cart', 100, 2 );


/**
 * Disallow creating price for bundled item
 *
 * @param array $allow
 * @param array $item
 *
 * @return bool
 */
function disallow_create_price_for_bundled_item( bool $allow, array $item ): bool {
	return wc_pb_is_bundled_cart_item( $item ) ? false : $allow;
}

add_filter( 'onvo_allow_create_price_for_product', __NAMESPACE__ . '\disallow_create_price_for_bundled_item', 100, 2 );
