<?php

namespace ONVO;

class IntentService {
	private API $api;

	public function __construct( API $api ) {
		$this->api = $api;
	}

	/**
	 * @throws API_Exception
	 * @throws \Exception
	 */
	public function sync_metadata_from_order( int $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$intent_id = get_intent_id_for_order( $order );
		if ( ! $intent_id ) {
			return;
		}

		$intent = $this->api->get_intent( $intent_id );
		$intent = Intent_Builder::from_get_intent_response( $intent );

		$intent->set_order_id( $order_id );
		$intent->set_cart_id( $order->get_cart_hash() );
		$intent->set_order_number( $order->get_order_number() );
		$intent->set_description( $this->build_intent_description( $order ) );

		$this->api->update_intent( $intent );
	}

	private function build_intent_description( \WC_Order $order ): string {
		return sprintf( 'WooCommerce Order ID #%s', $order->get_order_number() );
	}
}
