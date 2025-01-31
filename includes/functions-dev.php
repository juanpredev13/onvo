<?php

namespace ONVO;

/**
 * debug with ONVO context
 *
 * @param string $message
 * @param array $context
 */
function debug( string $message, array $context = [] ): void {
	wc_get_logger()->debug( $message,
		wp_parse_args( $context, array( 'source' => WC_ONVO_PAY_NAME . '-' . WC_ONVO_PAY_VERSION . '-debug' ) ) );
}

function error( string $message, array $context = [] ): void {
	wc_get_logger()->error( $message,
		wp_parse_args( $context, array( 'source' => WC_ONVO_PAY_NAME . '-' . WC_ONVO_PAY_VERSION . '-error' ) ) );
}
