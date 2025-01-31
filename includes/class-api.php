<?php

namespace ONVO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class API {
	private $private_key;
	private $api_url = 'https://api.onvopay.com';
	const API_VERSION = 'v1';

	/**
	 * Instance variable
	 *
	 * @var $instance The reference the *Singleton* instance of this class
	 */
	private static $instance;

	/**
	 * @param string $private_key
	 */
	public function set_private_key( string $private_key ): void {
		$this->private_key = $private_key;
	}

	public static function get_instance(): API {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param Intent $intent
	 *
	 * @return array
	 *
	 * @throws API_Exception
	 */
	public function create_intent( Intent $intent ): array {
		$args = [
			'amount'      => $intent->get_amount(),
			'currency'    => $intent->get_currency(),
			'description' => $intent->get_description()
		];

		if ( $intent->get_customer_id() ) {
			$args['customerId'] = $intent->get_customer_id();
		}

		if ( ! empty( $intent->get_metadata() ) ) {
			$args['metadata'] = $intent->get_metadata();
		}

		$request = $this->request( 'payment-intents', $args, 'POST' );
		if ( is_wp_error( $request ) || 201 !== wp_remote_retrieve_response_code( $request ) ) {
			$this->throw_on_failed_request( $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * @param string $intent_id
	 * @param string $method_id
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function confirm_intent( $intent_id, $method_id ): array {
		$args = [
			'paymentMethodId' => $method_id,
		];

		$request = $this->request( sprintf( 'payment-intents/%s/confirm', $intent_id ), $args, 'POST' );
		if ( is_wp_error( $request ) || 201 !== wp_remote_retrieve_response_code( $request ) ) {
			$this->throw_on_failed_request( $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * @param string $intent_id
	 *
	 * @return array
	 * @throws API_Exception
	 */
	public function get_intent( string $intent_id ): array {
		$request = $this->request( sprintf( 'payment-intents/%s', $intent_id ) );
		if ( is_wp_error( $request ) || ( 200 !== wp_remote_retrieve_response_code( $request ) && 404 !== wp_remote_retrieve_response_code( $request ) ) ) {
			$this->throw_on_failed_request( $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * @param Intent $intent
	 *
	 * @return mixed
	 * @throws API_Exception
	 */
	public function update_intent( Intent $intent ) {
		$args = [];

		if ( ! empty( $intent->get_description() ) ) {
			$args['description'] = $intent->get_description();
		}
		if ( ! $intent->has_succeeded() ) {
			$args['amount'] = $intent->get_amount();
		}
		if ( ! empty( $intent->get_metadata() ) ) {
			$args['metadata'] = $intent->get_metadata();
		}

		$request = $this->request( sprintf( 'payment-intents/%s', $intent->get_id() ), $args, 'POST' );
		if ( is_wp_error( $request ) || ( 201 !== wp_remote_retrieve_response_code( $request ) ) ) {
			$this->throw_on_failed_request( $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * @throws API_Exception
	 */
	public function get_customer( string $customer_id ) {
		$request = $this->request( sprintf( 'customers/%s', $customer_id ) );
		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			$this->throw_on_failed_request( $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * @param array $customer
	 *
	 * @return mixed
	 * @throws API_Exception
	 */
	public function create_customer( array $customer ) {
		$request = $this->request( 'customers', $customer, 'POST' );
		if( is_wp_error( $request ) || 201 !== wp_remote_retrieve_response_code( $request ) ) {
			$this->throw_on_failed_request( $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * @see Customer_Builder
	 * @throws API_Exception
	 */
	public function update_customer( string $customer_id , array $customer_data ) {
		$request = $this->request( 'customers/' . $customer_id, $customer_data, 'POST' );
		if ( is_wp_error( $request ) || 201 !== wp_remote_retrieve_response_code( $request ) ) {
			$this->throw_on_failed_request( $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * @return string
	 */
	public function get_api_url(): string {
		return $this->api_url;
	}

	/**
	 * @deprecated
	 */
	public function format_amount( $amount ) {
		return $amount * 100;
	}

	/**
	 * Checks if the request was successful, if not throws an exception.
	 * @param \WP_Error|array $request
	 *
	 * @throws API_Exception
	 */
	protected function throw_on_failed_request( $request ): void {
		$message         = is_wp_error( $request ) ? $request->get_error_message() : wp_remote_retrieve_body( $request );
		$message_decoded = json_decode( $message );
		if ( $message_decoded ) {
			$message = $message_decoded->message ?? $message_decoded->error ?? $message;

			if ( is_array( $message ) ) {
				$message = implode( ', ', $message );
			}
		}

		throw new API_Exception( $message, $request );
	}

	/**
	 * @param string $path
	 * @param array $body
	 * @param string $method
	 *
	 * @return array|\WP_Error
	 */
	protected function request( string $path, array $body = [], string $method = 'GET' ) {
		$url     = sprintf( '%s/%s/%s', $this->get_api_url(), self::API_VERSION, $path );
		$headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->private_key ];
		$body    = json_encode( $body );

		switch ( $method ) {
			case 'POST':
				$request = wp_safe_remote_post( $url, [ 'headers' => $headers, 'body' => $body ] );
				break;
			case 'GET':
			default:
				$request = wp_safe_remote_get( $url, [ 'headers' => $headers ] );
		}

		return $request;
	}

	/**
	 * Disable the cloning of this class.
	 *
	 * @return void
	 */
	final public function __clone() {
		throw new \Exception( 'Feature disabled.' );
	}

	/**
	 * Disable the wakeup of this class.
	 *
	 * @return void
	 */
	final public function __wakeup() {
		throw new \Exception( 'Feature disabled.' );
	}
}
