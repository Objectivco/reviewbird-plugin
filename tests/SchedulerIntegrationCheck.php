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
update_option( 'reviewbird_store_status', array( 'status' => 'healthy', 'has_active_subscription' => true, 'store_id' => 7 ) );
$product_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Schema scheduler test' ) );
$other_product_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Other schema scheduler test' ) );
$health = new HealthScheduler();
$schema = new SchemaScheduler();
$store = ActionScheduler::store();
$runner = ActionScheduler_QueueRunner::instance();
$health_hook = 'reviewbird_refresh_health_status';
$schema_hook = 'reviewbird_refresh_schema_reviews';

// A fresh store starts its normal check without upgrade work.
$health->schedule_recurring_check();
check( 1 === count( ids( $health_hook ) ), 'A fresh store did not get one health job.' );
check( ! ids( HealthScheduler::CLEANUP_HOOK ), 'A fresh store scheduled upgrade cleanup.' );
check( 'complete' === get_option( 'reviewbird_health_cleanup_status' ), 'A fresh store did not skip cleanup.' );
$store->delete_action( ids( $health_hook )[0] );
delete_option( 'reviewbird_health_cleanup_status' );

// Upgrade a queue larger than one cleanup batch, with old single and recurring jobs.
$legacy = array();
for ( $i = 0; $i < 1005; ++$i ) {
	$id = $i % 3 ? as_schedule_single_action( time(), $health_hook, array(), 'reviewbird' )
		: as_schedule_recurring_action( time(), 300, $health_hook, array(), 'reviewbird' );
	$legacy[] = $id;
	if ( $i % 2 ) { $store->mark_complete( $id ); }
}
$old_schema = as_enqueue_async_action( $schema_hook, array( $product_id ), 'reviewbird-v2' );
$unrelated = as_enqueue_async_action( 'another_plugin_job', array(), 'reviewbird' );
wp_schedule_single_event( time(), HealthScheduler::CLEANUP_HOOK );
for ( $i = 0; $i < 100; ++$i ) { $health->schedule_recurring_check(); }
check( ! ids( $health_hook, array( 'periodic' ) ), 'An upgrade scheduled health before old jobs were canceled.' );
check( 1 === count( ids( HealthScheduler::CLEANUP_HOOK ) ), 'Initialization duplicated cleanup jobs.' );
check( ! wp_next_scheduled( HealthScheduler::CLEANUP_HOOK ), 'The old WP-Cron event remains.' );
check( 'pending' === $store->get_status( $legacy[0] ), 'A page request canceled the old queue.' );
$before = count( ids( $health_hook, null, '' ) );
$runner->process_action( ids( HealthScheduler::CLEANUP_HOOK )[0], 'Reviewbird upgrade test' );
$removed = $before + 1 - count( ids( $health_hook, null, '' ) ); // The upgrade also creates the new health job.
check( $removed > 0 && $removed <= 1000, 'Upgrade cleanup did not respect its batch size.' );
check( 'pending' === get_option( 'reviewbird_health_cleanup_status' ), 'Upgrade cleanup stopped before the next batch.' );
check( 1 === count( ids( HealthScheduler::CLEANUP_HOOK ) ), 'Upgrade cleanup lost or duplicated its next batch.' );
check( 1 === count( ids( $health_hook ) ), 'The background upgrade did not leave exactly one health job.' );
$periodic = $store->fetch_action( ids( $health_hook )[0] )->get_schedule();
check( $periodic->is_recurring() && 300 === $periodic->get_recurrence(), 'Health must use AS recurrence every five minutes.' );

