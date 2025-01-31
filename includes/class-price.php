<?php

namespace ONVO;

class Price {
	/**
	 * @var Product
	 */
	protected $product;

	/**
	 * @var float
	 */
	protected $amount;

	/**
	 * @var string
	 */
	protected $currency;

	/**
	 * @var string
	 */
	protected $type;

	protected $test_mode;

	public function __construct( Product $product, $type, $test_mode ) {
		if ( ! $product->get_id( $test_mode ) ) {
			throw new \OutOfBoundsException( 'Product must be saved in ONVO.' );
		}

		$this->product   = $product;
		$this->type      = $type;
		$this->test_mode = $test_mode;
	}

	/**
	 * @return float
	 */
	public function get_amount(): float {
		return apply_filters( 'onvo_price_amount', $this->product->get_amount(), $this->product->get_wc_product() );
	}

	/**
	 * @return string
	 */
	public function get_currency(): string {
		return new \ONVO\Currency( get_woocommerce_currency() );
	}

	/**
	 * @return string
	 */
	public function get_product_id(): string {
		return $this->product->get_id( $this->test_mode );
	}

	public function get_nickname(): string {
		return $this->product->get_description();
	}

	public function get_recurring_data(): array {
		return apply_filters( 'onvo_recurring_price_data', [
			'interval'      => \WC_Subscriptions_Product::get_period( $this->product->get_wc_product() ),
			'intervalCount' => (int) \WC_Subscriptions_Product::get_interval( $this->product->get_wc_product() )
		], $this, $this->product->get_wc_product() );
	}

	public function get_type(): string {
		return $this->type;
	}
}
