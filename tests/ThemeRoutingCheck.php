<?php
/**
 * Run: php tests/ThemeRoutingCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Isolated checks for WordPress hook and request boundaries.
define( 'ABSPATH', __DIR__ );
define( 'REVIEWBIRD_VERSION', 'test' );
define( 'REVIEWBIRD_PLUGIN_URL', 'https://store.test/plugins/reviewbird/' );
$checks = 0;
$enabled = true;
$is_product = false;
$loop_rating = false;
$hooks = $styles = $options = array();
$products = array();
foreach ( array( 21, 22 ) as $id ) {
    $products[$id] = new class( $id ) {
        private $id;
        public function __construct( $id ) { $this->id = $id; }
        public function get_id() { return $this->id; }
        public function get_rating_count() { return 4; }
    };
}
$product = $products[21];
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$GLOBALS['checks']; }
function reviewbird_can_show_widget() { return $GLOBALS['enabled']; }
function post_password_required( $id ) { return ! empty( $GLOBALS['password_required'] ); }
function wc_get_product( $id ) { return $GLOBALS['products'][$id] ?? false; }
function reviewbird_render_widget( $id ) { return '<div id="reviewbird-widget-container-' . $id . '">Reviews</div>'; }
function wp_kses_post( $html ) { return $html; }
function shortcode_atts( $defaults, $atts ) { return array_merge( $defaults, $atts ); }
function wp_enqueue_style( $handle, ...$args ) { $GLOBALS['styles'][] = $handle; }
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][$hook][$priority][] = $callback; }
function has_action( $hook, $callback ) {
    foreach ( $GLOBALS['hooks'][$hook] ?? array() as $priority => $callbacks ) {
        if ( in_array( $callback, $callbacks, true ) ) { return $priority; }
    }
    return false;
}
function remove_action( $hook, $callback, $priority = 10 ) {
    $GLOBALS['hooks'][$hook][$priority] = array_filter( $GLOBALS['hooks'][$hook][$priority] ?? array(), function ( $value ) use ( $callback ) { return $value !== $callback; } );
}
function apply_filters( $hook, $value ) { return $value; }
function get_transient( $key ) { return false; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function is_product() { return $GLOBALS['is_product']; }
function doing_action( $hook ) { return $GLOBALS['loop_rating']; }
function get_queried_object_id() { return 21; }
function __( $value ) { return $value; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES ); }
function esc_html( $value ) { return esc_attr( $value ); }
function number_format_i18n( $value, $decimals = 0 ) { return number_format( $value, $decimals ); }

require __DIR__ . '/../src/Core/Plugin.php';
require __DIR__ . '/../src/Integration/StarRatingDisplay.php';

$plugin = new reviewbird\Core\Plugin();
$plugin->enqueue_star_styles();
check( array( 'reviewbird-stars' ) === $styles, 'A normal page with products needs star styles.' );
$plugin->register_product_widget_hook();
check( 14 === has_action( 'woocommerce_after_single_product_summary', array( $plugin, 'render_product_widget' ) ), 'Keep the default widget position.' );
check( 10 === has_action( 'woocommerce_after_single_product', array( $plugin, 'render_product_widget' ) ), 'Templates without Product Details need the final product hook.' );
$instance = (object) array( 'context' => array( 'postId' => 21 ) );
$block = array( 'attrs' => array( 'productId' => 21 ) );
$html = $plugin->render_block_product_widget( '<div>Embedded product</div>', $block, $instance );
check( false !== strpos( $html, 'reviewbird-widget-container-21' ), 'An embedded product needs a widget container.' );
ob_start();
$plugin->render_product_widget();
check( '' === ob_get_clean(), 'The classic fallback must not duplicate a block widget.' );
check( '' === $plugin->widget_shortcode( array( 'product_id' => 21 ) ), 'A shortcode must not duplicate the product widget.' );
check( false !== strpos( $plugin->widget_shortcode( array( 'product_id' => 22 ) ), 'reviewbird-widget-container-22' ), 'Another product needs its own widget.' );
$plugin = new reviewbird\Core\Plugin();
check( '' === $plugin->render_block_product_widget( '', $block, $instance ), 'A hidden product block must stay hidden.' );
$password_required = true;
check( 'Password form' === $plugin->render_block_product_widget( 'Password form', $block, $instance ), 'A protected product must not expose its widget.' );
$password_required = false;
ob_start();
$plugin->render_product_widget();
$plugin->render_product_widget();
check( 1 === substr_count( ob_get_clean(), 'reviewbird-widget-container-21' ), 'The two native hooks must output one widget.' );

$stars = ( new ReflectionClass( reviewbird\Integration\StarRatingDisplay::class ) )->newInstanceWithoutConstructor();
foreach ( array( 0, 20 ) as $priority ) {
    $hooks['woocommerce_single_product_summary'] = array( $priority => array( 'woocommerce_template_single_rating' ) );
    $stars->replace_single_product_rating();
    check( false === has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating' ), 'Remove the callback at its actual priority.' );
    check( 6 === has_action( 'woocommerce_single_product_summary', 'reviewbird_display_single_rating' ), 'Register the replacement once.' );
}
$html = $stars->filter_rating_html( 'native', 4.5, 4 );
check( false !== strpos( $html, 'rb-wc-rating--static' ) && false === strpos( $html, 'role="button"' ), 'Archive ratings must be static.' );
$is_product = true;
check( false !== strpos( $stars->filter_rating_html( 'native', 4.5, 4 ), 'role="button"' ), 'Keep the active product widget event contract.' );
$loop_rating = true;
check( false === strpos( $stars->filter_rating_html( 'native', 4.5, 4 ), 'role="button"' ), 'Related product loop ratings must be static.' );
$options['reviewbird_star_color'] = '#abcdef';
check( false !== strpos( $stars->filter_rating_html( 'native', 4.5, 4 ), 'color: #abcdef' ), 'Keep the last valid color after the transient expires.' );
$enabled = false;
check( 'native' === $stars->filter_rating_html( 'native', 4.5, 4 ), 'A disabled widget must keep native rating HTML.' );
$styles = array();
$plugin->enqueue_star_styles();
check( ! $styles, 'A disabled widget must not load replacement styles.' );
check( array( 'reviews' => array() ) === $plugin->remove_reviews_tab( array( 'reviews' => array() ) ), 'Keep native review navigation when the widget is disabled.' );
$native_priority = 20;
$hooks['woocommerce_single_product_summary'] = array( $native_priority => array( 'woocommerce_template_single_rating' ) );
$stars->replace_single_product_rating();
check( $native_priority === has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating' ), 'A disabled widget must keep the native rating callback.' );

$wp = (object) array( 'query_vars' => array( 'rest_route' => '/reviewbird/v1/products' ) );
$_SERVER['REQUEST_URI'] = '/wp-json/reviewbird/v1/products';
check( ! $plugin->enable_wc_auth_for_reviewbird_api( false ), 'Do not grant API-key access before REST dispatch.' );
define( 'REST_REQUEST', true );
foreach ( array(
    array( '/wp-json/reviewbird/v1/products', '/reviewbird/v1/products', true ),
    array( '/?rest_route=%2Freviewbird%2Fv1%2Fproducts', '/reviewbird/v1/products', true ),
    array( '/shop/index.php/custom-api/reviewx/v1/reviews', '/reviewx/v1/reviews', true ),
    array( '/wp-json/wp/v2/users?x=/wp-json/reviewbird/', '/wp/v2/users', false ),
    array( '/wp-json/reviewbird/v1/products?rest_route=/wp/v2/users', '/wp/v2/users', false ),
    array( '/?rest_route=/reviewbird-other/v1/products', '/reviewbird-other/v1/products', false ),
    array( '/wp-json/reviewbird/v1/products', array( '/reviewbird/v1/products' ), false ),
) as list( $uri, $route, $expected ) ) {
    $_SERVER['REQUEST_URI'] = $uri;
    $wp->query_vars['rest_route'] = $route;
    check( $expected === $plugin->enable_wc_auth_for_reviewbird_api( false ), 'Authenticate only the route WordPress resolved: ' . $uri );
}
check( $plugin->enable_wc_auth_for_reviewbird_api( true ), 'Preserve native WooCommerce authentication.' );
echo "Theme and REST routing: {$checks} checks passed.\n";
