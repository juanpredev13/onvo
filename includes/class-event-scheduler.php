<?php

namespace ONVO;

class EventScheduler {
	public static function order_metadata_sync( int $order_id ) {
		self::schedule_single_action( 'sync_order_metadata', [ 'order_id' => $order_id ] );
	}

	public static function customer_sync( int $order_id ) {
		self::schedule_single_action( 'sync_customer_data', [ 'order_id' => $order_id ] );
	}

	private static function schedule_single_action(
		string $hook,
		array $args = [],
		int $time = 0,
		bool $force = false
	) {
		$time  = $time ?: time();
		$group = WC_ONVO_PAY_NAME;
		$hook  = 'ONVO\\' . sanitize_key( $hook );

		if ( ! as_next_scheduled_action( $hook, $args, $group ) || $force ) {
			as_schedule_single_action( $time, $hook, $args, $group );
		}
	}
}
