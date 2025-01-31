<?php

namespace ONVO;

class Product {
	/**
	 * @var null|string
	 */
	private $id;

	private $price_id;

	/**
	 * @var \WC_Product
	 */
	protected $wc_product;

	public function __construct( int $wc_product_id ) {
		$wc_product = wc_get_product( $wc_product_id );

		if ( ! $wc_product ) {
			throw new \OutOfBoundsException( 'Invalid wc_product.' );
		}

		$this->wc_product = $wc_product;
	}

	/**
	 * @param string $id
	 */
	public function set_id( string $id, $test_mode ): void {
		$onvo_product_id_key = $test_mode ? '_onvo_product_id_test' : '_onvo_product_id_live';
		$this->wc_product->update_meta_data( $onvo_product_id_key, $id );
		$this->wc_product->save_meta_data();
		$this->id = $id;
	}


	/**
	 * @return string
	 */
	public function get_id( $test_mode ): string {
		if ( ! $this->id ) {
			$onvo_customer_id_key = $test_mode ? '_onvo_product_id_test' : '_onvo_product_id_live';
			$this->id             = $this->wc_product->get_meta( $onvo_customer_id_key );
		}

		return $this->id;
	}

	/**
	 * @param string $id
	 */
	public function set_price_id( string $price_id, $test_mode ): void {
		$onvo_customer_id_key = $test_mode ? '_onvo_price_id_test' : '_onvo_price_id_live';
		$this->wc_product->update_meta_data( $onvo_customer_id_key, $price_id );
		$this->wc_product->save_meta_data();
		$this->price_id = $price_id;
	}

	/**
	 * @return string
	 */
	public function get_price_id( $test_mode ): string {
		if ( ! $this->price_id ) {
			$onvo_price_id_key = $test_mode ? '_onvo_price_id_test' : '_onvo_price_id_live';
			$this->price_id    = $this->wc_product->get_meta( $onvo_price_id_key );
		}

		return $this->price_id;
	}

	public function get_description(): string {
		return $this->wc_product->get_description() || '';
	}

	public function get_images(): array {
		$image_ids = array_merge( [ $this->wc_product->get_image_id() ], $this->wc_product->get_gallery_image_ids() );
		$images    = [];

		foreach ( $image_ids as $image_id ) {
			$image = wp_get_attachment_image_src( $image_id, 'full' );

			if ( $image ) {
				$images[] = $image[0];
			}
		}

		return $images;
	}

	public function is_active(): bool {
		return $this->wc_product->is_purchasable();
	}

	public function is_shippable() {
		return $this->wc_product->needs_shipping();
	}

	public function get_name() {
		return $this->wc_product->get_formatted_name();
	}

	public function get_package_dimensions() {
		return [
			'length' => $this->wc_product->get_length( 'edit' ) ? (float) $this->wc_product->get_length( 'edit' ) : 0,
			'width'  => $this->wc_product->get_width( 'edit' ) ? (float) $this->wc_product->get_width( 'edit' ) : 0,
			'height' => $this->wc_product->get_height( 'edit' ) ? (float) $this->wc_product->get_height( 'edit' ) : 0,
			'weight' => $this->wc_product->get_weight( 'edit' ) ? (float) $this->wc_product->get_weight( 'edit' ) : 0,
		];
	}

	public function get_amount() {
		return $this->wc_product->get_price();
	}

	/**
	 * @return \WC_Product
	 */
	public function get_wc_product(): \WC_Product {
		return $this->wc_product;
	}
}