for ( $i = 0; $i < 50; ++$i ) {
	$health->schedule_immediate_refresh();
	$schema->schedule_schema_refresh( (string) $product_id );
	$schema->schedule_schema_refresh( $other_product_id );
}
check( 1 === count( ids( $health_hook, array( 'immediate' ) ) ), 'Connection notifications duplicated immediate checks.' );
check( 1 === count( ids( $schema_hook, array( $product_id ) ) ), 'Existing schema work was duplicated.' );
check( 1 === count( ids( $schema_hook, array( $other_product_id ) ) ), 'A different product lost its schema job.' );
$schema->schedule_schema_refresh( array( 702 ) );
$schema->schedule_schema_refresh( 0 );
update_option( 'reviewbird_enable_schema', 'no' );
$schema->schedule_schema_refresh( 702 );
check( ! ids( $schema_hook ), 'Disabled schema requests left pending work.' );
update_option( 'reviewbird_enable_schema', 'yes' );
$schema->schedule_schema_refresh( $product_id );
$schema->schedule_schema_refresh( $other_product_id );

// Failed API responses preserve cached data and leave recurrence to AS.
$good_cache = array( 'status' => 'healthy', 'has_active_subscription' => true, 'store_id' => 7 );
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
update_post_meta( $product_id, SchemaScheduler::META_KEY, array( 'last good schema' ) );
$schema->refresh_schema_reviews( $product_id );
check( array( 'last good schema' ) === get_post_meta( $product_id, SchemaScheduler::META_KEY, true ), 'A failed response erased the schema cache.' );
$response = array( 'response' => array( 'code' => 200 ), 'body' => '{"reviews":[]}' );
$schema->refresh_schema_reviews( $product_id );
check( '' === get_post_meta( $product_id, SchemaScheduler::META_KEY, true ), 'A valid empty response did not clear obsolete schema.' );

// Finish the next cleanup batch and verify action and log deletion.
$runner->process_action( ids( HealthScheduler::CLEANUP_HOOK )[0], 'Reviewbird test' );
check( 'complete' === get_option( 'reviewbird_health_cleanup_status' ), 'Cleanup did not finish.' );
$legacy_ids = implode( ',', array_map( 'intval', $legacy ) );
check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_actions} WHERE action_id IN ({$legacy_ids})" ), 'Old health actions remain.' );
check( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->actionscheduler_logs} WHERE action_id IN ({$legacy_ids})" ), 'Old health logs remain.' );
check( 2 === count( ids( $health_hook ) ), 'Cleanup removed current health jobs.' );
check( 'canceled' === $store->get_status( $old_schema ) && 'pending' === $store->get_status( $unrelated ), 'Disabled schema cleanup removed unrelated work.' );
$health->schedule_recurring_check();
check( ! ids( HealthScheduler::CLEANUP_HOOK ), 'Completed cleanup started again.' );

// Product removal cancels both jobs; a queued callback cannot recreate deleted metadata.
$deleted_product_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Deleted scheduler product' ) );
$schema->schedule_schema_refresh( $deleted_product_id );
reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', $deleted_product_id );
wp_delete_post( $deleted_product_id, true );
check( ! ids( $schema_hook, array( $deleted_product_id ) ) && ! ids( 'reviewbird_sync_product_rating', array( $deleted_product_id ) ), 'Deletion left product jobs pending.' );
$before = $calls;
$schema->refresh_schema_reviews( $deleted_product_id );
reviewbird_sync_product_rating( $deleted_product_id );
check( $before === $calls, 'A deleted product caused an API request.' );

// Moving a product to trash must cancel work before permanent deletion.
$trashed_product_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Trashed scheduler product' ) );
$schema->schedule_schema_refresh( $trashed_product_id );
reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', $trashed_product_id );
wp_trash_post( $trashed_product_id );
check( ! ids( $schema_hook, array( $trashed_product_id ) ) && ! ids( 'reviewbird_sync_product_rating', array( $trashed_product_id ) ), 'Moving a product to trash left its jobs pending.' );
$before = $calls;
$schema->refresh_schema_reviews( $trashed_product_id );
reviewbird_sync_product_rating( $trashed_product_id );
check( $before === $calls, 'A trashed product caused an API request.' );

