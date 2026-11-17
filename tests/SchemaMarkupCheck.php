<?php
/**
 * Run: php tests/SchemaMarkupCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Isolated check with WordPress and Action Scheduler stubs.

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );
require __DIR__ . '/../src/Integration/SchemaScheduler.php';
require __DIR__ . '/../src/Integration/SchemaMarkup.php';

class ActionScheduler {
    public static function is_initialized() { return true; }
}
function reviewbird_is_schema_enabled() { return $GLOBALS['enabled']; }
function reviewbird_is_store_connected() { return $GLOBALS['connected']; }
function reviewbird_product_meta_id( $id ) { return $GLOBALS['redirects'][ $id ] ?? $id; }
function reviewbird_product_id( $id ) { return $GLOBALS['shared_ids'][ $id ] ?? $id; }
function reviewbird_product_translation_ids( $id ) { return $GLOBALS['translations'][ $id ] ?? array( $id ); }
function reviewbird_find_product( $id ) { return ! in_array( $id, $GLOBALS['deleted'] ?? array(), true ); }
function get_post( $id ) { return (object) array( 'post_type' => 'product' ); }
function wc_get_product( $id ) {
    return new class( $GLOBALS['product_statuses'][ $id ] ?? 'publish' ) {
        private $status;
        public function __construct( $status ) { $this->status = $status; }
        public function get_status() { return $this->status; }
    };
}
function reviewbird_get_store_id() { return 7; }
function reviewbird_get_api_url() { return 'https://example.test'; }
function reviewbird_should_disable_ssl_verify() { return false; }
function home_url() { return 'https://store.test'; }
class WP_Error { public function get_error_message() { return 'Offline'; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_get( $url, $args ) { ++$GLOBALS['http_calls']; return $GLOBALS['response']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ][0] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = array( $value, $ttl ); }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $key ] ); }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $key ] = $value; }
function as_unschedule_all_actions( $hook, $args = null ) {
    $GLOBALS['cancelled'][] = array( $GLOBALS['blog_id'] ?? 1, $hook, $args );
    if ( null === $args ) { $GLOBALS['queued'] = array(); } else { unset( $GLOBALS['queued'][ $args[0] ] ); }
}
function absint( $id ) { return abs( (int) $id ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function reviewbird_enqueue_product_action( $hook, $id ) {
    if ( ! as_next_scheduled_action( $hook, array( $id ) ) ) {
        as_enqueue_async_action( $hook, array( $id ), 'reviewbird-product-' . $id, true );
    }
}
function as_next_scheduled_action( $hook, $args ) {
    $seen = $GLOBALS['queued'][ $args[0] ] ?? false;
    if ( $GLOBALS['interleave'] ?? false ) {
        $GLOBALS['interleave'] = false;
        ( new reviewbird\Integration\SchemaScheduler() )->schedule_schema_refresh( $args[0] );
    }
    return $seen;
}
function as_enqueue_async_action( $hook, $args, $group, $unique = false ) {
    check( 'reviewbird-product-' . $args[0] === $group && $unique );
    if ( $unique && isset( $GLOBALS['queued'][ $args[0] ] ) ) { return; }
    $GLOBALS['queued'][ $args[0] ] = true;
    ++$GLOBALS['jobs'];
}
function check( $condition ) {
    if ( ! $condition ) { throw new RuntimeException( 'Schema recovery check failed.' ); }
}

$product = new class {
    public function get_id() { return 123; }
};
$schema = new reviewbird\Integration\SchemaMarkup();
$key = reviewbird\Integration\SchemaScheduler::META_KEY;
$original = array( 'review' => array( 'native review' ) );
foreach ( array( '', 'invalid', array(), array( array( '@type' => 'Review' ) ) ) as $cached ) {
    $GLOBALS['enabled'] = true;
    $GLOBALS['connected'] = true;
    $GLOBALS['queued'] = array();
    $GLOBALS['jobs'] = 0;
    $GLOBALS['meta'] = array( '_reviewbird_avg_stars' => 4.96, '_reviewbird_reviews_count' => 12, $key => $cached );
    $markup = $schema->filter_woocommerce_structured_data( $original, $product );
    $schema->filter_woocommerce_structured_data( $original, $product );
    check( 4.96 === $markup['aggregateRating']['ratingValue'] );
    check( 1 === $GLOBALS['jobs'] );
    check( ( is_array( $cached ) && $cached ? $cached : $original['review'] ) === $markup['review'] );
}
foreach ( array( 'disabled', 'disconnected', 'zero', 'unknown' ) as $state ) {
    $GLOBALS['enabled'] = 'disabled' !== $state;
    $GLOBALS['connected'] = 'disconnected' !== $state;
    $GLOBALS['queued'] = array();
    $GLOBALS['jobs'] = 0;
    $GLOBALS['meta'] = array( '_reviewbird_reviews_count' => 'zero' === $state ? 0 : '' );
    $schema->filter_woocommerce_structured_data( $original, $product );
    check( 0 === $GLOBALS['jobs'] );
}
$scheduler = new reviewbird\Integration\SchemaScheduler();
$GLOBALS['enabled'] = $GLOBALS['connected'] = true;
$GLOBALS['queued'] = array();
$GLOBALS['jobs'] = $GLOBALS['http_calls'] = 0;
$GLOBALS['meta'] = array( '_reviewbird_avg_stars' => 4.5, '_reviewbird_reviews_count' => 2, $key => array( array( 'reviewBody' => 'last good review' ) ) );
$last_good = $GLOBALS['meta'][ $key ];
$GLOBALS['response'] = new WP_Error();
$scheduler->refresh_schema_reviews( 123 );
check( $last_good === $GLOBALS['meta'][ $key ] );
check( 300 === $GLOBALS['transients']['reviewbird_schema_refresh_123'][1] );
$schema->filter_woocommerce_structured_data( $original, $product );
check( 0 === $GLOBALS['jobs'] );
delete_transient( 'reviewbird_schema_refresh_123' ); // Simulate expiry after the outage.
$schema->filter_woocommerce_structured_data( $original, $product );
check( 1 === $GLOBALS['jobs'] );
$GLOBALS['response'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"reviews":[]}' );
$scheduler->refresh_schema_reviews( 123 );
$GLOBALS['queued'] = array();
check( '' === get_post_meta( 123, $key, true ) );
check( DAY_IN_SECONDS === $GLOBALS['transients']['reviewbird_schema_refresh_123'][1] );
$schema->filter_woocommerce_structured_data( $original, $product );
check( 1 === $GLOBALS['jobs'] ); // A valid empty result has a refresh deadline too.
$GLOBALS['response']['body'] = '{"reviews":["invalid review"]}';
$GLOBALS['meta'][ $key ] = $last_good;
try { $scheduler->refresh_schema_reviews( 123 ); } catch ( TypeError $error ) {}
check( 300 === $GLOBALS['transients']['reviewbird_schema_refresh_123'][1] && $last_good === $GLOBALS['meta'][ $key ] );
$GLOBALS['enabled'] = false;
$scheduler->cancel_disabled_schema_actions( 'yes', 'no' );
$scheduler->invalidate_schema_reviews( 123 );
check( ! get_transient( 'reviewbird_schema_refresh_123' ) && ! $GLOBALS['queued'] );
$GLOBALS['enabled'] = true;
$schema->filter_woocommerce_structured_data( $original, $product );
check( 2 === $GLOBALS['jobs'] );
$GLOBALS['queued'] = array();
$GLOBALS['interleave'] = true;
$scheduler->schedule_schema_refresh( 123 );
check( 3 === $GLOBALS['jobs'] ); // Native uniqueness rejects an overlapping enqueue.
$GLOBALS['translations'][123] = array( 123, 124 );
$before = count( $GLOBALS['cancelled'] );
$scheduler->cancel_deleted_product_actions( 123, (object) array( 'post_type' => 'product' ) );
check( $before === count( $GLOBALS['cancelled'] ) );
$GLOBALS['product_statuses'][124] = 'trash'; // A trashed sibling must not preserve work.
$scheduler->cancel_deleted_product_actions( 123 ); // The trash hook supplies only the ID.
check( $before + 2 === count( $GLOBALS['cancelled'] ) && ! $GLOBALS['queued'] );
$GLOBALS['deleted'] = array( 123 );
$before = $GLOBALS['http_calls'];
$scheduler->schedule_schema_refresh( 123 );
$scheduler->refresh_schema_reviews( 123 );
check( ! $GLOBALS['queued'] && $before === $GLOBALS['http_calls'] );
$GLOBALS['redirects'][123] = 124;
$scheduler->schedule_schema_refresh( 123 );
check( isset( $GLOBALS['queued'][124] ) );

$GLOBALS['shared_ids'][124] = 120;
$GLOBALS['translations'][124] = array( 124, 125 );
$GLOBALS['queued'] = array( 124 => true );
$scheduler->cancel_deleted_product_actions( 124, (object) array( 'post_type' => 'product' ) );
check( ! isset( $GLOBALS['queued'][124] ) && isset( $GLOBALS['queued'][120] ) );

require __DIR__ . '/../src/Integration/HealthScheduler.php';
require __DIR__ . '/../src/Core/Deactivator.php';
function is_multisite() { return true; }
function get_current_network_id() { return 5; }
function get_sites( $args ) { check( 5 === $args['network_id'] ); return array( 1, 2 ); }
function switch_to_blog( $id ) { $GLOBALS['blog_stack'][] = $GLOBALS['blog_id']; $GLOBALS['blog_id'] = $id; }
function restore_current_blog() { $GLOBALS['blog_id'] = array_pop( $GLOBALS['blog_stack'] ); }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['cron_cleared'][] = $GLOBALS['blog_id']; }
function delete_option( $key ) {}
$GLOBALS['blog_id'] = 3;
$GLOBALS['cancelled'] = $GLOBALS['cron_cleared'] = array();
reviewbird\Core\Deactivator::deactivate( true );
check( 3 === $GLOBALS['blog_id'] && array( 1, 2 ) === $GLOBALS['cron_cleared'] );
check( array( 1, 1, 1, 1, 2, 2, 2, 2 ) === array_column( $GLOBALS['cancelled'], 0 ) );
echo "Schema and lifecycle recovery checks passed.\n";
