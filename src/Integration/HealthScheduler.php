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
	private const ACTION_HOOK    = 'reviewbird_refresh_health_status';
	public const CLEANUP_HOOK    = 'reviewbird_cleanup_health_actions';
	private const CLEANUP_OPTION = 'reviewbird_health_cleanup_status';

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
		if ( ! class_exists( '\ActionScheduler' ) || ! \ActionScheduler::is_initialized() ) {
			return;
		}
		if ( ! get_option( self::CLEANUP_OPTION ) ) {
			wp_clear_scheduled_hook( self::CLEANUP_HOOK );
			if ( as_get_scheduled_actions( array( 'hook' => self::ACTION_HOOK, 'per_page' => 1 ), 'ids' ) ) {
				// Large queues must be canceled in the background, not on a page request.
				if ( ! as_next_scheduled_action( self::CLEANUP_HOOK ) ) {
					as_enqueue_async_action( self::CLEANUP_HOOK, array(), 'reviewbird', true, 0 );
				}
				return;
			}
			// A new store has no health actions to remove.
			update_option( self::CLEANUP_OPTION, 'complete', false );
		}
		if ( ! as_next_scheduled_action( self::ACTION_HOOK, array( 'periodic' ), 'reviewbird-health' ) ) {
			as_schedule_recurring_action( time(), self::REFRESH_INTERVAL, self::ACTION_HOOK, array( 'periodic' ), 'reviewbird-health', true, 5 );
		}
		if ( 'complete' !== get_option( self::CLEANUP_OPTION ) && ! as_next_scheduled_action( self::CLEANUP_HOOK ) ) {
			as_schedule_single_action( time() + 60, self::CLEANUP_HOOK, array(), 'reviewbird', true, 0 );
		}
	}

	/** Schedule one immediate check after a connection notification. */
	public function schedule_immediate_refresh(): void {
		if ( ! class_exists( '\ActionScheduler' ) || ! \ActionScheduler::is_initialized() ) {
			return;
		}
		if ( ! as_next_scheduled_action( self::ACTION_HOOK, array( 'immediate' ), 'reviewbird-health-immediate' ) ) {
			as_enqueue_async_action( self::ACTION_HOOK, array( 'immediate' ), 'reviewbird-health-immediate', true, 5 );
		}
	}

	/**
	 * Run current checks. Old jobs without a recognized type do no API work.
	 *
	 * @param string $type Check type. Old jobs have no recognized type and do no API work.
	 */
	public function run_scheduled_check( $type = '' ): void {
		if ( ! in_array( $type, array( 'periodic', 'immediate' ), true ) ) {
			return;
		}
		$this->refresh_health_status();
	}

	/** Remove old health actions and their logs in small batches after an upgrade. */
	public function cleanup_legacy_actions(): void {
		if ( 'complete' === get_option( self::CLEANUP_OPTION ) ) {
			return;
		}
		try {
			if ( ! get_option( self::CLEANUP_OPTION ) ) {
				as_unschedule_all_actions( self::ACTION_HOOK );
				as_unschedule_all_actions( self::CLEANUP_HOOK );
				update_option( self::CLEANUP_OPTION, 'pending', false );
				$this->schedule_recurring_check();
			}
			$ids = as_get_scheduled_actions(
				array(
					'hook'     => self::ACTION_HOOK,
					'per_page' => 1000,
					'orderby'  => 'date',
					'order'    => 'ASC',
				),
				'ids'
			);
			$store     = \ActionScheduler::store();
			$deadline  = microtime( true ) + 10;
			$remaining = count( $ids ) === 1000;
			foreach ( $ids as $id ) {
				$status = $store->get_status( $id );
				if ( 'in-progress' === $status ) {
					$remaining = true;
				} elseif ( 'pending' !== $status ) {
					// AS also deletes the logs. Keep the new pending health checks.
					$store->delete_action( $id );
				}
				if ( microtime( true ) >= $deadline ) {
					$remaining = true;
					break;
				}
			}
			if ( ! $remaining ) {
				update_option( self::CLEANUP_OPTION, 'complete', false );
				return;
			}
		} catch ( \Exception $error ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning(
					sprintf( 'Health status refresh failed: %s', $error->getMessage() ),
					array( 'source' => 'reviewbird' )
				);
			}
		}
		as_schedule_single_action( time() + 60, self::CLEANUP_HOOK, array(), 'reviewbird', false, 0 );
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
			if ( 404 !== ( $error_data['status'] ?? null ) || 'not_connected' !== ( $error_data['response']['status'] ?? null ) ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->warning(
						sprintf( 'Health status refresh failed: %s', $response->get_error_message() ),
						array( 'source' => 'reviewbird' )
					);
				}
				return $response;
			}
			$response = $error_data['response'];
		}

		if ( ! is_array( $response ) || ! is_string( $response['status'] ?? null ) || '' === $response['status'] ) {
			return new \WP_Error( 'reviewbird_invalid_health_status', __( 'The connection response is invalid. Please try again.', 'reviewbird' ) );
		}

		$saved = update_option( 'reviewbird_store_status', $response, false );
		if ( ! $saved && get_option( 'reviewbird_store_status' ) !== $response ) {
			return new \WP_Error( 'reviewbird_health_cache_failed', __( 'The connection status could not be saved. Please try again.', 'reviewbird' ) );
		}
		set_transient( 'reviewbird_admin_health_status', $response, self::REFRESH_INTERVAL );

		$store_id = absint( $response['store_id'] ?? 0 );

		if ( $store_id && ! get_option( 'reviewbird_store_id' ) ) {
			update_option( 'reviewbird_store_id', $store_id );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				sprintf( 'Health status refreshed: %s', $response['status'] ),
				array( 'source' => 'reviewbird' )
			);
		}
		return $response;
	}
}
