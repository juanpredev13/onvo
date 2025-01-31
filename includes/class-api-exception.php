<?php

namespace ONVO;

class API_Exception extends \Exception {
	/**
	 * @var mixed
	 */
	private $response;

	/**
	 * API_Exception constructor.
	 *
	 * @param string $message
	 * @param mixed $response
	 */
	public function __construct( string $message, $response ) {
		parent::__construct( $message );

		$this->response = $response;
	}

	/**
	 * @retur mixed
	 */
	public function get_response() {
		return $this->response;
	}
}
