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
	 * Keep new checks separate from the old queue during upgrade cleanup.
	 */
	private const ACTION_GROUP = 'reviewbird-health';

	/**
	 * WordPress cron hook and completion option for the one-time cleanup.
	 */
	public const CLEANUP_HOOK    = 'reviewbird_cleanup_health_actions';
	private const CLEANUP_OPTION = 'reviewbird_health_actions_cleaned';

	/**
	 * Interval between health checks in seconds (5 minutes).
	 */
	private const REFRESH_INTERVAL = 300;

	/**
	 * Initialize the scheduler.
	 */
	public function init(): void {
		add_action( self::ACTION_HOOK, array( $this, 'run_scheduled_check' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_legacy_actions' ) );
		add_action( 'init', array( $this, 'schedule_recurring_check' ) );
	}

	/**
	 * Schedule recurring health check if not already scheduled.
	 */
	public function schedule_recurring_check(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! get_option( self::CLEANUP_OPTION ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CLEANUP_HOOK );
		}

		if ( ! as_has_scheduled_action( self::ACTION_HOOK, array( true ), self::ACTION_GROUP ) ) {
			as_schedule_recurring_action(
				time(),
				self::REFRESH_INTERVAL,
				self::ACTION_HOOK,
				array( true ),
				self::ACTION_GROUP,
				true,
				5
			);
		}
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
			array( true ),
			self::ACTION_GROUP . '-immediate',
			true,
			5
		);
	}

	/**
	 * Run new checks. Old queued checks have no argument and do no API work.
	 *
	 * @param bool $current_schedule Whether this check uses the new schedule.
	 */
	public function run_scheduled_check( bool $current_schedule = false ): void {
		if ( $current_schedule ) {
			$this->refresh_health_status();
		}
	}

	/**
	 * Delete old health actions and their logs in bounded background batches.
	 */
	public function cleanup_legacy_actions(): void {
		global $wpdb;

		if ( ! function_exists( 'as_get_scheduled_actions' ) || get_option( self::CLEANUP_OPTION ) ) {
			return;
		}

		// Schedule the retry first so a timeout does not stop cleanup.
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CLEANUP_HOOK );
		}

		$query = array(
			'hook'             => self::ACTION_HOOK,
			'group'            => 'reviewbird',
			'per_page'         => 1000,
			'orderby'          => 'action_id',
			'order'            => 'ASC',
			'status'           => array( 'pending', 'complete', 'failed', 'canceled' ),
			// Let recently completed jobs finish their logs and rescheduling.
			'modified'         => time() - self::REFRESH_INTERVAL,
			'modified_compare' => '<=',
		);

		try {
			$store    = \ActionScheduler::store();
			$deadline = microtime( true ) + 10;
			$ids      = as_get_scheduled_actions( $query, 'ids' );
			if ( ! is_array( $ids ) || ! empty( $wpdb->last_error ) ) {
				$this->log_refresh_error( 'Could not read old health actions for cleanup.' );
				return;
			}
			foreach ( $ids as $action_id ) {
				// A queue runner can claim an action after the query above.
				$status = $store->get_status( $action_id );
				if ( 'in-progress' !== $status && ( 'pending' !== $status || ! $store->get_claim_id( $action_id ) ) ) {
					// The store also removes the action's logs through its deletion hook.
					$store->delete_action( $action_id );
				}
				if ( microtime( true ) >= $deadline ) {
					break;
				}
			}

			// Wait for running or claimed actions before marking cleanup complete.
			unset( $query['modified'], $query['modified_compare'], $query['status'] );
			$query['per_page'] = 1;
			if ( array() === as_get_scheduled_actions( $query, 'ids' ) && empty( $wpdb->last_error ) ) {
				update_option( self::CLEANUP_OPTION, true, false );
				wp_clear_scheduled_hook( self::CLEANUP_HOOK );
			}
		} catch ( \Exception $error ) {
			$this->log_refresh_error( 'Health action cleanup failed: ' . $error->getMessage() );
		}
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
