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
$options = array( 'reviewbird_store_id' => 7 );
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
    public function prepare( $query, ...$args ) { return $args; }
    public function query( $args ) {
        list( $expires, $key, $now ) = $args;
        if ( $GLOBALS['options'][$key] > $now ) { return 0; }
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
function as_enqueue_async_action( $hook, $args, $group ) {
    check( 'reviewbird' === $group, 'Rating jobs use the shared reviewbird group.' );
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
class WC_Product { public function get_rating_count() { return 999; } }

require __DIR__ . '/../src/functions.php';
require __DIR__ . '/../src/Integration/RatingOverride.php';
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
$rated = new class extends WC_Product {
    public function get_average_rating() { return 4.5; }
    public function get_rating_count() { return 7; }
};
$stars->remember_rated_product( 4.5, $rated );
check( str_contains( $stars->filter_rating_html( '', 4.5, 0 ), '(7)' ), 'Loop ratings use the count of the product just rated.' );
check( ! str_contains( $stars->filter_rating_html( '', 3.0, 0 ), 'rb-wc-rating-count' ), 'A different rating must not borrow that count.' );

$ratings = new reviewbird\Integration\RatingOverride();
$product = new class { public function get_id() { return 11; } };
check( 0 === $ratings->override_rating_count( 0, $product ), 'Missing ratings use the native value.' );
$ratings->override_average_rating( 0, $product );
check( 0 === count( $calls ) && 1 === count( $queued ), 'A page must queue one job and make no HTTP call.' );
$hooks['reviewbird_sync_product_rating']( 11 );
check( 0 === get_post_meta( 11, '_reviewbird_reviews_count' ) && 0.0 === get_post_meta( 11, '_reviewbird_avg_stars' ), 'Save explicit zero ratings.' );
check( array_fill( 1, 5, 0 ) === get_post_meta( 11, '_reviewbird_rating_counts' ), 'Save the empty distribution.' );
$queued = array();
$ratings->override_rating_count( 0, $product );
check( ! $queued && 1 === count( $calls ), 'Known zero ratings must not trigger another job.' );

foreach ( array( '{}', '{"statistics":{"total_reviews":2,"average_rating":0}}', '{"statistics":{"total_reviews":-1,"average_rating":0}}' ) as $index => $body ) {
    $response['body'] = $body;
    reviewbird_sync_product_rating( 20 + $index );
    check( '' === get_post_meta( 20 + $index, '_reviewbird_reviews_count' ), 'Malformed ratings must not become saved zeros.' );
}

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
