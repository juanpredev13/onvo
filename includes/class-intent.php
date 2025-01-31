<?php

namespace ONVO;

class Intent {
	private $amount;
	private $currency;
	private $customer_id;
	private $description;
	private $id;
	private $status;
	private $charges;
	private $payment_method_id;
	private array $metadata = [];

	public function get_amount(): float {
		return $this->amount;
	}

	public function set_amount( float $amount ) {
		$this->amount = $amount;
	}

	public function get_currency(): string {
		return $this->currency->getValue();
	}

	public function set_currency( Currency $currency ) {
		$this->currency = $currency;
	}

	public function get_customer_id(): ?string {
		return $this->customer_id;
	}

	public function set_customer_id( ?string $customer_id ) {
		$this->customer_id = $customer_id;
	}

	public function get_description(): ?string {
		return $this->description;
	}

	public function set_description( ?string $description ) {
		$this->description = $description;
	}

	public function set_id( string $intent_id ) {
		$this->id = $intent_id;
	}

	public function get_id(): ?string {
		return $this->id;
	}

	public function get_charges(): array {
		return $this->charges;
	}

	public function set_charges( array $charges ) {
		$this->charges = $charges;
	}

	public function get_last_charge_id(): ?string {
		if ( empty( $this->charges ) ) {
			return null;
		}

		return $this->charges[0]['id'];
	}

	public function get_payment_method_id(): ?string {
		return $this->payment_method_id;
	}

	public function set_payment_method_id( ?string $payment_method_id ) {
		$this->payment_method_id = $payment_method_id;
	}

	/*
	 * @return string
	 *
	 * @todo change to enum
	 */
	public function set_status( string $status ): void {
		$this->status = $status;
	}

	public function get_status(): string {
		return $this->status;
	}

	public function set_metadata( array $metadata ) {
		$this->metadata = array_map( 'strval', $metadata );
	}

	public function get_metadata(): array {
		return $this->metadata;
	}

	public function set_metadata_key( string $key, $value ) {
		$this->metadata[ $key ] = (string) $value;
	}

	public function set_order_id( int $order_id ) {
		$this->set_metadata_key( 'WooCommerce Order ID', $order_id );
	}

	public function set_cart_id( string $cart_id ) {
		$this->set_metadata_key( 'WooCommerce Cart ID', $cart_id );
	}

	public function set_order_number(string $order_number) {
		$this->set_metadata_key( 'WooCommerce Order Number', $order_number );
	}

	public function has_succeeded(): bool {
		return $this->status === 'succeeded';
	}

	public function requires_confirmation(): bool {
		return $this->status === 'requires_confirmation';
	}

	public function requires_payment_method(): bool {
		return $this->status === 'requires_payment_method';
	}

	public function refunded(): bool {
		return $this->status === 'refunded';
	}

	public function is_canceled(): bool {
		return $this->status === 'canceled';
	}

	public function requires_payment(): bool {
		if ( $this->is_canceled() || $this->refunded() || $this->has_succeeded() ) {
			return false;
		}

		return true;
	}
}
