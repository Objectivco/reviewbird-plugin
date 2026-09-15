<?php
/**
 * Health status scheduler for reviewbird.
 *
 * Uses Action Scheduler to refresh store health status in the background,
 * preventing blocking API calls on page loads.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		add_action( self::ACTION_HOOK, array( $this, 'run_scheduled_check' ) );
		add_action( 'init', array( $this, 'schedule_recurring_check' ) );
	}

	/**
	 * Schedule recurring health check if not already scheduled.
	 */
	public function schedule_recurring_check(): void {
		Scheduler::schedule( self::ACTION_HOOK, array( 'periodic' ), time(), 5 );
	}

	/** Schedule one immediate check after a connection notification. */
	public function schedule_immediate_refresh(): void {
		Scheduler::schedule( self::ACTION_HOOK, array( 'immediate' ), time(), 5 );
	}

	/**
	 * Schedule the next check before making the API request.
	 *
	 * @param string $type Check type. Old jobs have no recognized type and do no API work.
	 */
	public function run_scheduled_check( $type = '' ): void {
		if ( ! in_array( $type, array( 'periodic', 'immediate' ), true ) || ! Scheduler::enabled() ) {
			return;
		}
		if ( 'periodic' === $type ) {
			// Single jobs avoid AS's separate, non-unique recurring replacement path.
			Scheduler::schedule( self::ACTION_HOOK, array( 'periodic' ), time() + self::REFRESH_INTERVAL, 5, false );
		}
		$this->refresh_health_status();
	}

	/**
	 * Refresh health status from the API and store in option.
	 *
	 * Used by Action Scheduler and the admin Refresh button.
	 *
	 * @return array|\WP_Error Fresh status, or an error with the last good cache preserved.
	 */
	public function refresh_health_status() {
		$domain   = wp_parse_url( home_url(), PHP_URL_HOST ) ?? '';
		$endpoint = '/api/woocommerce/health?domain=' . rawurlencode( $domain );
		$response = reviewbird_api_request( $endpoint );

		if ( is_wp_error( $response ) ) {
			$error_data = $response->get_error_data();
			if ( 404 === ( $error_data['status'] ?? null ) && 'not_connected' === ( $error_data['response']['status'] ?? null ) ) {
				$response = $error_data['response'];
			} else {
				$this->log_refresh_error( $response->get_error_message() );
				return $response;
			}
		}

		if ( ! is_array( $response ) || ! is_string( $response['status'] ?? null ) || '' === $response['status'] ) {
			return new \WP_Error( 'reviewbird_invalid_health_status', __( 'The connection response is invalid. Please try again.', 'reviewbird' ) );
		}

		$saved = update_option( 'reviewbird_store_status', $response, false );
		if ( ! $saved && get_option( 'reviewbird_store_status' ) !== $response ) {
			return new \WP_Error( 'reviewbird_health_cache_failed', __( 'The connection status could not be saved. Please try again.', 'reviewbird' ) );
		}

		$store_id = absint( $response['store_id'] ?? 0 );

		if ( $store_id && ! get_option( 'reviewbird_store_id' ) ) {
			update_option( 'reviewbird_store_id', $store_id );
		}

		$this->log_refresh_success( $response['status'] );
		return $response;
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
