<?php

namespace ONVO\Compatibility\WooCommerce_All_Products_for_Subscriptions;

use ONVO\Price;

/**
 * get subscription terms (interval, intervalCount) from plugin's schema
 *
 * @param array $recurring_price_data
 * @param Price $price
 * @param \WC_Product $product
 *
 * @return array
 */
function maybe_overwrite_recurring_price_data( array $recurring_price_data, Price $price, \WC_Product $product ) {
	if ( $product->is_type( 'bundle' ) ) {
		$cart = wc()->cart->get_cart();
		foreach ( $cart as $cart_item ) {
			if ( $cart_item['product_id'] === $product->get_id() && \WCS_ATT_Product::is_subscription( $cart_item['data'] ) ) {
				list( $interval, $period ) = explode( '_', \WCS_ATT_Cart::get_subscription_scheme( $cart_item ) );

				return array(
					'interval'      => $period,
					'intervalCount' => (int) $interval
				);
			}
		}
	}

	return $recurring_price_data;
}

add_filter( 'onvo_recurring_price_data', __NAMESPACE__ . '\maybe_overwrite_recurring_price_data', 10, 3 );

/**
 * Overwrite price amount for bundle product from cart total to be sent to Onvo api
 *
 * @param float $amount
 * @param \WC_Product $product
 *
 * @return int
 */
function maybe_overwrite_price_amount( float $amount, \WC_Product $product ): int {
	if ( $product->is_type( 'bundle' ) ) {
		$cart = wc()->cart->get_cart();
		foreach ( $cart as $cart_item ) {
			if ( $cart_item['product_id'] === $product->get_id() && \WCS_ATT_Product::is_subscription( $cart_item['data'] ) ) {
				return WC()->cart->get_total( 'api' );
			}
		}
	}

	return $amount;
}

add_filter( 'onvo_price_amount', __NAMESPACE__ . '\maybe_overwrite_price_amount', 10, 2 );
