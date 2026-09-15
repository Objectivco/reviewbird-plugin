<?php
/**
 * Run: php tests/SchedulerIntegrationCheck.php /path/to/disposable/wordpress
 *
 * Load Action Scheduler and Reviewbird's HealthScheduler and SchemaScheduler on the test site.
 * Use a reviewbird_scheduler_test_ database. Disable WP-Cron, the AS async runner, and external HTTP.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This check resets an isolated WordPress test database.

use reviewbird\Integration\HealthScheduler;
use reviewbird\Integration\SchemaScheduler;

$test_site = $argv[1] ?? '';
$config = is_file( $test_site . '/wp-config.php' ) ? file_get_contents( $test_site . '/wp-config.php' ) : '';
if ( ! preg_match( "/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]reviewbird_scheduler_test_[a-z0-9_]+['\"]\s*\)/", $config ) ) {
	fwrite( STDERR, "Use a disposable site with a reviewbird_scheduler_test_ database.\n" );
	exit( 2 );
}
require $test_site . '/wp-load.php';
if ( ! preg_match( '/^reviewbird_scheduler_test_[a-z0-9_]+$/', DB_NAME ) ) { exit( 2 ); }

$checks = 0;
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
function ids( $hook, $args = null, $status = 'pending' ) {
	return as_get_scheduled_actions( array( 'hook' => $hook, 'args' => $args, 'status' => $status, 'per_page' => 2000 ), 'ids' );
}

check( ActionScheduler::is_initialized(), 'Action Scheduler must be initialized.' );
check( ActionScheduler::store() instanceof ActionScheduler_DBStore, 'Finish the AS table migration before this check.' );
foreach ( array( $wpdb->actionscheduler_actions, $wpdb->actionscheduler_logs, $wpdb->actionscheduler_claims ) as $table ) {
	$wpdb->query( "DELETE FROM {$table}" );
}
delete_option( 'reviewbird_health_cleanup_status' );
delete_option( 'reviewbird_store_status' );
update_option( 'reviewbird_enable_schema', 'yes' );
update_option( 'reviewbird_store_id', 7 );
$health = new HealthScheduler();
$schema = new SchemaScheduler();
$store = ActionScheduler::store();
$runner = ActionScheduler_QueueRunner::instance();
$health_hook = 'reviewbird_refresh_health_status';
$schema_hook = 'reviewbird_refresh_schema_reviews';

// Upgrade a queue larger than one cleanup batch, with old single and recurring jobs.
$legacy = array();
for ( $i = 0; $i < 1005; ++$i ) {
	$id = $i % 3 ? as_schedule_single_action( time(), $health_hook, array(), 'reviewbird' )
		: as_schedule_recurring_action( time(), 300, $health_hook, array(), 'reviewbird' );
	$legacy[] = $id;
	if ( $i % 2 ) { $store->mark_complete( $id ); }
}
$old_schema = as_enqueue_async_action( $schema_hook, array( 700 ), 'reviewbird-v2' );
$unrelated = as_enqueue_async_action( 'another_plugin_job', array(), 'reviewbird' );
wp_schedule_single_event( time(), HealthScheduler::CLEANUP_HOOK );
for ( $i = 0; $i < 100; ++$i ) { $health->schedule_recurring_check(); }
check( 1 === count( ids( $health_hook ) ), 'An empty cache or repeated initialization duplicated health jobs.' );
check( 1 === count( ids( HealthScheduler::CLEANUP_HOOK ) ), 'Initialization duplicated cleanup jobs.' );
check( ! wp_next_scheduled( HealthScheduler::CLEANUP_HOOK ), 'The old WP-Cron event remains.' );
check( 'canceled' === $store->get_status( $legacy[0] ), 'The old recurring job was not canceled.' );
$periodic = $store->fetch_action( ids( $health_hook )[0] )->get_schedule();
check( $periodic->is_recurring() && 300 === $periodic->get_recurrence(), 'Health must use AS recurrence every five minutes.' );

for ( $i = 0; $i < 50; ++$i ) {
	$health->schedule_immediate_refresh();
	$schema->schedule_schema_refresh( '700' );
	$schema->schedule_schema_refresh( 701 );
}
check( 1 === count( ids( $health_hook, array( 'immediate' ) ) ), 'Connection notifications duplicated immediate checks.' );
check( 1 === count( ids( $schema_hook, array( 700 ) ) ), 'Existing schema work was duplicated.' );
check( 1 === count( ids( $schema_hook, array( 701 ) ) ), 'A different product lost its schema job.' );
$schema->schedule_schema_refresh( array( 702 ) );
$schema->schedule_schema_refresh( 0 );
update_option( 'reviewbird_enable_schema', 'no' );
$schema->schedule_schema_refresh( 702 );
check( 2 === count( ids( $schema_hook ) ), 'Invalid or disabled schema requests created work.' );
update_option( 'reviewbird_enable_schema', 'yes' );

// Failed API responses preserve cached data and leave recurrence to AS.
$good_cache = array( 'status' => 'healthy', 'store_id' => 7 );
update_option( 'reviewbird_store_status', $good_cache );
$calls = 0;
$response = new WP_Error( 'test_outage', 'API unavailable' );
add_filter( 'pre_http_request', function () use ( &$calls, &$response ) { ++$calls; return $response; }, 100 );
foreach ( array( new WP_Error( 'test_outage', 'API unavailable' ), array( 'response' => array( 'code' => 200 ), 'body' => '{broken' ) ) as $response ) {
	$runner->process_action( ids( $health_hook, array( 'periodic' ) )[0], 'Reviewbird test' );
	check( 1 === count( ids( $health_hook, array( 'periodic' ) ) ), 'AS did not retain one recurring health job.' );
	check( $good_cache === get_option( 'reviewbird_store_status' ), 'A failed response erased the health cache.' );
}
$health->run_scheduled_check();
check( 2 === $calls, 'An old health callback made an API request.' );
update_post_meta( 700, SchemaScheduler::META_KEY, array( 'last good schema' ) );
$schema->refresh_schema_reviews( 700 );
check( array( 'last good schema' ) === get_post_meta( 700, SchemaScheduler::META_KEY, true ), 'A failed response erased the schema cache.' );
$response = array( 'response' => array( 'code' => 200 ), 'body' => '{"reviews":[]}' );
$schema->refresh_schema_reviews( 700 );
check( '' === get_post_meta( 700, SchemaScheduler::META_KEY, true ), 'A valid empty response did not clear obsolete schema.' );

// Cleanup uses the AS store to remove actions and their logs, at most 1000 per batch.
$before = count( ids( $health_hook, null, '' ) );
$runner->process_action( ids( HealthScheduler::CLEANUP_HOOK )[0], 'Reviewbird test' );
$removed = $before - count( ids( $health_hook, null, '' ) );
check( $removed > 0 && $removed <= 1000, 'Cleanup did not respect its batch size.' );
check( 'pending' === get_option( 'reviewbird_health_cleanup_status' ), 'Cleanup stopped before the next batch.' );
check( 1 === count( ids( HealthScheduler::CLEANUP_HOOK ) ), 'The next cleanup batch is missing or duplicated.' );
$runner->process_action( ids( HealthScheduler::CLEANUP_HOOK )[0], 'Reviewbird test' );
check( 'complete' === get_option( 'reviewbird_health_cleanup_status' ), 'Cleanup did not finish.' );
$legacy_ids = implode( ',', array_map( 'intval', $legacy ) );
check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_actions} WHERE action_id IN ({$legacy_ids})" ), 'Old health actions remain.' );
check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_logs} WHERE action_id IN ({$legacy_ids})" ), 'Old health logs remain.' );
check( 2 === count( ids( $health_hook ) ), 'Cleanup removed current health jobs.' );
check( 'pending' === $store->get_status( $old_schema ) && 'pending' === $store->get_status( $unrelated ), 'Cleanup removed other work.' );
$health->schedule_recurring_check();
check( ! ids( HealthScheduler::CLEANUP_HOOK ), 'Completed cleanup started again.' );

as_schedule_single_action( time(), HealthScheduler::CLEANUP_HOOK );
wp_schedule_single_event( time(), HealthScheduler::CLEANUP_HOOK );
reviewbird\Core\Deactivator::deactivate();
foreach ( array( $health_hook, $schema_hook, HealthScheduler::CLEANUP_HOOK ) as $hook ) {
	check( ! ids( $hook ), 'Deactivation left plugin jobs pending.' );
}
check( ! wp_next_scheduled( HealthScheduler::CLEANUP_HOOK ), 'Deactivation left the WP-Cron event.' );
reviewbird\Core\Activator::activate();
$health->schedule_recurring_check();
check( 1 === count( ids( $health_hook ) ), 'Reactivation did not restore one health job.' );
echo "Scheduler integration: {$checks} checks passed with real WordPress and Action Scheduler.\n";
