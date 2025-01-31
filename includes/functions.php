<?php

namespace ONVO;

function get_customer_id( int $user_id, $test_mode ) {
	$onvo_customer_id_key = $test_mode ? '_onvo_customer_id_test' : '_onvo_customer_id_live';

	return get_user_meta( $user_id, $onvo_customer_id_key, true );
}

function set_customer_id_for_wp_user( $onvo_customer_id, $wp_user_id, $test_mode ) {
	$onvo_customer_id_key = $test_mode ? '_onvo_customer_id_test' : '_onvo_customer_id_live';

	return update_user_meta( $wp_user_id, $onvo_customer_id_key, $onvo_customer_id );
}

function get_intent_id_from_wc_session(): ?string {
	return WC()->session->get( 'onvo_intent_id' );
}

/**
 * @param string|null $intent_id
 *
 * @return void
 */
function set_intent_id_for_wc_session( ?string $intent_id ) {
	WC()->session->set( 'onvo_intent_id', $intent_id );
}

function get_intent_id_for_order( $order ): ?string {
	$order = wc_get_order( $order );
	if ( ! $order ) {
		return null;
	}

	return $order->get_meta( '_onvo_payment_intent_id' );
}

function set_intent_id_for_order( $order, string $intent_id ) {
	$order = wc_get_order( $order );
	if ( ! $order ) {
		return;
	}

	$order->update_meta_data( '_onvo_payment_intent_id', wc_clean( $intent_id ) );
	$order->save_meta_data();
}

function get_payment_method_id_from_order( $order ): ?string {
	$order = wc_get_order( $order );
	if ( ! $order ) {
		return null;
	}

	return $order->get_meta( '_onvo_payment_method_id' );
}

function set_payment_method_id_for_order( $order, string $payment_method_id ) {
	$order = wc_get_order( $order );
	if ( ! $order ) {
		return;
	}

	$order->update_meta_data( '_onvo_payment_method_id', wc_clean( $payment_method_id ) );
	$order->save_meta_data();
}

function get_customer_id_from_order( $order ): ?string {
	$order = wc_get_order( $order );
	if ( ! $order ) {
		return null;
	}

	return $order->get_meta( '_onvo_customer_id' );
}

function set_customer_id_for_order( $order, string $customer_id ) {
	$order = wc_get_order( $order );
	if ( ! $order ) {
		return;
	}

	$order->update_meta_data( '_onvo_customer_id', wc_clean( $customer_id ) );
	$order->save_meta_data();
}

/**
 * Prepare WC total or number to ONVO api
 *
 * @param $number
 *
 * @return float
 */
function wc_number_to_onvo( $number ): float {
	return $number * 100;
}

/**
 * Format from ONVO to WC
 *
 * @param $number
 *
 * @return float
 */
function wc_number_from_onvo( $number ): float {
	return wc_format_decimal( $number / 100 );
}
