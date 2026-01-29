<?php
/**
 * Health status scheduler for reviewbird.
 *
 * Uses Action Scheduler to refresh store health status in the background,
 * preventing blocking API calls on page loads.
 *
 * @package reviewbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace reviewbird\Integration;

/**
 * Health scheduler class.
 */
class HealthScheduler {

	/**
	 * Action hook name for health status refresh.
	 */
	private const ACTION_HOOK = 'reviewbird_refresh_health_status';

	/**
	 * Interval between health checks in seconds (5 minutes).
	 */
	private const REFRESH_INTERVAL = 300;

	/**
	 * Initialize the scheduler.
	 */
	public function init(): void {
		add_action( self::ACTION_HOOK, array( $this, 'refresh_health_status' ) );
		add_action( 'init', array( $this, 'schedule_recurring_check' ) );
	}

	/**
	 * Schedule recurring health check if not already scheduled.
	 */
	public function schedule_recurring_check(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		// Schedule recurring check if not already scheduled.
		if ( ! as_has_scheduled_action( self::ACTION_HOOK ) ) {
			as_schedule_recurring_action(
				time(),
				self::REFRESH_INTERVAL,
				self::ACTION_HOOK,
				array(),
				'reviewbird'
			);
		}

		// If store is connected but status option is empty, schedule immediate refresh.
		// This handles plugin updates, option clears, or first-time activation.
		$store_id = get_option( 'reviewbird_store_id' );
		$status   = get_option( 'reviewbird_store_status' );

		if ( $store_id && ! empty( $status ) ) {
			return;
		}

		$this->schedule_immediate_refresh();
	}

	/**
	 * Schedule an immediate health status refresh.
	 *
	 * Used after store connection to quickly populate the health status.
	 */
	public function schedule_immediate_refresh(): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		as_schedule_single_action(
			time(),
			self::ACTION_HOOK,
			array(),
			'reviewbird'
		);
	}

	/**
	 * Refresh health status from the API and store in option.
	 *
	 * This runs in the background via Action Scheduler.
	 */
	public function refresh_health_status(): void {
		$domain   = wp_parse_url( home_url(), PHP_URL_HOST ) ?? '';
		$endpoint = '/api/woocommerce/health?domain=' . rawurlencode( $domain );
		$response = reviewbird_api_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			$this->log_refresh_error( $response->get_error_message() );
			return;
		}

		update_option( 'reviewbird_store_status', $response, false );

		$this->log_refresh_success( $response['status'] ?? 'unknown' );
	}

	/**
	 * Log a successful refresh.
	 *
	 * @param string $status The health status.
	 */
	private function log_refresh_success( string $status ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				sprintf( 'Health status refreshed: %s', $status ),
				array( 'source' => 'reviewbird' )
			);
		}
	}

	/**
	 * Log a refresh error.
	 *
	 * @param string $error_message The error message.
	 */
	private function log_refresh_error( string $error_message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning(
				sprintf( 'Health status refresh failed: %s', $error_message ),
				array( 'source' => 'reviewbird' )
			);
		}
	}
}