// Shared identity work survives deletion of its original translation.
$original_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Original translated product' ) );
$translation_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Surviving translated product' ) );
update_post_meta( $original_id, '_reviewbird_product_id', $original_id );
update_post_meta( $translation_id, '_reviewbird_product_id', $original_id );
$translations = static function ( $ids, $id ) use ( $original_id, $translation_id ) {
    return in_array( $id, array( $original_id, $translation_id ), true ) ? array( $original_id, $translation_id ) : $ids;
};
add_filter( 'reviewbird_product_translation_ids', $translations, 10, 2 );
$schema->schedule_schema_refresh( $original_id );
wp_trash_post( $original_id );
check( 1 === count( ids( $schema_hook, array( $original_id ) ) ), 'Moving a product to trash removed work needed by a surviving translation.' );
wp_delete_post( $original_id, true );
check( 1 === count( ids( $schema_hook, array( $original_id ) ) ), 'Deletion removed work needed by a surviving translation.' );
$response = array( 'response' => array( 'code' => 200 ), 'body' => '{"reviews":[{"rating":5,"author":{"name":"Buyer"},"body":"Shared review"}]}' );
$schema->refresh_schema_reviews( $original_id );
check( 'Shared review' === get_post_meta( $translation_id, SchemaScheduler::META_KEY, true )[0]['reviewBody'], 'Queued work did not use the surviving translation.' );
check( '' === get_post_meta( $original_id, SchemaScheduler::META_KEY, true ), 'Queued work recreated deleted product metadata.' );
remove_filter( 'reviewbird_product_translation_ids', $translations, 10 );

// Jobs from an earlier metadata owner must move to the stable identity before deletion.
$third_translation_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'Third translated product' ) );
update_post_meta( $third_translation_id, '_reviewbird_product_id', $original_id );
$translations = static function ( $ids, $id ) use ( $translation_id, $third_translation_id ) {
    return in_array( $id, array( $translation_id, $third_translation_id ), true ) ? array( $translation_id, $third_translation_id ) : $ids;
};
add_filter( 'reviewbird_product_translation_ids', $translations, 10, 2 );
as_unschedule_all_actions( $schema_hook, array( $original_id ) );
as_enqueue_async_action( $schema_hook, array( $translation_id ), 'reviewbird' );
wp_delete_post( $translation_id, true );
check( ! ids( $schema_hook, array( $translation_id ) ) && 1 === count( ids( $schema_hook, array( $original_id ) ) ), 'Deletion did not move an intermediate translation job to the stable identity.' );
$schema->refresh_schema_reviews( $original_id );
check( 'Shared review' === get_post_meta( $third_translation_id, SchemaScheduler::META_KEY, true )[0]['reviewBody'], 'The remaining translation did not receive the schema cache.' );
remove_filter( 'reviewbird_product_translation_ids', $translations, 10 );

// A trashed translation cannot use queued results after the last live product is deleted.
$trashed_id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'trash', 'post_title' => 'Trashed translated product' ) );
$translations = static function ( $ids, $id ) use ( $third_translation_id, $trashed_id ) {
    return $id === $third_translation_id ? array( $third_translation_id, $trashed_id ) : $ids;
};
add_filter( 'reviewbird_product_translation_ids', $translations, 10, 2 );
wp_delete_post( $third_translation_id, true );
check( ! ids( $schema_hook, array( $original_id ) ), 'A trashed sibling kept the deleted product job.' );
remove_filter( 'reviewbird_product_translation_ids', $translations, 10 );

// The enqueue lock is released immediately and abandoned locks can be replaced.
$test_lock = 'reviewbird_scheduler_test_lock';
delete_option( $test_lock );
$first_expiry = time() + 60;
check( reviewbird_acquire_lock( $test_lock, $first_expiry ), 'The first request did not acquire an absent lock.' );
check( ! reviewbird_acquire_lock( $test_lock, $first_expiry + 1 ), 'A second request overwrote a live lock with a different expiry.' );
check( $first_expiry === (int) get_option( $test_lock ), 'A rejected request changed the existing lock.' );
update_option( $test_lock, time() - 1, false );
check( reviewbird_acquire_lock( $test_lock, $first_expiry + 2 ), 'An abandoned lock could not be replaced.' );
delete_option( $test_lock );
$lock_key = 'reviewbird_enqueue_' . md5( 'reviewbird_sync_product_rating:' . $product_id );
update_option( $lock_key, time() - 1, false );
reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', $product_id );
check( 1 === count( ids( 'reviewbird_sync_product_rating', array( $product_id ) ) ) && false === get_option( $lock_key ), 'An abandoned enqueue lock blocked new work.' );
as_unschedule_all_actions( 'reviewbird_sync_product_rating', array( $product_id ) );
reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', $product_id );
check( 1 === count( ids( 'reviewbird_sync_product_rating', array( $product_id ) ) ), 'The lock blocked a second update within its timeout.' );

