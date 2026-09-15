<?php
/**
 * Shared scheduling and upgrade cleanup.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep at most one pending job for each hook and argument list.
 */
class Scheduler {
	const GROUP                  = 'reviewbird-v2';
	const CLEANUP_HOOK           = 'reviewbird_cleanup_health_actions';
	const DISABLED_OPTION        = 'reviewbird_scheduler_disabled';
	private const CLEANUP_OPTION = 'reviewbird_scheduler_cleanup_complete';
	private const LEGACY_GROUPS  = array( 'reviewbird', 'reviewbird-health', 'reviewbird-health-immediate' );
	private const JOB_HOOKS      = array( 'reviewbird_refresh_health_status', 'reviewbird_refresh_schema_reviews', self::CLEANUP_HOOK );

	/**
	 * Prevent reentry on the same database connection.
	 *
	 * @var bool
	 */
	private static $locked = false;

	/** Register background cleanup and stop old recurring jobs. */
	public function init(): void {
		add_action( 'init', array( $this, 'ensure_cleanup' ), 20 );
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup' ) );
		add_action( 'action_scheduler_before_execute', array( $this, 'migrate_legacy_action' ), 1 );
	}

	/**
	 * Check that the Action Scheduler store is ready.
	 *
	 * @return bool
	 */
	public static function ready(): bool {
		return class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized();
	}

	/**
	 * Read the deactivation flag without a request-local option cache.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A worker must see deactivation from another request.
		$disabled = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::DISABLED_OPTION ) );
		return empty( $wpdb->last_error ) && 'yes' !== $disabled;
	}

	/**
	 * Add one single job, or return the existing job ID.
	 *
	 * @param string $hook Job hook.
	 * @param array  $args Job arguments. Callers must use stable argument types.
	 * @param int    $timestamp Due time.
	 * @param int    $priority Queue priority.
	 * @param bool   $include_running Whether a running job also satisfies the request.
	 * @return int Job ID, or zero on failure or deactivation.
	 */
	public static function schedule( string $hook, array $args, int $timestamp, int $priority = 10, bool $include_running = true ): int {
		if ( ! self::ready() || ! self::lock() ) {
			return 0;
		}
		try {
			if ( ! self::enabled() ) {
				return 0;
			}
			foreach ( $include_running ? array( 'pending', 'in-progress' ) : array( 'pending' ) as $status ) {
				$ids = self::query(
					array(
						'hook'     => $hook,
						'args'     => $args,
						'group'    => self::GROUP,
						'status'   => $status,
						'per_page' => 1,
					)
				);
				if ( $ids ) {
					return (int) $ids[0];
				}
			}
			// Older AS versions ignore args for unique jobs. The lock protects this insert instead.
			$id = (int) as_schedule_single_action( $timestamp, $hook, $args, self::GROUP, false, $priority );
			// Deactivation can time out on this lock while a slow insert is in progress.
			if ( $id && ! self::enabled() ) {
				\ActionScheduler::store()->cancel_action( $id );
				return 0;
			}
			return $id;
		} catch ( \Exception $error ) {
			self::log_error( $error->getMessage() );
			return 0;
		} finally {
			self::unlock();
		}
	}

	/**
	 * Query IDs without treating a database failure as an empty queue.
	 *
	 * @param array $query Query parameters.
	 * @return array Action IDs.
	 * @throws \RuntimeException When the store query fails.
	 */
	private static function query( array $query ): array {
		global $wpdb;
		$ids = as_get_scheduled_actions( $query, 'ids' );
		if ( ! is_array( $ids ) || ! empty( $wpdb->last_error ) ) {
			throw new \RuntimeException( 'Could not read the Reviewbird action queue.' );
		}
		return $ids;
	}

	/**
	 * Get the site-specific database lock name.
	 *
	 * @return string
	 */
	private static function lock_name(): string {
		global $wpdb;
		return 'reviewbird_schedule_' . md5( DB_NAME . ':' . $wpdb->prefix );
	}

