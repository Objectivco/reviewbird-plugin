<?php
/**
 * Run: php tests/FrontendResilienceCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Isolated check with WordPress and Action Scheduler stubs.
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
define( 'ABSPATH', __DIR__ );
define( 'REVIEWBIRD_VERSION', 'test' );
define( 'REVIEWBIRD_API_URL_PRODUCTION', 'https://api.test' );
define( 'REVIEWBIRD_API_URL_STAGING', 'https://api.test' );
define( 'REVIEWBIRD_API_URL_DEVELOPMENT', 'https://api.test' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
$options = array( 'reviewbird_store_id' => 7, 'reviewbird_enable_widget' => 'yes', 'reviewbird_store_status' => array( 'status' => 'healthy', 'has_active_subscription' => true ) );
$transients = $meta = $queued = $hooks = $calls = array();
$checks = 0;
$response = array( 'response' => array( 'code' => 200 ), 'body' => '{"statistics":{"total_reviews":0,"average_rating":0}}' );
class WP_Error {
    public function __construct( public $code = 'offline', public $message = 'Offline', public $data = array() ) {}
    public function get_error_message() { return $this->message; }
}
class ActionScheduler { public static function is_initialized() { return true; } }
$wpdb = new class {
    public $options = 'wp_options';
    public function prepare( $query, ...$args ) { return array( $query, $args ); }
    public function query( $prepared ) {
        $GLOBALS['lock_writes'] = ( $GLOBALS['lock_writes'] ?? 0 ) + 1;
        list( $query, $args ) = $prepared;
        if ( 0 === strpos( $query, 'INSERT IGNORE' ) ) {
            list( $key, $expires ) = $args;
            if ( isset( $GLOBALS['options'][$key] ) ) { return 0; }
            $GLOBALS['options'][$key] = $expires;
            return 1;
        }
        if ( 0 === strpos( $query, 'DELETE' ) ) {
            list( $key, $expires ) = $args;
            if ( (string) ( $GLOBALS['options'][$key] ?? '' ) !== (string) $expires ) { return 0; }
            unset( $GLOBALS['options'][$key] );
            return 1;
        }
        list( $expires, $key, $now ) = $args;
        if ( ! isset( $GLOBALS['options'][$key] ) || $GLOBALS['options'][$key] > $now ) { return 0; }
        $GLOBALS['options'][$key] = $expires;
        return 1;
    }
};
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$GLOBALS['checks']; }
function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function add_option( $key, $value, $deprecated = '', $autoload = false ) {
    if ( isset( $GLOBALS['options'][$key] ) ) { return false; }
    $GLOBALS['options'][$key] = $value;
    return true;
}
function wp_cache_delete( ...$args ) {}
function get_transient( $key ) { return $GLOBALS['transients'][$key][0] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = array( $value, $ttl ); }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][$id][$key] = $value; }
function has_filter( $hook ) { return false; }
function add_filter( ...$args ) {}
function add_action( $hook, $callback, ...$args ) { $GLOBALS['hooks'][$hook] = $callback; }
function as_next_scheduled_action( $hook, $args ) { return isset( $GLOBALS['queued'][$args[0]] ); }
function as_unschedule_all_actions( $hook ) { $GLOBALS['queued'] = array_filter( $GLOBALS['queued'], static fn( $value ) => $value !== $hook ); }
function as_enqueue_async_action( $hook, $args, $group, $unique = false ) {
    check( 'reviewbird' === $group && ! $unique, 'Product jobs share one group without suppressing other products on older AS.' );
    $GLOBALS['enqueue_calls'] = ( $GLOBALS['enqueue_calls'] ?? 0 ) + 1;
    $GLOBALS['queued'][$args[0]] = $hook;
}
function home_url() { return 'https://store.test'; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function nocache_headers() { $GLOBALS['no_cache'] = true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function wp_remote_get( $url, $args ) {
    $GLOBALS['calls'][] = array( $url, $args );
    if ( str_contains( $url, '/api/public/7/' ) ) {
        preg_match( '~/api/public/7/(\d+)~', $url, $matches );
        check( (bool) get_transient( 'reviewbird_rating_sync_' . $matches[1] ), 'Set the rating lock before the HTTP call.' );
    }
    if ( isset( $GLOBALS['during_http'] ) ) { $GLOBALS['during_http'](); }
    return $GLOBALS['response'];
}
function wp_remote_request( $url, $args ) { return wp_remote_get( $url, $args ); }
function apply_filters( $hook, $value ) { return $value; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return esc_attr( $value ); }
function number_format_i18n( $value, $decimals = 0 ) { return number_format( $value, $decimals ); }
function get_locale() { return 'fr_FR'; }
class WC_Order {
    public $meta = array();
    public function get_meta( $key ) { return $this->meta[$key] ?? ''; }
    public function update_meta_data( $key, $value ) { $this->meta[$key] = $value; }
    public $saved_meta = 0;
    public function save() { throw new RuntimeException( 'Locale capture must not run a full order save.' ); }
    public function save_meta_data() { ++$this->saved_meta; }
}
class WC_Product {
    public function __construct( private $id = 5 ) {}
    public function get_id() { return $this->id; }
    public function get_status() { return 'publish'; }
    public function get_rating_count() { return 999; }
}
function wc_get_product( $id ) {
    $GLOBALS['product_reads'][] = $id;
    return 5 === $id ? $GLOBALS['block_product'] : ( 999 === $id || in_array( $id, $GLOBALS['missing_products'] ?? array(), true ) ? false : new WC_Product( $id ) );
}
function is_product() { return true; }
function doing_action( $hook ) { return false; }
function get_queried_object_id() { return 5; }
function get_posts( $args ) { return array(); }
function wp_get_environment_type() { return 'production'; }
function get_post_type( $id ) { return 'product'; }
function wc_get_logger() { return new class { public function __call( $level, $args ) {} }; }

require __DIR__ . '/../src/functions.php';
require __DIR__ . '/../src/hooks.php';
require __DIR__ . '/../src/scheduler.php';
require __DIR__ . '/../src/Integration/WooCommerce.php';
require __DIR__ . '/../src/Integration/StarRatingDisplay.php';

$integration = new reviewbird\Integration\WooCommerce();
check( isset( $hooks['woocommerce_new_order'] ) && ! isset( $hooks['woocommerce_before_order_object_save'], $hooks['woocommerce_checkout_order_created'] ), 'Capture locale only when an order is created.' );
$order = new WC_Order();
$integration->save_order_locale( 1, $order );
check( 'fr_FR' === $order->get_meta( '_reviewbird_locale' ) && 1 === $order->saved_meta, 'Capture the order locale with a meta-only save.' );
$order->meta['_reviewbird_locale'] = 'en_US';
$integration->save_order_locale( 1, $order );
check( 'en_US' === $order->get_meta( '_reviewbird_locale' ) && 1 === $order->saved_meta, 'Keep the first locale when a draft becomes an order.' );
$GLOBALS['product'] = new WC_Product();
$stars = ( new ReflectionClass( reviewbird\Integration\StarRatingDisplay::class ) )->newInstanceWithoutConstructor();
$html = $stars->filter_rating_html( '', 4.5, 0 );
check( ! str_contains( $html, 'rb-wc-rating-count' ) && str_contains( $html, 'rb-wc-star' ), 'Unknown counts must not use the main product count.' );
check( str_contains( $stars->filter_rating_html( '', 4.5, 12 ), '(12)' ), 'Keep an explicit rating count.' );
$GLOBALS['product'] = new class extends WC_Product { public function get_rating_count() { return 27; } };
$block_product = new class extends WC_Product { public function get_rating_count() { return 3; } };
$stars->set_loop_rating_product();
check( str_contains( $stars->filter_rating_html( '', 5.0, 0 ), '(27)' ), 'Loop ratings use the loop product count.' );
$stars->clear_rating_product();
check( ! str_contains( $stars->filter_rating_html( '', 5.0, 0 ), 'rb-wc-rating-count' ), 'An individual review after the loop must not inherit the product count.' );
$stars->set_rating_block_product( array( 'postId' => 5 ), array( 'blockName' => 'woocommerce/product-rating' ) );
check( str_contains( $stars->filter_rating_html( '', 5.0, 0 ), '(3)' ), 'The rating block uses its own product count.' );
check( 'x' === $stars->clear_rating_product( 'x' ) && ! str_contains( $stars->filter_rating_html( '', 5.0, 0 ), 'rb-wc-rating-count' ), 'Clear the product after the rating block renders.' );
$stars->set_rating_block_product( array( 'postId' => 5 ), array( 'blockName' => 'core/paragraph' ) );
check( ! str_contains( $stars->filter_rating_html( '', 5.0, 0 ), 'rb-wc-rating-count' ), 'Other blocks set no rating product.' );

$product = new class {
    public function get_id() { return 11; }
    public function is_type( $type ) { return false; }
};
check( 0 === reviewbird_override_review_count( 0, $product ), 'Missing ratings use the native value.' );
reviewbird_override_average_rating( 0, $product );
check( 0 === count( $calls ) && 1 === count( $queued ), 'A page must queue one job and make no HTTP call.' );
$product_reads = array();
reviewbird_sync_product_rating( 11 );
check( array( 11, 11 ) === $product_reads, 'Rating sync resolves one product before HTTP and checks it again afterward.' );
check( 0 === get_post_meta( 11, '_reviewbird_reviews_count' ) && 0.0 === get_post_meta( 11, '_reviewbird_avg_stars' ), 'Save explicit zero ratings.' );
check( array_fill( 1, 5, 0 ) === get_post_meta( 11, '_reviewbird_rating_counts' ), 'Save the empty distribution.' );
$queued = array();
reviewbird_override_review_count( 0, $product );
check( ! $queued && 1 === count( $calls ), 'Known zero ratings must not trigger another job.' );
$meta[40] = array( '_reviewbird_reviews_count' => 5, '_reviewbird_avg_stars' => 4.2 );
$variation_product = new class {
    public function get_id() { return 41; }
    public function get_parent_id() { return 40; }
    public function is_type( $type ) { return 'variation' === $type; }
};
check( 5 === reviewbird_override_review_count( 0, $variation_product ) && 4.2 === reviewbird_override_average_rating( 0, $variation_product ), 'Variations show the parent product rating.' );
check( ! $queued, 'Variations must not queue their own rating job.' );

foreach ( array( '{}', '{"statistics":{"total_reviews":2,"average_rating":0}}', '{"statistics":{"total_reviews":-1,"average_rating":0}}' ) as $index => $body ) {
    $response['body'] = $body;
    reviewbird_sync_product_rating( 20 + $index );
    check( '' === get_post_meta( 20 + $index, '_reviewbird_reviews_count' ), 'Malformed ratings must not become saved zeros.' );
}

$response['body'] = '{"statistics":{"total_reviews":1,"average_rating":5,"rating_distribution":{"5":1}}}';
foreach ( array( 199, 301, 304, 400, 500 ) as $index => $code ) {
    $response['response']['code'] = $code;
    reviewbird_sync_product_rating( 30 + $index );
    check( '' === get_post_meta( 30 + $index, '_reviewbird_reviews_count' ), 'Only successful HTTP responses may save ratings.' );
}
$response['response']['code'] = 200;

$before = count( $calls );
reviewbird_sync_product_rating( 999 );
check( $before === count( $calls ), 'Deleted products must make no rating API request.' );
check( array( 1 => 0, 2 => 0, 3 => 0, 4 => 1, 5 => 1 ) === reviewbird_normalize_rating_counts( array( 4 => '1', 5 => 1 ), 2 ), 'Normalize valid sparse rating counts.' );
foreach ( array( null, array(), array( 5 => 3 ), array( 5 => 2.5 ), array( 5 => true ), array( 0 => 2 ), array( 6 => 2 ), array( 5 => -2 ) ) as $counts ) {
    check( null === reviewbird_normalize_rating_counts( $counts, 2 ), 'Reject invalid or inconsistent rating distributions.' );
}
check( 1 === array_sum( reviewbird_normalize_rating_counts( array( 5 => 1 ), 6 ) ), 'Unrated reviews must not become invented rating buckets.' );
check( array_fill( 1, 5, 0 ) === reviewbird_normalize_rating_counts( array_fill( 1, 5, 0 ), 6 ), 'All-unrated reviews must preserve zero rating counts.' );
check( null === reviewbird_normalize_rating_counts( array( 4 => PHP_INT_MAX, 5 => 1 ), PHP_INT_MAX ), 'Bucket totals must not overflow the review count.' );
$options['reviewbird_enable_widget'] = 'no';
$queued[77] = 'reviewbird_sync_product_rating';
reviewbird_cancel_disabled_jobs( 'yes', 'no', 'reviewbird_enable_widget' );
check( ! $queued, 'Disabling the widget must cancel queued rating work.' );
check( 2 === reviewbird_override_review_count( 2, $variation_product ), 'Disabled widgets must keep native counts.' );
$options['reviewbird_enable_widget'] = 'yes';
$options['reviewbird_store_status']['status'] = 'not_connected';
check( 1.5 === reviewbird_override_average_rating( 1.5, $variation_product ), 'Disconnected stores must keep native ratings.' );
$options['reviewbird_store_status']['status'] = 'healthy';

check( reviewbird_acquire_lock( 'reviewbird_lock_test', time() + 60 ), 'The first request must acquire an absent lock.' );
$first_expiry = $options['reviewbird_lock_test'];
check( ! reviewbird_acquire_lock( 'reviewbird_lock_test', time() + 61 ) && $first_expiry === $options['reviewbird_lock_test'], 'A second request must not overwrite a live lock with a different expiry.' );
unset( $options['reviewbird_lock_test'] );
$queued = array();
$enqueue_calls = 0;
reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', 88 );
$writes_before = $lock_writes;
for ( $i = 0; $i < 24; ++$i ) { reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', 88 ); }
check( 1 === $enqueue_calls && $writes_before === $lock_writes, 'Pending jobs must cause no enqueue or option-lock write.' );
reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', 89 );
check( 2 === $enqueue_calls && isset( $queued[88], $queued[89] ), 'Different products must retain their own queued actions.' );
unset( $queued[88] );
reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', 88 );
check( 3 === $enqueue_calls, 'A completed action can be replaced immediately.' );

$response = array( 'response' => array( 'code' => 200 ), 'body' => '{"statistics":{"total_reviews":2,"average_rating":4.5,"rating_distribution":{"4":1,"5":1}}}' );
$during_http = static function () { $GLOBALS['missing_products'][] = 93; };
reviewbird_sync_product_rating( 93 );
unset( $during_http );
check( '' === get_post_meta( 93, '_reviewbird_reviews_count' ), 'A product deleted during HTTP must not receive rating metadata.' );
// The service rounds its own average; the plugin saves it as sent rather than recalculating 4.67.
$response['body'] = '{"statistics":{"total_reviews":6,"average_rating":4.7,"rating_distribution":{"1":0,"2":0,"3":0,"4":1,"5":2}}}';
reviewbird_sync_product_rating( 94 );
check( 6 === get_post_meta( 94, '_reviewbird_reviews_count' ) && 4.7 === get_post_meta( 94, '_reviewbird_avg_stars' ) && 3 === array_sum( get_post_meta( 94, '_reviewbird_rating_counts' ) ), 'Public sync must save the service average and keep unrated reviews out of the rating counts.' );
// Google requires the schema rating to match the stars shown on the page.
$saved_queue = $queued;
$options['reviewbird_enable_schema'] = 'yes';
$rated_product = new class {
    public function get_id() { return 94; }
    public function is_type( $type ) { return false; }
};
$aggregate = reviewbird_filter_structured_data( array(), $rated_product )['aggregateRating'];
check( 4.7 === reviewbird_override_average_rating( 0, $rated_product ) && 4.7 === $aggregate['ratingValue'] && 3 === $aggregate['ratingCount'] && 6 === $aggregate['reviewCount'], 'Schema and visible stars must show the service average.' );
unset( $options['reviewbird_enable_schema'] );
$queued = $saved_queue;
$response['body'] = '{"statistics":{"total_reviews":3,"average_rating":0,"rating_distribution":{"1":0,"2":0,"3":0,"4":0,"5":0}}}';
reviewbird_sync_product_rating( 95 );
check( 3 === get_post_meta( 95, '_reviewbird_reviews_count' ) && 0.0 === get_post_meta( 95, '_reviewbird_avg_stars' ) && array_fill( 1, 5, 0 ) === get_post_meta( 95, '_reviewbird_rating_counts' ), 'Public sync must accept all-unrated reviews without inventing ratings.' );

$calls = array();
$response = new WP_Error();
check( array() === reviewbird_get_cached_product_reviews( 30 ), 'An outage must return empty fallback data.' );
reviewbird_get_cached_product_reviews( 30 );
reviewbird_get_cached_product_reviews( 31 );
check( 1 === count( $calls ) && 3 === $calls[0][1]['timeout'], 'An outage must use a three-second timeout and stop repeated requests across products.' );
check( 300 === $transients['reviewbird_ssr_30'][1] && 300 === $transients['reviewbird_ssr_api_failed'][1], 'Cache failures for five minutes.' );
set_transient( 'reviewbird_ssr_32', array( 'reviews' => array( array( 'id' => 1 ) ) ), 14400 );
check( 1 === reviewbird_get_cached_product_reviews( 32 )['reviews'][0]['id'], 'An outage must preserve cached reviews.' );
unset( $transients['reviewbird_ssr_api_failed'] );
$response = array( 'response' => array( 'code' => 200 ), 'body' => '{"reviews":[]}' );
reviewbird_get_cached_product_reviews( 33 );
check( 14400 === $transients['reviewbird_ssr_33'][1], 'Cache a valid empty result for four hours.' );

$calls = array();
$_GET['reviewbird_setup'] = str_repeat( 'a', 64 );
$response = new WP_Error();
check( ! reviewbird_is_onboarding_preview(), 'A failed preview check must not grant access.' );
check( ! defined( 'DONOTCACHEPAGE' ), 'An invalid token must not disable the page cache.' );
check( 3 === $calls[0][1]['timeout'], 'Preview requests must use a three-second timeout.' );
check( 300 === $transients['reviewbird_preview_failed_' . hash( 'sha256', $_GET['reviewbird_setup'] )][1], 'Cache rejected tokens.' );
$_GET['reviewbird_setup'] = str_repeat( 'b', 64 );
check( ! reviewbird_is_onboarding_preview() && 1 === count( $calls ), 'Random tokens must share the site request limit.' );
check( ! reviewbird_is_onboarding_preview(), 'A lock miss is remembered for the request.' );
$_GET['reviewbird_setup'] = str_repeat( 'c', 64 );
$options['reviewbird_preview_check_lock'] = time() - 1;
$response = array( 'response' => array( 'code' => 200 ), 'body' => '{"onboarding_preview_allowed":true}' );
check( reviewbird_is_onboarding_preview(), 'An approved token must work after the request limit expires.' );
check( defined( 'DONOTCACHEPAGE' ) && $no_cache, 'Approved previews must remain private from page caches.' );
check( reviewbird_is_onboarding_preview() && 2 === count( $calls ), 'Repeated checks in one request must reuse the result.' );
echo "Frontend resilience: {$checks} checks passed.\n";
