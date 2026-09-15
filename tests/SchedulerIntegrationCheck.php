<?php
/**
 * Run: php tests/SchedulerIntegrationCheck.php /path/to/disposable/wordpress
 *
 * The test site must load Action Scheduler and the three Reviewbird schedulers.
 * Its database name must start with reviewbird_scheduler_test_. Never use a customer site.
 * Disable WP-Cron and the AS async runner. Block external HTTP requests on the test site.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This integration check controls an isolated WordPress database and child processes.

$test_site = $argv[1] ?? '';
$config = is_file( $test_site . '/wp-config.php' ) ? file_get_contents( $test_site . '/wp-config.php' ) : '';
if ( ! preg_match( "/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]reviewbird_scheduler_test_[a-z0-9_]+['\"]\s*\)/", $config ) ) {
	fwrite( STDERR, "Use a disposable test site with a reviewbird_scheduler_test_ database.\n" );
	exit( 2 );
}
if ( 'before-init' === ( $argv[2] ?? '' ) ) {
	define( 'ABSPATH', $test_site . '/' );
	require dirname( __DIR__ ) . '/vendor/autoload.php';
	exit( 0 === reviewbird\Integration\Scheduler::schedule( 'reviewbird_refresh_health_status', array( 'periodic' ), time() ) ? 0 : 5 );
}
require $test_site . '/wp-load.php';
if ( ! preg_match( '/^reviewbird_scheduler_test_[a-z0-9_]+$/', DB_NAME ) ) {
	fwrite( STDERR, "The loaded database is not a disposable scheduler test database.\n" );
	exit( 2 );
}

use reviewbird\Integration\Scheduler;
use reviewbird\Integration\HealthScheduler;
use reviewbird\Integration\SchemaScheduler;

$health = new HealthScheduler();
$schema = new SchemaScheduler();
$scheduler = new Scheduler();
$mode = $argv[2] ?? 'check';

if ( 'worker' === $mode ) {
	$barrier = $argv[3];
	file_put_contents( $barrier . '.' . getmypid(), 'ready' );
	$deadline = microtime( true ) + 20;
	while ( ! is_file( $barrier ) && microtime( true ) < $deadline ) { usleep( 10000 ); }
	if ( ! is_file( $barrier ) ) { exit( 3 ); }
	for ( $i = 0; $i < 40; ++$i ) {
		$health->schedule_recurring_check();
		$health->schedule_immediate_refresh();
		$schema->schedule_schema_refresh( (string) ( 700 + $i % 4 ) );
		$scheduler->ensure_cleanup();
	}
	exit;
}
if ( 'deactivate' === $mode ) {
	Scheduler::deactivate();
	exit;
}
if ( 'crash-before' === $mode ) {
	add_filter( 'query', function ( $sql ) {
		if ( schema_insert( $sql, 902 ) ) { exit( 17 ); }
		return $sql;
	} );
	$schema->schedule_schema_refresh( 902 );
	exit( 4 );
}
if ( 'crash-after' === $mode ) {
	add_action( 'action_scheduler_stored_action', function ( $id ) {
		if ( array( 903 ) === ActionScheduler::store()->fetch_action( $id )->get_args() ) { exit( 17 ); }
	} );
	$schema->schedule_schema_refresh( 903 );
	exit( 4 );
}

$checks = 0;
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
function ids( $hook, $args = null, $group = Scheduler::GROUP, $status = 'pending' ) {
	return as_get_scheduled_actions( array( 'hook' => $hook, 'args' => $args, 'group' => $group, 'status' => $status, 'per_page' => 10000 ), 'ids' );
}
function schema_insert( $sql, $product_id ) {
	return stripos( $sql, 'INSERT INTO' ) !== false && strpos( $sql, $GLOBALS['wpdb']->actionscheduler_actions ) !== false && strpos( $sql, "'[" . $product_id . "]'" ) !== false;
}
function run_child( array $command ) {
	$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	fclose( $pipes[0] );
	return array( $process, $pipes );
}
function finish_child( array $child, $expected_exit = 0 ) {
	list( $process, $pipes ) = $child;
	$output = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	check( $expected_exit === proc_close( $process ), 'Unexpected child exit: ' . $output );
}

check( Scheduler::ready(), 'Action Scheduler must be initialized.' );
check( ActionScheduler::store() instanceof ActionScheduler_DBStore, 'Finish the AS table migration before this test.' );
finish_child( run_child( array( PHP_BINARY, __FILE__, $test_site, 'before-init' ) ) );
// Reset only the disposable database. Keep group IDs so AS group caches remain valid.
foreach ( array( $wpdb->actionscheduler_actions, $wpdb->actionscheduler_logs, $wpdb->actionscheduler_claims ) as $table ) {
	$wpdb->query( "DELETE FROM {$table}" );
}
delete_option( Scheduler::DISABLED_OPTION );
delete_option( 'reviewbird_scheduler_cleanup_complete' );
update_option( 'reviewbird_enable_schema', 'yes' );
update_option( 'reviewbird_store_id', 7 );
delete_option( 'reviewbird_store_status' );
$health_hook = 'reviewbird_refresh_health_status';
$schema_hook = 'reviewbird_refresh_schema_reviews';
$store = ActionScheduler::store();
$runner = ActionScheduler_QueueRunner::instance();

// Reproduce the old recurrence gap using AS's actual factory and store.
$old_id = as_schedule_recurring_action( time(), 300, 'reviewbird_test_recurrence', array(), 'reviewbird-test' );
$old_action = $store->fetch_action( $old_id );
$store->mark_complete( $old_id );
as_schedule_recurring_action( time(), 300, 'reviewbird_test_recurrence', array(), 'reviewbird-test', true );
ActionScheduler::factory()->repeat( $old_action );
check( 2 === count( ids( 'reviewbird_test_recurrence', null, 'reviewbird-test' ) ), 'The fixture did not reproduce the AS replacement race.' );

// Exercise real parallel requests, including first insertion of each schema job.
foreach ( array( $health_hook, $schema_hook, Scheduler::CLEANUP_HOOK ) as $hook ) {
	as_unschedule_all_actions( $hook, null, Scheduler::GROUP );
}
$barrier = sys_get_temp_dir() . '/reviewbird-scheduler-barrier-' . getmypid();
$children = array();
for ( $i = 0; $i < 12; ++$i ) {
	$children[] = run_child( array( PHP_BINARY, __FILE__, $test_site, 'worker', $barrier ) );
}
$deadline = microtime( true ) + 20;
while ( count( glob( $barrier . '.*' ) ) < 12 && microtime( true ) < $deadline ) { usleep( 10000 ); }
check( 12 === count( glob( $barrier . '.*' ) ), 'Workers did not reach the insertion barrier.' );
touch( $barrier );
foreach ( $children as $child ) { finish_child( $child ); }
foreach ( glob( $barrier . '*' ) as $file ) { unlink( $file ); }
check( 1 === count( ids( $health_hook, array( 'periodic' ) ) ), 'Parallel requests duplicated the periodic check.' );
check( 1 === count( ids( $health_hook, array( 'immediate' ) ) ), 'Parallel requests duplicated the immediate check.' );
check( 1 === count( ids( Scheduler::CLEANUP_HOOK ) ), 'Parallel requests duplicated cleanup.' );
for ( $product_id = 700; $product_id < 704; ++$product_id ) {
	check( 1 === count( ids( $schema_hook, array( $product_id ) ) ), 'Product work was duplicated or skipped.' );
}
$schema->schedule_schema_refresh( array( 700 ) );
$schema->schedule_schema_refresh( 0 );
check( 4 === count( ids( $schema_hook ) ), 'Invalid product IDs created jobs.' );

// New jobs use single schedules. A next check exists before API work starts.
$initial = ids( $health_hook, array( 'periodic' ) )[0];
check( ! $store->fetch_action( $initial )->get_schedule()->is_recurring(), 'AS must not create a second recurring replacement.' );
$http_calls = 0;
$http_result = new WP_Error( 'test_outage', 'API unavailable' );
$http_filter = function () use ( &$http_calls, &$http_result, $health, $health_hook ) {
	++$http_calls;
	check( 1 === count( ids( $health_hook, array( 'periodic' ) ) ), 'Next health check was not saved before the HTTP request.' );
	$health->schedule_recurring_check();
	return $http_result;
};
add_filter( 'pre_http_request', $http_filter, 100 );
$completion = function () use ( $health ) { $health->schedule_recurring_check(); };
add_action( 'action_scheduler_completed_action', $completion );
for ( $i = 0; $i < 10; ++$i ) {
	$id = ids( $health_hook, array( 'periodic' ) )[0];
	$runner->process_action( $id, 'Reviewbird integration test' );
	$pending = ids( $health_hook, array( 'periodic' ) );
	check( 1 === count( $pending ), 'Execution or completion created duplicate next checks.' );
	check( $store->fetch_action( $pending[0] )->get_schedule()->get_date()->getTimestamp() >= time() + 298, 'An API failure created an immediate retry.' );
}
check( 10 === $http_calls, 'Unexpected API calls during normal scheduling.' );
$good_cache = array( 'status' => 'healthy', 'store_id' => 7 );
update_option( 'reviewbird_store_status', $good_cache );
$http_result = array( 'headers' => array(), 'body' => '<html>Invalid JSON</html>', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
$runner->process_action( ids( $health_hook, array( 'periodic' ) )[0], 'Reviewbird integration test' );
check( $good_cache === get_option( 'reviewbird_store_status' ), 'Invalid JSON erased the good health cache.' );
remove_filter( 'pre_http_request', $http_filter, 100 );
remove_action( 'action_scheduler_completed_action', $completion );

// Recover if a worker failed before the health callback could save its successor.
$failed = ids( $health_hook, array( 'periodic' ) )[0];
$store->log_execution( $failed );
$store->mark_failure( $failed );
for ( $i = 0; $i < 50; ++$i ) { $health->schedule_recurring_check(); }
check( 1 === count( ids( $health_hook, array( 'periodic' ) ) ), 'Recovery from a failed health job created duplicate checks.' );

// An update during a running schema fetch gets one later job, without losing other products.
$schema_running = ids( $schema_hook, array( 700 ) )[0];
$store->log_execution( $schema_running );
for ( $i = 0; $i < 50; ++$i ) { $schema->schedule_schema_refresh( '700' ); }
check( 1 === count( ids( $schema_hook, array( 700 ) ) ), 'Updates during execution must share one later schema refresh.' );
$store->mark_complete( $schema_running );

// A failed schema response must preserve the last successful data.
update_post_meta( 700, SchemaScheduler::META_KEY, array( 'last good schema' ) );
$schema->refresh_schema_reviews( 700 );
check( array( 'last good schema' ) === get_post_meta( 700, SchemaScheduler::META_KEY, true ), 'An API outage erased the schema cache.' );
$schema_response = array( 'headers' => array(), 'body' => '{broken', 'response' => array( 'code' => 200 ), 'cookies' => array() );
$schema_http = function () use ( &$schema_response ) { return $schema_response; };
add_filter( 'pre_http_request', $schema_http, 100 );
$schema->refresh_schema_reviews( 700 );
check( array( 'last good schema' ) === get_post_meta( 700, SchemaScheduler::META_KEY, true ), 'Invalid JSON erased the schema cache.' );
$schema_response['body'] = '{"reviews":[]}';
$schema_response['response']['code'] = 503;
$schema->refresh_schema_reviews( 700 );
check( array( 'last good schema' ) === get_post_meta( 700, SchemaScheduler::META_KEY, true ), 'An HTTP error erased the schema cache.' );
$schema_response['response']['code'] = 200;
$schema->refresh_schema_reviews( 700 );
check( '' === get_post_meta( 700, SchemaScheduler::META_KEY, true ), 'A valid empty response must remove obsolete schema.' );
remove_filter( 'pre_http_request', $schema_http, 100 );
update_option( 'reviewbird_enable_schema', 'no' );
$schema->schedule_schema_refresh( 704 );
check( ! ids( $schema_hook, array( 704 ) ), 'Disabled schema created new work.' );
update_option( 'reviewbird_enable_schema', 'yes' );

// A nested enqueue cannot reenter the same insert, including on older AS versions.
$nested = function ( $sql ) use ( $schema_hook ) {
	if ( schema_insert( $sql, 777 ) ) {
		check( 0 === Scheduler::schedule( $schema_hook, array( 777 ), time() ), 'Nested insertion was not blocked.' );
	}
	return $sql;
};
add_filter( 'query', $nested );
$schema->schedule_schema_refresh( 777 );
remove_filter( 'query', $nested );
check( 1 === count( ids( $schema_hook, array( 777 ) ) ), 'Nested scheduling created duplicate rows.' );

// Query and insertion failures must release the lock and leave retryable work.
$db_failure = function ( $sql ) {
	return strpos( $sql, 'SELECT a.action_id' ) !== false ? 'SELECT broken FROM reviewbird_missing_test_table' : $sql;
};
$wpdb->suppress_errors( true );
add_filter( 'query', $db_failure );
check( 0 === Scheduler::schedule( $schema_hook, array( 778 ), time() ), 'A failed query was treated as an empty queue.' );
remove_filter( 'query', $db_failure );
check( Scheduler::schedule( $schema_hook, array( 778 ), time() ) > 0, 'A query failure left the insertion lock held.' );
$insert_failure = function ( $sql ) { return schema_insert( $sql, 779 ) ? 'INSERT INTO reviewbird_missing_test_table VALUES (1)' : $sql; };
add_filter( 'query', $insert_failure );
check( 0 === Scheduler::schedule( $schema_hook, array( 779 ), time() ), 'Expected the injected insertion failure.' );
remove_filter( 'query', $insert_failure );
check( Scheduler::schedule( $schema_hook, array( 779 ), time() ) > 0, 'An insertion failure left the lock held.' );

// Exit skips finally. The database connection must release its lock on process death.
finish_child( run_child( array( PHP_BINARY, __FILE__, $test_site, 'crash-before' ) ), 17 );
check( ! ids( $schema_hook, array( 902 ) ), 'The process exited after the expected insertion point.' );
check( Scheduler::schedule( $schema_hook, array( 902 ), time() ) > 0, 'A dead process left the scheduling lock held.' );
finish_child( run_child( array( PHP_BINARY, __FILE__, $test_site, 'crash-after' ) ), 17 );
check( 1 === count( ids( $schema_hook, array( 903 ) ) ), 'The process did not exit after insertion.' );
$inserted = (int) ids( $schema_hook, array( 903 ) )[0];
check( $inserted === Scheduler::schedule( $schema_hook, array( 903 ), time() ), 'Retry after a process failure did not reuse the saved job.' );
check( 1 === count( ids( $schema_hook, array( 903 ) ) ), 'Retry after a process failure created duplicate work.' );

// A legacy recurring action is canceled before AS can execute and repeat it.
$legacy_recurring = as_schedule_recurring_action( time(), 300, $health_hook, array( true ), 'reviewbird-health' );
$runner->process_action( $legacy_recurring, 'Reviewbird integration test' );
check( 'canceled' === $store->get_status( $legacy_recurring ), 'The old recurring job was not canceled.' );
check( 0 === count( ids( $health_hook, null, 'reviewbird-health' ) ), 'The old recurring chain created another job.' );

// Upgrade a queue larger than one batch. Completed actions retain old claim IDs.
$legacy = array();
for ( $i = 0; $i < 1005; ++$i ) {
	$id = as_schedule_single_action( time() - 600, $health_hook, array(), 'reviewbird' );
	$legacy[] = $id;
	if ( $i % 2 ) {
		$store->mark_complete( $id );
		$wpdb->update( $wpdb->actionscheduler_actions, array( 'claim_id' => 999, 'last_attempt_gmt' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ), array( 'action_id' => $id ) );
	}
}
for ( $i = 0; $i < 10; ++$i ) {
	$legacy[] = as_schedule_single_action( time() - 600, $schema_hook, array( '780' ), 'reviewbird' );
}
$unrelated = as_schedule_single_action( time(), 'another_plugin_test_job', array(), 'reviewbird' );
$other_group = as_schedule_single_action( time(), $health_hook, array(), 'another-plugin' );
$claimed = as_schedule_single_action( time() - 600, $health_hook, array(), 'reviewbird' );
$wpdb->update( $wpdb->actionscheduler_actions, array( 'claim_id' => 888 ), array( 'action_id' => $claimed ) );
$recent = as_schedule_single_action( time(), $health_hook, array(), 'reviewbird' );
$store->mark_complete( $recent );
$store->log_execution( $other_group );
$before = count( ids( $health_hook, null, 'reviewbird', '' ) );
$scheduler->cleanup();
check( $before - 1000 === count( ids( $health_hook, null, 'reviewbird', '' ) ), 'Cleanup did not respect the 1000-action batch.' );
check( ! get_option( 'reviewbird_scheduler_cleanup_complete' ), 'Cleanup marked a partial batch complete.' );
$scheduler->cleanup();
check( 'pending' === $store->get_status( $claimed ), 'Cleanup deleted a claimed job.' );
check( 'complete' === $store->get_status( $recent ), 'Cleanup deleted a recently completed job.' );
check( 'pending' === $store->get_status( $unrelated ) && 'in-progress' === $store->get_status( $other_group ), 'Cleanup changed unrelated work.' );
check( 1 === count( ids( $schema_hook, array( 780 ) ) ), 'Cleanup lost or duplicated pending schema work.' );
check( ! get_option( 'reviewbird_scheduler_cleanup_complete' ), 'Cleanup must wait for claimed and recently completed actions.' );

// Simulate a runner claiming the row between the initial check and cancellation.
$race_id = as_schedule_single_action( time() - 600, $health_hook, array(), 'reviewbird' );
$claim_connection = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$claim_race = function ( $sql ) use ( $race_id, $claim_connection, $wpdb ) {
	if ( strpos( $sql, "SET status = 'canceled' WHERE action_id = " . $race_id . ' ' ) !== false ) {
		$claim_connection->query( 'UPDATE ' . $wpdb->actionscheduler_actions . ' SET claim_id = 887 WHERE action_id = ' . (int) $race_id );
	}
	return $sql;
};
add_filter( 'query', $claim_race );
$scheduler->cleanup();
remove_filter( 'query', $claim_race );
check( 'pending' === $store->get_status( $race_id ) && 887 === (int) $store->get_claim_id( $race_id ), 'Cleanup deleted an action claimed after its query.' );

foreach ( array( $claimed, $recent, $race_id, $legacy_recurring ) as $id ) {
	$wpdb->update( $wpdb->actionscheduler_actions, array( 'status' => 'complete', 'last_attempt_gmt' => gmdate( 'Y-m-d H:i:s', time() - 600 ) ), array( 'action_id' => $id ) );
	$legacy[] = $id;
}
$scheduler->cleanup();
check( get_option( 'reviewbird_scheduler_cleanup_complete' ), 'Cleanup did not finish.' );
foreach ( $legacy as $id ) {
	check( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_actions} WHERE action_id = %d", $id ) ), 'An old action remains.' );
	check( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_logs} WHERE action_id = %d", $id ) ), 'An old action left orphan logs.' );
}

// A running request must observe deactivation even with an old option cache.
get_option( Scheduler::DISABLED_OPTION );
finish_child( run_child( array( PHP_BINARY, __FILE__, $test_site, 'deactivate' ) ) );
check( 0 === Scheduler::schedule( $schema_hook, array( 900 ), time() ), 'A stale request queued work after deactivation.' );
foreach ( array( $health_hook, $schema_hook, Scheduler::CLEANUP_HOOK ) as $hook ) {
	check( 0 === count( ids( $hook ) ), 'Deactivation left plugin jobs pending.' );
}
reviewbird\Core\Activator::activate();
$health->schedule_recurring_check();
check( 1 === count( ids( $health_hook, array( 'periodic' ) ) ), 'Reactivation did not restore one health check.' );

// A slow insert must not leave new jobs after deactivation times out on the lock.
$late_deactivation = function ( $sql ) use ( $test_site ) {
	if ( schema_insert( $sql, 901 ) ) {
		finish_child( run_child( array( PHP_BINARY, __FILE__, $test_site, 'deactivate' ) ) );
	}
	return $sql;
};
add_filter( 'query', $late_deactivation );
check( 0 === Scheduler::schedule( $schema_hook, array( 901 ), time() ), 'An insert completed after deactivation without cancellation.' );
remove_filter( 'query', $late_deactivation );
check( 0 === count( ids( $schema_hook, array( 901 ) ) ), 'A late insert left pending work after deactivation.' );
reviewbird\Core\Activator::activate();

echo "Scheduler integration: {$checks} checks passed with real WordPress, Action Scheduler, MySQL, and 12 concurrent PHP processes.\n";
