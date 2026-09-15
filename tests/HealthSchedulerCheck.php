<?php
/**
 * Run with: php tests/HealthSchedulerCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Standalone check with small WordPress and Action Scheduler stubs.

define( 'ABSPATH', __DIR__ );

$options = array();
$actions = array();
$logs = array();
$cron = array();
$hooks = array();
$next_id = 0;
$api_calls = 0;
$stale_lookup = false;
$delete_fails = false;
$query_fails = false;
$wpdb = (object) array( 'last_error' => '' );
$checks = 0;

function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_transient( $key ) {}
function wp_next_scheduled( $hook ) { return $GLOBALS['cron'][ $hook ] ?? false; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['cron'][ $hook ] = $time; return true; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); }
function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
function home_url() { return 'https://store.test/'; }
function reviewbird_api_request( $endpoint ) {
	check( '/api/woocommerce/health?domain=store.test' === $endpoint, 'Wrong health endpoint.' );
	++$GLOBALS['api_calls'];
	return new WP_Error();
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	public function get_error_data() { return null; }
	public function get_error_message() { return 'API unavailable'; }
}

function add_test_action( $hook, $group, $args = array(), $status = 'pending', $claim = 0, $interval = 0 ) {
	$id = ++$GLOBALS['next_id'];
	$GLOBALS['actions'][ $id ] = compact( 'hook', 'group', 'args', 'status', 'claim', 'interval' );
	$GLOBALS['logs'][ $id ] = array( 'created', 'started', 'completed' );
	return $id;
}
function has_test_action( $hook, $group ) {
	foreach ( $GLOBALS['actions'] as $action ) {
		if ( $action['hook'] === $hook && $action['group'] === $group && in_array( $action['status'], array( 'pending', 'in-progress' ), true ) ) { return true; }
	}
	return false;
}
function as_has_scheduled_action( $hook, $args = null, $group = '' ) {
	return ! $GLOBALS['stale_lookup'] && has_test_action( $hook, $group );
}
function as_schedule_recurring_action( $time, $interval, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
	check( $priority < 10, 'New health checks must be able to run before the old backlog is removed.' );
	if ( $unique && has_test_action( $hook, $group ) ) { return 0; }
	return add_test_action( $hook, $group, $args, 'pending', 0, $interval );
}
function as_schedule_single_action( $time, $hook, $args = array(), $group = '', $unique = false, $priority = 10 ) {
	return as_schedule_recurring_action( $time, 0, $hook, $args, $group, $unique, $priority );
}
function as_unschedule_all_actions( $hook, $args = array(), $group = '' ) {
	foreach ( $GLOBALS['actions'] as &$action ) {
		if ( $action['hook'] === $hook && $action['group'] === $group && $action['status'] === 'pending' ) { $action['status'] = 'canceled'; }
	}
}
function as_get_scheduled_actions( $query, $format ) {
	$GLOBALS['wpdb']->last_error = '';
	if ( $GLOBALS['query_fails'] && 1 === $query['per_page'] ) {
		$GLOBALS['wpdb']->last_error = 'Query failed';
		return array();
	}
	check( 'ids' === $format && $query['per_page'] > 0 && $query['per_page'] <= 1000, 'Cleanup must use bounded ID queries.' );
	$ids = array();
	foreach ( $GLOBALS['actions'] as $id => $action ) {
		if ( $action['hook'] !== $query['hook'] || $action['group'] !== $query['group'] ) { continue; }
		if ( isset( $query['status'] ) && ! in_array( $action['status'], $query['status'], true ) ) { continue; }
		if ( isset( $query['claimed'] ) && (bool) $action['claim'] !== $query['claimed'] ) { continue; }
		if ( isset( $query['modified'] ) && ( $action['modified'] ?? 0 ) > $query['modified'] ) { continue; }
		$ids[] = $id;
		if ( count( $ids ) === $query['per_page'] ) { break; }
	}
	return $ids;
}
class ActionScheduler {
	public static function store() { return new self(); }
	public function get_claim_id( $id ) { return $GLOBALS['actions'][ $id ]['claim']; }
	public function get_status( $id ) { return $GLOBALS['actions'][ $id ]['status']; }
	public function delete_action( $id ) {
		check( wp_next_scheduled( 'reviewbird_cleanup_health_actions' ), 'Cleanup must schedule a retry before deletion.' );
		if ( $GLOBALS['delete_fails'] ) { throw new RuntimeException( 'Delete failed' ); }
		unset( $GLOBALS['actions'][ $id ], $GLOBALS['logs'][ $id ] );
	}
}

require_once dirname( __DIR__ ) . '/src/Integration/HealthScheduler.php';
require_once dirname( __DIR__ ) . '/src/Core/Deactivator.php';

$scheduler = new \reviewbird\Integration\HealthScheduler();
$scheduler->init();
$hook = 'reviewbird_refresh_health_status';
$cleanup_hook = \reviewbird\Integration\HealthScheduler::CLEANUP_HOOK;
check( $hooks['init'] === array( $scheduler, 'schedule_recurring_check' ), 'The upgrade must run on ordinary loads, not just activation.' );
check( $hooks[ $cleanup_hook ] === array( $scheduler, 'cleanup_legacy_actions' ), 'Cleanup callback is missing.' );

// Busy sites must have one recurring job even with no store ID or cache.
for ( $i = 0; $i < 40000; ++$i ) {
	if ( 20000 === $i ) { $options['reviewbird_store_id'] = 7; }
	$scheduler->schedule_recurring_check();
}
check( 1 === count( $actions ) && 1 === count( $cron ), 'Page loads created duplicate health or cleanup jobs.' );
$recurring_id = array_key_first( $actions );
check( 300 === $actions[ $recurring_id ]['interval'], 'The normal interval must stay at five minutes.' );
check( 0 === $api_calls, 'Page loads must not call the health API.' );

// A stale lookup models a second request between lookup and insertion.
$stale_lookup = true;
$scheduler->schedule_recurring_check();
$stale_lookup = false;
check( 1 === count( $actions ), 'Recurring insertion must request native uniqueness.' );
$actions[ $recurring_id ]['status'] = 'in-progress';
$scheduler->schedule_recurring_check();
check( 1 === count( $actions ), 'A running recurring check must prevent another schedule.' );
$actions[ $recurring_id ]['status'] = 'pending';

// Connection callbacks can request one immediate check without replacing recurrence.
for ( $i = 0; $i < 100; ++$i ) { $scheduler->schedule_immediate_refresh(); }
check( 2 === count( $actions ), 'Connection callbacks must share one immediate job.' );
$immediate_id = $next_id;
$actions[ $immediate_id ]['status'] = 'in-progress';
$scheduler->schedule_immediate_refresh();
check( 2 === count( $actions ), 'A running immediate check must prevent another immediate job.' );
$actions[ $immediate_id ]['status'] = 'pending';

// Old jobs do no API work during cleanup. New jobs still work during an outage.
call_user_func_array( $hooks[ $hook ], array() );
check( 0 === $api_calls, 'Old queued actions still poll the API.' );
call_user_func_array( $hooks[ $hook ], $actions[ $recurring_id ]['args'] );
check( 1 === $api_calls, 'The new recurring job did not reach the API.' );
for ( $i = 0; $i < 100; ++$i ) { $scheduler->schedule_recurring_check(); }
check( 2 === count( $actions ), 'An API failure caused a page-load retry flood.' );
$scheduler->refresh_health_status();
check( 2 === $api_calls, 'Manual refresh must still work.' );

// Upgrade cleanup spans batches and preserves other jobs, logs, and the cache.
$options['reviewbird_store_status'] = array( 'status' => 'healthy', 'store_id' => 7 );
$cache = $options['reviewbird_store_status'];
$schema_id = add_test_action( 'reviewbird_refresh_schema_reviews', 'reviewbird' );
$other_id = add_test_action( $hook, 'other-plugin' );
$running_id = add_test_action( $hook, 'reviewbird', array(), 'in-progress', 3 );
$claimed_id = add_test_action( $hook, 'reviewbird', array(), 'pending', 4 );
$recent_id = add_test_action( $hook, 'reviewbird', array(), 'complete', 5 );
$actions[ $recent_id ]['modified'] = time();
for ( $i = 0; $i < 1005; ++$i ) {
	$status = array( 'pending', 'complete', 'failed', 'canceled' )[ $i % 4 ];
	add_test_action( $hook, 'reviewbird', array(), $status, 'pending' === $status ? 0 : 2 );
}
$before = count( $actions );
unset( $cron[ $cleanup_hook ] );
$scheduler->cleanup_legacy_actions();
check( $before - 999 === count( $actions ), 'The first cleanup must skip the claimed action within its batch of 1000.' );
check( ! get_option( 'reviewbird_health_actions_cleaned' ), 'Cleanup finished before all old jobs were removed.' );
check( count( $logs ) === count( $actions ), 'Deleted actions left their logs behind.' );

$delete_fails = true;
$scheduler->cleanup_legacy_actions();
check( ! get_option( 'reviewbird_health_actions_cleaned' ) && wp_next_scheduled( $cleanup_hook ), 'A failed cleanup must remain retryable.' );
$delete_fails = false;
$scheduler->cleanup_legacy_actions();
check( 7 === count( $actions ), 'Cleanup did not preserve new, unrelated, running, claimed, and recently completed jobs.' );
check( ! get_option( 'reviewbird_health_actions_cleaned' ), 'Cleanup must wait for running and claimed jobs.' );
foreach ( array( $running_id, $claimed_id, $recent_id ) as $id ) {
	$actions[ $id ]['status'] = 'complete';
	// Completed actions retain their claim ID in the real Action Scheduler store.
	$actions[ $id ]['modified'] = time() - 301;
}
$query_fails = true;
$scheduler->cleanup_legacy_actions();
check( ! get_option( 'reviewbird_health_actions_cleaned' ) && wp_next_scheduled( $cleanup_hook ), 'A failed final query must not mark cleanup complete.' );
$query_fails = false;
$scheduler->cleanup_legacy_actions();
check( array( $recurring_id, $immediate_id, $schema_id, $other_id ) === array_keys( $actions ), 'Cleanup deleted unrelated actions or retained old health jobs.' );
check( array_keys( $logs ) === array_keys( $actions ), 'Cleanup did not remove all old health logs.' );
check( $cache === $options['reviewbird_store_status'], 'Cleanup changed the health cache.' );
check( get_option( 'reviewbird_health_actions_cleaned' ) && ! wp_next_scheduled( $cleanup_hook ), 'Cleanup did not finish and clear its cron event.' );
$scheduler->schedule_recurring_check();
$scheduler->cleanup_legacy_actions();
check( 4 === count( $actions ) && ! wp_next_scheduled( $cleanup_hook ), 'Completed cleanup must not restart on later requests.' );

// Deactivation clears both schedules and an unfinished cleanup event.
wp_schedule_single_event( time(), $cleanup_hook );
\reviewbird\Core\Deactivator::deactivate();
check( ! wp_next_scheduled( $cleanup_hook ), 'Deactivation left a cleanup event.' );
check( ! has_test_action( $hook, 'reviewbird-health' ) && ! has_test_action( $hook, 'reviewbird-health-immediate' ), 'Deactivation left a health check scheduled.' );

echo "Health scheduler: {$checks} checks passed.\n";
