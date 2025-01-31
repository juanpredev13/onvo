<?php

namespace ONVO;

class Intent_Builder {
	public static function from_get_intent_response( array $api_intent ): Intent {
		if ( ! isset( $api_intent['id'] ) || is_null( trim( $api_intent['id'] ) ) ) {
			error( sprintf( 'Invalid intent id: %s. Error: %s', $api_intent['id'], $api_intent['message'] ), [
				'intent_id' => $api_intent['id'],
				'method'    => __METHOD__,
				'message'   => $api_intent['message'],
				'path'      => $api_intent['path']
			] );

			if ( isset( $api_intent['message'] ) ) {
				$message = $api_intent['message'];
				if ( is_array( $message ) ) {
					$message = wp_json_encode( $message );
				}

				throw new \Exception( $message );
			}

			throw new \Exception( sprintf( 'Invalid intent id: %s', $api_intent['id'] ) );
		}

		$intent = new Intent();
		$intent->set_id( $api_intent['id'] );

		if ( isset( $api_intent['charges'] ) ) {
			$intent->set_charges( $api_intent['charges'] );
		}

		if ( Currency::isValid( $api_intent['currency'] ) ) {
			$intent->set_currency( Currency::from( $api_intent['currency'] ) );
		}

		$intent->set_customer_id( self::get_customer_id( $api_intent ) );
		$intent->set_payment_method_id( self::get_payment_method_id( $api_intent ) );
		$intent->set_amount( $api_intent['amount'] );
		$intent->set_status( $api_intent['status'] );
		$intent->set_description( $api_intent['description'] );

		if ( ! empty( $api_intent['metadata'] ) ) {
			$intent->set_metadata( $api_intent['metadata'] );
		}

		return $intent;
	}

	public static function from_cart( \WC_Cart $cart, $currency, $customer_id ): Intent {
		$intent = new Intent();

		$intent->set_amount( wc_number_to_onvo( $cart->get_total( false ) ) );
		$intent->set_description( sprintf( 'WooCommerce Cart ID %s', $cart->get_cart_hash() ) );
		$intent->set_cart_id( $cart->get_cart_hash() );

		if ( Currency::isValid( $currency ) ) {
			$intent->set_currency( Currency::from( $currency ) );
		}

		if ( $customer_id ) {
			$intent->set_customer_id( $customer_id );
		}

		if ( get_intent_id_from_wc_session() ) {
			$intent->set_id( get_intent_id_from_wc_session() );
		}

		return $intent;
	}

	public static function from_renewal( \WC_Order $order, string $onvo_customer_id ): Intent {
		$intent = self::from_order( $order, $onvo_customer_id);
		$intent->set_description( sprintf( 'WooCommerce Renewal Order ID: %s', $order->get_id() ) );

		return $intent;
	}

	public static function from_order( \WC_Order $order, string $onvo_customer_id ): Intent {
		$intent = new Intent();
		$intent->set_amount( wc_number_to_onvo( $order->get_total( false ) ) );
		$intent->set_customer_id( $onvo_customer_id );
		$intent->set_description( sprintf( 'WooCommerce Order ID %s', $order->get_order_number() ) );
		$intent->set_order_id( $order->get_id() );
		$intent->set_order_number( $order->get_order_number() );
		$intent->set_cart_id( $order->get_cart_hash() );
		if ( Currency::isValid( $order->get_currency() ) ) {
			$intent->set_currency( Currency::from( $order->get_currency() ) );
		}

		$intent_id = get_intent_id_for_order( $order );
		if ( $intent_id ) {
			$intent->set_id( $intent_id );
		}

		return $intent;
	}

	private static function get_customer_id( array $api_intent ): ?string {
		if ( isset( $api_intent['customerId'] ) ) {
			return $api_intent['customerId'];
		} elseif ( isset( $api_intent['customer']['id'] ) ) {
			return $api_intent['customer']['id'];
		}

		return null;
	}

	private static function get_payment_method_id( array $api_intent ): ?string {
		if ( isset( $api_intent['paymentMethodId'] ) ) {
			return $api_intent['paymentMethodId'];
		} elseif ( isset( $api_intent['paymentMethod']['id'] ) ) {
			return $api_intent['paymentMethod']['id'];
		}

		return null;
	}
}
