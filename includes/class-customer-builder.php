<?php

namespace ONVO;

class Customer_Builder {
	/**
	 * @var \WC_Customer|\WC_Order
	 */
	private $customer;

	/**
	 * @param \WC_Customer|\WC_Order $customer
	 *
	 * @return Customer_Builder
	 */
	public static function from( $customer ): Customer_Builder {
		return new self( $customer );
	}

	private function __construct( $customer ) {
		$this->customer = $customer;
	}

	/**
	 * @throws \Exception
	 */
	public function get_array(): array {
		// Check if the object is a WC_Customer
		if ( $this->customer instanceof \WC_Customer ) {
			return $this->build_from_customer( $this->customer );
		}

		// Check if the object is a WC_Order
		if ( $this->customer instanceof \WC_Order ) {
			return $this->build_from_order( $this->customer );
		}

		// Handle other types or throw an error
		throw new \Exception( sprintf( 'Unsupported customer type: %s', get_class( $this->customer ) ) );
	}

	private function build_from_customer( \WC_Customer $customer ): array {
		$array = [
			'email'   => $customer->get_email(),
			'name'    => $customer->get_display_name(),
			'address' => [
				'city'       => $customer->get_billing_city(),
				'country'    => $customer->get_billing_country(),
				'line1'      => $customer->get_billing_address_1(),
				'line2'      => $customer->get_billing_address_2(),
				'postalCode' => $customer->get_billing_postcode(),
				'state'      => $customer->get_billing_state(),
			]
		];

		if ( $customer->has_shipping_address() ) {
			$array['shipping'] = [
				'name'    => 'woo :: ' . $customer->get_email(),
				'address' => [
					'city'       => $customer->get_shipping_city(),
					'country'    => $customer->get_shipping_country(),
					'line1'      => $customer->get_shipping_address_1(),
					'line2'      => $customer->get_shipping_address_2(),
					'postalCode' => $customer->get_shipping_postcode(),
					'state'      => $customer->get_shipping_state(),
				],
			];
		}

		return $array;
	}

	private function build_from_order( \WC_Order $order ): array {
		$array = [
			'email'   => $order->get_billing_email(),
			'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'address' => [
				'city'       => $order->get_billing_city(),
				'country'    => $order->get_billing_country(),
				'line1'      => $order->get_billing_address_1(),
				'line2'      => $order->get_billing_address_2(),
				'postalCode' => $order->get_billing_postcode(),
				'state'      => $order->get_billing_state(),
			]
		];

		if ( $order->has_shipping_address() ) {
			$array['shipping'] = [
				'name'    => 'woo :: ' . $order->get_billing_email(),
				'address' => [
					'city'       => $order->get_shipping_city(),
					'country'    => $order->get_shipping_country(),
					'line1'      => $order->get_shipping_address_1(),
					'line2'      => $order->get_shipping_address_2(),
					'postalCode' => $order->get_shipping_postcode(),
					'state'      => $order->get_shipping_state(),
				],
			];
		}

		return $array;
	}
}
