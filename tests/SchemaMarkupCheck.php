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
require __DIR__ . '/../src/scheduler.php';

class ActionScheduler {
    public static function is_initialized() { return true; }
}
function reviewbird_is_schema_enabled() { return $GLOBALS['enabled']; }
function reviewbird_is_store_connected() { return $GLOBALS['connected']; }
function reviewbird_product_meta_id( $id ) { return $GLOBALS['redirects'][ $id ] ?? $id; }
function reviewbird_product_id( $id ) { return $GLOBALS['shared_ids'][ $id ] ?? $id; }
function reviewbird_product_translation_ids( $id ) { return $GLOBALS['translations'][ $id ] ?? array( $id ); }
function reviewbird_find_product( $id ) {
    $GLOBALS['product_reads'][] = $id;
    $id = reviewbird_product_meta_id( $id );
    return in_array( $id, $GLOBALS['deleted'] ?? array(), true ) ? false : wc_get_product( $id );
}
function get_post( $id ) { return (object) array( 'post_type' => 'product' ); }
function wc_get_product( $id ) {
    return new class( $id, $GLOBALS['product_statuses'][ $id ] ?? 'publish' ) {
        private $id;
        private $status;
        public function __construct( $id, $status ) { $this->id = $id; $this->status = $status; }
        public function get_id() { return $this->id; }
        public function get_status() { return $this->status; }
    };
}
function reviewbird_get_store_id() { return 7; }
class WP_Error { public function get_error_message() { return 'Offline'; } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function reviewbird_api_request( $endpoint ) {
    ++$GLOBALS['http_calls'];
    if ( isset( $GLOBALS['during_http'] ) ) { $GLOBALS['during_http'](); }
    return is_wp_error( $GLOBALS['response'] ) ? $GLOBALS['response'] : json_decode( $GLOBALS['response']['body'], true );
}
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ][0] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = array( $value, $ttl ); }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $key ] ); }
function update_post_meta( $id, $key, $value ) { $GLOBALS['last_meta_write'] = $id; $GLOBALS['meta'][ $key ] = $value; }
function as_unschedule_all_actions( $hook, $args = null ) {
    $GLOBALS['cancelled'][] = array( $GLOBALS['blog_id'] ?? 1, $hook, $args );
    if ( null === $args ) { $GLOBALS['queued'] = array(); } else { unset( $GLOBALS['queued'][ $args[0] ] ); }
}
function absint( $id ) { return abs( (int) $id ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function as_next_scheduled_action( $hook, $args ) { return $GLOBALS['queued'][ $args[0] ] ?? false; }
function as_enqueue_async_action( $hook, $args, $group, $unique = false ) {
    check( 'reviewbird' === $group && ! $unique );
    $GLOBALS['queued'][ $args[0] ] = true;
    ++$GLOBALS['jobs'];
}
function check( $condition ) {
    if ( ! $condition ) { throw new RuntimeException( 'Schema recovery check failed.' ); }
}

$product = new class {
    public function get_id() { return 123; }
};
$key = '_reviewbird_schema_reviews';
$original = array( 'review' => array( 'native review' ) );
foreach ( array( '', 'invalid', array(), array( array( '@type' => 'Review' ) ) ) as $cached ) {
    $GLOBALS['enabled'] = true;
    $GLOBALS['connected'] = true;
    $GLOBALS['queued'] = array();
    $GLOBALS['jobs'] = 0;
    $GLOBALS['meta'] = array( '_reviewbird_avg_stars' => 4.96, '_reviewbird_reviews_count' => 12, $key => $cached );
    $markup = reviewbird_filter_structured_data( $original, $product );
    reviewbird_filter_structured_data( $original, $product );
    check( 4.96 === $markup['aggregateRating']['ratingValue'] );
    check( 1 === $GLOBALS['jobs'] );
    check( ( is_array( $cached ) && $cached ? $cached : $original['review'] ) === $markup['review'] );
    check( 12 === $markup['aggregateRating']['ratingCount'] && 12 === $markup['aggregateRating']['reviewCount'] );
}
// Unrated reviews raise reviewCount but not ratingCount.
$GLOBALS['meta'] = array( '_reviewbird_avg_stars' => 5.0, '_reviewbird_reviews_count' => 6, '_reviewbird_rating_counts' => array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 1 ) );
$markup = reviewbird_filter_structured_data( $original, $product );
check( 5.0 === $markup['aggregateRating']['ratingValue'] && 1 === $markup['aggregateRating']['ratingCount'] && 6 === $markup['aggregateRating']['reviewCount'] );
$GLOBALS['meta'] = array( '_reviewbird_avg_stars' => 0.0, '_reviewbird_reviews_count' => 3, '_reviewbird_rating_counts' => array_fill( 1, 5, 0 ) );
check( ! isset( reviewbird_filter_structured_data( $original, $product )['aggregateRating'] ) );
foreach ( array( 'disabled', 'disconnected', 'zero', 'unknown' ) as $state ) {
    $GLOBALS['enabled'] = 'disabled' !== $state;
    $GLOBALS['connected'] = 'disconnected' !== $state;
    $GLOBALS['queued'] = array();
    $GLOBALS['jobs'] = 0;
    $GLOBALS['meta'] = array( '_reviewbird_reviews_count' => 'zero' === $state ? 0 : '' );
    reviewbird_filter_structured_data( $original, $product );
    check( 0 === $GLOBALS['jobs'] );
}
$GLOBALS['enabled'] = $GLOBALS['connected'] = true;
$GLOBALS['queued'] = array();
$GLOBALS['jobs'] = $GLOBALS['http_calls'] = 0;
$GLOBALS['meta'] = array( '_reviewbird_avg_stars' => 4.5, '_reviewbird_reviews_count' => 2, $key => array( array( 'reviewBody' => 'last good review' ) ) );
$last_good = $GLOBALS['meta'][ $key ];
$GLOBALS['response'] = new WP_Error();
reviewbird_refresh_schema_reviews( 123 );
check( $last_good === $GLOBALS['meta'][ $key ] );
check( 300 === $GLOBALS['transients']['reviewbird_schema_refresh_123'][1] );
reviewbird_filter_structured_data( $original, $product );
check( 0 === $GLOBALS['jobs'] );
delete_transient( 'reviewbird_schema_refresh_123' ); // Simulate expiry after the outage.
reviewbird_filter_structured_data( $original, $product );
check( 1 === $GLOBALS['jobs'] );
$GLOBALS['response'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"reviews":[]}' );
$GLOBALS['product_reads'] = array();
reviewbird_refresh_schema_reviews( 123 );
check( array( 123, 123 ) === $GLOBALS['product_reads'] );
$GLOBALS['queued'] = array();
check( '' === get_post_meta( 123, $key, true ) );
check( DAY_IN_SECONDS === $GLOBALS['transients']['reviewbird_schema_refresh_123'][1] );
reviewbird_filter_structured_data( $original, $product );
check( 1 === $GLOBALS['jobs'] ); // A valid empty result has a refresh deadline too.
$GLOBALS['response']['body'] = '{"reviews":["invalid review"]}';
$GLOBALS['meta'][ $key ] = $last_good;
try { reviewbird_refresh_schema_reviews( 123 ); } catch ( TypeError $error ) {}
check( 300 === $GLOBALS['transients']['reviewbird_schema_refresh_123'][1] && $last_good === $GLOBALS['meta'][ $key ] );
$GLOBALS['enabled'] = false;
reviewbird_cancel_disabled_jobs( 'yes', 'no', 'reviewbird_enable_schema' );
reviewbird_invalidate_schema_reviews( 123 );
check( ! get_transient( 'reviewbird_schema_refresh_123' ) && ! $GLOBALS['queued'] );
$GLOBALS['enabled'] = true;
reviewbird_filter_structured_data( $original, $product );
check( 2 === $GLOBALS['jobs'] );
$GLOBALS['queued'] = array();
reviewbird_schedule_schema_refresh( 123 );
reviewbird_schedule_schema_refresh( 123 );
check( 3 === $GLOBALS['jobs'] ); // An existing product action is reused.
$GLOBALS['translations'][123] = array( 123, 124 );
$before = count( $GLOBALS['cancelled'] );
reviewbird_cancel_deleted_product_jobs( 123, (object) array( 'post_type' => 'product' ) );
check( $before === count( $GLOBALS['cancelled'] ) );
$GLOBALS['product_statuses'][124] = 'trash'; // A trashed sibling must not preserve work.
reviewbird_cancel_deleted_product_jobs( 123 ); // The trash hook supplies only the ID.
check( $before + 2 === count( $GLOBALS['cancelled'] ) && ! $GLOBALS['queued'] );
$GLOBALS['deleted'] = array( 123 );
$before = $GLOBALS['http_calls'];
reviewbird_schedule_schema_refresh( 123 );
reviewbird_refresh_schema_reviews( 123 );
check( ! $GLOBALS['queued'] && $before === $GLOBALS['http_calls'] );
$GLOBALS['redirects'][123] = 124;
reviewbird_schedule_schema_refresh( 123 );
check( isset( $GLOBALS['queued'][124] ) );

$GLOBALS['shared_ids'][124] = 120;
$GLOBALS['translations'][124] = array( 124, 125 );
$GLOBALS['queued'] = array( 124 => true );
reviewbird_cancel_deleted_product_jobs( 124, (object) array( 'post_type' => 'product' ) );
check( ! isset( $GLOBALS['queued'][124] ) && isset( $GLOBALS['queued'][120] ) );

$GLOBALS['redirects'][120] = 124;
$GLOBALS['response'] = array( 'response' => array( 'code' => 200 ), 'body' => '{"reviews":[{"rating":5,"body":"Current review"}]}' );
$GLOBALS['during_http'] = static function () {
    $GLOBALS['deleted'][] = 124;
    $GLOBALS['redirects'][120] = 125;
};
reviewbird_refresh_schema_reviews( 120 );
unset( $GLOBALS['during_http'] );
check( 125 === $GLOBALS['last_meta_write'] ); // Re-resolve the stable identity after HTTP.

function is_multisite() { return true; }
function get_current_network_id() { return 5; }
function get_sites( $args ) { check( 5 === $args['network_id'] ); return array( 1, 2 ); }
function switch_to_blog( $id ) { $GLOBALS['blog_stack'][] = $GLOBALS['blog_id']; $GLOBALS['blog_id'] = $id; }
function restore_current_blog() { $GLOBALS['blog_id'] = array_pop( $GLOBALS['blog_stack'] ); }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['cron_cleared'][] = $GLOBALS['blog_id']; }
function delete_option( $key ) {}
$GLOBALS['blog_id'] = 3;
$GLOBALS['cancelled'] = $GLOBALS['cron_cleared'] = array();
reviewbird_deactivate( true );
check( 3 === $GLOBALS['blog_id'] && array( 1, 2 ) === $GLOBALS['cron_cleared'] );
check( array( 1, 1, 1, 1, 2, 2, 2, 2 ) === array_column( $GLOBALS['cancelled'], 0 ) );
echo "Schema and lifecycle recovery checks passed.\n";