as_schedule_single_action( time(), HealthScheduler::CLEANUP_HOOK );
wp_schedule_single_event( time(), HealthScheduler::CLEANUP_HOOK );
reviewbird\Core\Deactivator::deactivate();
foreach ( array( $health_hook, $schema_hook, 'reviewbird_sync_product_rating', HealthScheduler::CLEANUP_HOOK ) as $hook ) {
	check( ! ids( $hook ), 'Deactivation left plugin jobs pending.' );
}
check( ! wp_next_scheduled( HealthScheduler::CLEANUP_HOOK ), 'Deactivation left the WP-Cron event.' );
reviewbird\Core\Activator::activate();
$health->schedule_recurring_check();
check( 1 === count( ids( $health_hook ) ), 'Reactivation did not restore one health job.' );

if ( is_multisite() ) {
	// A network deactivation must clear each site's queue and keep unrelated work.
	$main_site_id = get_current_blog_id();
	$network = get_network();
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	check( ! empty( $admins ), 'The disposable network needs an administrator.' );
	$child_site_id = wpmu_create_blog( $network->domain, trailingslashit( $network->path ) . 'reviewbird-deactivation-' . strtolower( wp_generate_password( 8, false ) ) . '/', 'Reviewbird deactivation test', (int) $admins[0], array(), $network->id );
	check( ! is_wp_error( $child_site_id ), 'The disposable child site could not be created.' );
	$plugin_hooks = array( $health_hook, $schema_hook, 'reviewbird_sync_product_rating', HealthScheduler::CLEANUP_HOOK );
	$unrelated_actions = array();
	try {
		foreach ( array( $main_site_id, $child_site_id ) as $site_id ) {
			switch_to_blog( $site_id );
			try {
				// A new child site has not yet loaded Action Scheduler on its own request.
				( new ActionScheduler_StoreSchema() )->register_tables();
				( new ActionScheduler_LoggerSchema() )->register_tables();
				foreach ( $plugin_hooks as $hook ) {
					check( as_schedule_single_action( time() + 3600, $hook, array( $site_id ), 'reviewbird' ) > 0, 'Could not create a child-site plugin job.' );
				}
				$unrelated_actions[ $site_id ] = as_schedule_single_action( time() + 3600, 'another_plugin_job', array( $site_id ), 'reviewbird' );
				wp_schedule_single_event( time() + 3600, HealthScheduler::CLEANUP_HOOK );
				wp_schedule_single_event( time() + 3600, 'another_plugin_cron' );
			} finally {
				restore_current_blog();
			}
		}
		reviewbird\Core\Deactivator::deactivate( true );
		check( $main_site_id === get_current_blog_id(), 'Network deactivation did not restore the current site.' );
		foreach ( array( $main_site_id, $child_site_id ) as $site_id ) {
			switch_to_blog( $site_id );
			try {
				foreach ( $plugin_hooks as $hook ) {
					check( ! ids( $hook ), 'Network deactivation left a plugin job on site ' . $site_id );
				}
				check( ! wp_next_scheduled( HealthScheduler::CLEANUP_HOOK ), 'Network deactivation left plugin WP-Cron work.' );
				check( 'pending' === ActionScheduler::store()->get_status( $unrelated_actions[ $site_id ] ), 'Network deactivation removed another plugin action.' );
				check( (bool) wp_next_scheduled( 'another_plugin_cron' ), 'Network deactivation removed another plugin WP-Cron event.' );
			} finally {
				restore_current_blog();
			}
		}
	} finally {
		wp_delete_site( $child_site_id );
	}
}
echo "Scheduler integration: {$checks} checks passed with real WordPress and Action Scheduler.\n";