	/**
	 * Get the scheduling lock.
	 *
	 * @return bool Whether this request owns the lock.
	 */
	private static function lock(): bool {
		global $wpdb;
		if ( self::$locked ) {
			return false;
		}
		// ponytail: one short site lock; use per-job locks if product imports contend.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Protect the query and insert across requests and cache backends.
		self::$locked = '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 3)', self::lock_name() ) );
		return self::$locked;
	}

	/** Release the lock on success and failure. */
	private static function unlock(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the connection-owned lock.
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name() ) );
		self::$locked = false;
	}

	/** Start or resume cleanup after activation or upgrade. */
	public function ensure_cleanup(): void {
		if ( ! self::ready() || ! self::enabled() ) {
			return;
		}
		// Remove the WordPress cron event used by the first health scheduling fix.
		if ( wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_clear_scheduled_hook( self::CLEANUP_HOOK );
		}
		if ( ! get_option( self::CLEANUP_OPTION ) ) {
			self::schedule( self::CLEANUP_HOOK, array(), time() + 60, 0 );
		}
	}

	/**
	 * Replace old jobs before AS executes or repeats them.
	 *
	 * @param int $action_id Action ID.
	 */
	public function migrate_legacy_action( $action_id ): void {
		$store  = \ActionScheduler::store();
		$action = $store->fetch_action( $action_id );
		if ( ! in_array( $action->get_hook(), self::JOB_HOOKS, true ) || ! in_array( $action->get_group(), self::LEGACY_GROUPS, true ) ) {
			return;
		}
		if ( 'reviewbird_refresh_schema_reviews' === $action->get_hook() && self::enabled() ) {
			$args       = $action->get_args();
			$product_id = absint( reset( $args ) );
			if ( $product_id && ! self::schedule( $action->get_hook(), array( $product_id ), time(), 10, false ) ) {
				return;
			}
		}
		$store->cancel_action( $action_id );
	}

	/** Delete old jobs and logs in bounded batches, preserving pending schema work. */
	public function cleanup(): void {
		global $wpdb;
		if ( ! self::ready() || ! self::enabled() || get_option( self::CLEANUP_OPTION ) ) {
			return;
		}
		// Queue the retry before deletion so a timeout cannot strand the upgrade.
		if ( ! self::schedule( self::CLEANUP_HOOK, array(), time() + 60, 0, false ) ) {
			return;
		}
		$deadline  = microtime( true ) + 10;
		$budget    = 1000;
		$remaining = false;
		try {
			$store = \ActionScheduler::store();
			// Wait for the standard AS table migration before removing old records.
			if ( ! $store instanceof \ActionScheduler_DBStore ) {
				return;
			}
			foreach ( array( 'reviewbird_refresh_health_status', 'reviewbird_refresh_schema_reviews' ) as $hook ) {
				foreach ( self::LEGACY_GROUPS as $group ) {
					$query = array(
						'hook'             => $hook,
						'group'            => $group,
						'per_page'         => $budget,
						'orderby'          => 'action_id',
						'order'            => 'ASC',
						'modified'         => time() - 300,
						'modified_compare' => '<=',
					);
					foreach ( self::query( $query ) as $id ) {
						if ( --$budget < 0 || microtime( true ) >= $deadline ) {
							return;
						}
						$status = $store->get_status( $id );
						if ( 'in-progress' === $status || ( 'pending' === $status && $store->get_claim_id( $id ) ) ) {
							continue;
						}
						if ( 'pending' === $status && 'reviewbird_refresh_schema_reviews' === $hook ) {
							$args       = $store->fetch_action( $id )->get_args();
							$product_id = absint( reset( $args ) );
							if ( $product_id && ! self::schedule( $hook, array( $product_id ), time(), 10, false ) ) {
								continue;
							}
						}
						if ( 'pending' === $status ) {
							// Claiming and cancellation must be atomic; a separate claim check is insufficient.
							// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cancel only an unclaimed legacy action before deleting it through AS.
							$canceled = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->actionscheduler_actions} SET status = 'canceled' WHERE action_id = %d AND status = 'pending' AND claim_id = 0", $id ) );
							if ( 1 !== $canceled ) {
								continue;
							}
						}
						$store->delete_action( $id );
						if ( microtime( true ) >= $deadline ) {
							return;
						}
					}
					if ( 0 === $budget ) {
						return;
					}
					unset( $query['modified'], $query['modified_compare'] );
					$query['per_page'] = 1;
					$remaining         = (bool) self::query( $query ) || $remaining;
				}
			}
			if ( ! $remaining ) {
				update_option( self::CLEANUP_OPTION, true, false );
			}
		} catch ( \Exception $error ) {
			self::log_error( $error->getMessage() );
		}
	}

	/** Stop new inserts before canceling queued work during deactivation. */
	public static function deactivate(): void {
		$owns_lock = self::lock();
		try {
			update_option( self::DISABLED_OPTION, 'yes', false );
			wp_clear_scheduled_hook( self::CLEANUP_HOOK );
			if ( self::ready() ) {
				foreach ( self::JOB_HOOKS as $hook ) {
					as_unschedule_all_actions( $hook );
				}
			}
		} finally {
			if ( $owns_lock ) {
				self::unlock();
			}
		}
	}

	/**
	 * Log a scheduling failure.
	 *
	 * @param string $message Failure message.
	 */
	private static function log_error( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'reviewbird' ) );
		}
	}
}
