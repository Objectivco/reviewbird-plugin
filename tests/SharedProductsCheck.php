<?php
/**
 * Run: php tests/SharedProductsCheck.php [polylang|wpml|wpml5|plain]
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This isolated check uses small WordPress and WooCommerce stubs.
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
define( 'ABSPATH', __DIR__ );
define( 'REVIEWBIRD_VERSION', 'test' );
$mode = $argv[1] ?? 'polylang';
$wpml_mode = in_array( $mode, array( 'wpml', 'wpml5' ), true );
if ( $wpml_mode ) { define( 'ICL_SITEPRESS_VERSION', 'wpml5' === $mode ? '5.0.0' : '4.9.0' ); }
$products = array();
$meta = array();
$groups = array();
$languages = array();
$default_language = 'en';
$purchases = array();
$orders = array();
$checks = 0;
$wpml_language = 'fi';
$wpml_language_stack = array();
$comment_meta = array();
$registered_filters = array();
$filter_callbacks = array();

class WP_Error { public function __construct( ...$args ) {} }
class WooCommerce {}
class WC_Order_Item_Product {
    private $parent;
    private $variation;
    public function __construct( int $parent = 20, int $variation = 21 ) { $this->parent = $parent; $this->variation = $variation; }
    public function get_product_id( $context = 'view' ) { return $this->parent; }
    public function get_variation_id( $context = 'view' ) { return $this->variation; }
    public function get_name( $context = 'view' ) { return 'Finnish name'; }
}
class WP_REST_Request {
    private $params;
    private $headers;
    private $route;
    public function __construct( array $params = array(), array $headers = array(), string $route = '/wc/v3/orders' ) { $this->params = $params; $this->headers = $headers; $this->route = $route; }
    public function get_param( $key ) { return $this->params[$key] ?? null; }
    public function get_route() { return $this->route; }
    public function get_header( $key ) { return $this->headers[$key] ?? ''; }
}
class WP_REST_Response {
    public $data;
    public $headers = array();
    public function __construct( $data, $status = 200 ) { $this->data = $data; }
    public function header( $key, $value ) { $this->headers[$key] = $value; }
}
class SharedTestProduct {
    public $id;
    public $type;
    public $parent;
    public $children;
    public $status = 'publish';
    public $attributes = array( 'attribute_size' => 'large' );
    public function __construct( int $id, string $type = 'product', int $parent = 0, array $children = array() ) { $this->id = $id; $this->type = $type; $this->parent = $parent; $this->children = $children; }
    public function get_id() { return $this->id; }
    public function get_status() { return $this->status; }
    public function get_parent_id() { return $this->parent; }
    public function get_children() { return $this->children; }
    public function get_type() { return 'product_variation' === $this->type ? 'variation' : ( $this->children ? 'variable' : 'simple' ); }
    public function is_type( $type ) { return $type === $this->get_type(); }
    public function get_name() { return 'Name ' . $this->id; }
    public function get_permalink() { return 'https://store.test/product/' . $this->id; }
    public function get_image_id() { return $this->id; }
    public function get_gallery_image_ids() { return array(); }
    public function get_variation_attributes() { return $this->attributes; }
    public function exists() { return true; }
    public function __call( $name, $args ) { return 'get_attributes' === $name ? array() : ''; }
}
function check( $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$GLOBALS['checks']; }
function wc_get_product( $id ) { return $GLOBALS['products'][$id] ?? false; }
function get_post_type( $id ) { return isset( $GLOBALS['products'][$id] ) ? $GLOBALS['products'][$id]->type : false; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][$id][$key] = $value; }
function get_posts( array $args ) {
    $ids = array();
    foreach ( $GLOBALS['products'] as $id => $product ) {
        if ( (string) get_post_meta( $id, $args['meta_key'], true ) === (string) $args['meta_value'] ) { $ids[] = $id; }
    }
    sort( $ids );
    return array_slice( $ids, 0, $args['posts_per_page'] );
}
function has_filter( $hook ) {
    return ! empty( $GLOBALS['filter_callbacks'][$hook] ) || ( $GLOBALS['wpml_mode'] && in_array( $hook, array( 'wpml_original_element_id', 'wpml_element_trid', 'wpml_current_language', 'wpml_post_language_details' ), true ) );
}
function apply_filters( $hook, $value, ...$args ) {
    if ( $GLOBALS['wpml_mode'] ) {
        if ( 'wpml_current_language' === $hook ) { $value = $GLOBALS['wpml_language']; }
        if ( 'wpml_post_language_details' === $hook ) { $locale = $GLOBALS['languages'][$args[0]] ?? ''; $value = $locale ? array( 'language_code' => substr( $locale, 0, 2 ), 'locale' => $locale ) : array(); }
        if ( 'wpml_element_trid' === $hook ) { $value = $args[0]; }
        if ( 'wpml_original_element_id' === $hook ) { $value = min( array_values( $GLOBALS['groups'][$args[0]] ?? array( $args[0] ) ) ); }
        if ( 'wpml_get_element_translations' === $hook ) {
            $ids = $GLOBALS['groups'][$args[0]] ?? array();
            if ( ! ( $args[3] ?? false ) ) { $ids = array_filter( $ids, static function ( $id ) { return 'publish' === $GLOBALS['products'][$id]->get_status(); } ); }
            $value = array_map( static function ( $id ) { return (object) array( 'element_id' => $id ); }, $ids );
        }
    }
    $priorities = $GLOBALS['filter_callbacks'][$hook] ?? array();
    ksort( $priorities );
    foreach ( $priorities as $callbacks ) {
        foreach ( $callbacks as list( $callback, $accepted_args ) ) {
            $value = $callback( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted_args ) );
        }
    }
    return $value;
}
if ( 'polylang' === $mode ) {
    function pll_get_post_translations( $id ) { return $GLOBALS['groups'][$id] ?? array(); }
    function pll_get_post( $id, $language ) { return $GLOBALS['groups'][$id][$language] ?? 0; }
    function pll_default_language() { return $GLOBALS['default_language']; }
    function pll_get_post_language( $id, $field ) { return $GLOBALS['languages'][$id] ?? false; }
}
function wc_get_products( array $args ) {
    $GLOBALS['last_product_query'] = $args;
    $parents = array_values( array_filter( $GLOBALS['products'], static function ( $product ) { return 'product' === $product->type; } ) );
    if ( $GLOBALS['wpml_mode'] && 'all' !== $GLOBALS['wpml_language'] ) { $parents = array_values( array_filter( $parents, static function ( $product ) { return 0 === strpos( $GLOBALS['languages'][$product->id] ?? 'en_US', $GLOBALS['wpml_language'] ); } ) ); }
    return (object) array( 'products' => array_slice( $parents, ( $args['page'] - 1 ) * $args['limit'], $args['limit'] ), 'total' => count( $parents ), 'max_num_pages' => (int) ceil( count( $parents ) / $args['limit'] ) );
}
function get_the_terms() { return array(); }
function wp_get_post_terms() { return array(); }
function get_ancestors() { return array(); }
function wp_get_attachment_url( $id ) { return 'https://store.test/image/' . $id; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function get_comment_meta( $id, $key, $single = false ) { return $single ? ( $GLOBALS['comment_meta'][$id][$key] ?? '' ) : array(); }
function get_locale() { return 'en_US'; }
function add_action( ...$args ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['registered_filters'][$hook] = array( $hook, $callback, $priority, $accepted_args );
    $GLOBALS['filter_callbacks'][$hook][$priority][] = array( $callback, $accepted_args );
}
function has_action( $hook ) { return $GLOBALS['wpml_mode'] && 'wpml_switch_language' === $hook; }
function do_action( $hook, ...$args ) {
    if ( $GLOBALS['wpml_mode'] && 'wpml_switch_language' === $hook ) {
        if ( 'wpml5' === $GLOBALS['mode'] ) {
            if ( null === $args[0] ) { $GLOBALS['wpml_language'] = array_pop( $GLOBALS['wpml_language_stack'] ); return; }
            $GLOBALS['wpml_language_stack'][] = $GLOBALS['wpml_language'];
        }
        $GLOBALS['wpml_language'] = $args[0];
    }
}
function absint( $id ) { return abs( (int) $id ); }
function __( $text ) { return $text; }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function wc_customer_bought_product( $email, $user, $id ) { $GLOBALS['purchase_checks'][] = $id; return in_array( $id, $GLOBALS['purchases'], true ); }
function wc_get_orders( $args ) { $GLOBALS['last_order_query'] = $args; return $GLOBALS['orders']; }
function wc_get_is_paid_statuses() { return array( 'processing', 'completed' ); }
function wc_attribute_label( $value ) { return $value; }

require dirname( __DIR__ ) . '/src/functions.php';
require dirname( __DIR__ ) . '/src/Api/ProductsController.php';
require dirname( __DIR__ ) . '/src/Api/RatingsController.php';
require dirname( __DIR__ ) . '/src/Integration/WooCommerce.php';
require dirname( __DIR__ ) . '/src/Integration/Polylang.php';
require dirname( __DIR__ ) . '/src/Integration/WPML.php';
require dirname( __DIR__ ) . '/src/Core/Plugin.php';
new reviewbird\Integration\Polylang();
new reviewbird\Integration\WPML();

function group( array $members ): void { foreach ( $members as $id ) { $GLOBALS['groups'][$id] = $members; } }
if ( 'plain' === $mode ) {
    check( array() === $registered_filters, 'No language compatibility hooks must load without a language plugin.' );
    $products[10] = new SharedTestProduct( 10 );
    check( 10 === reviewbird_product_id( 10 ), 'Stores without translation plugins must keep their product IDs.' );
    check( 10 === get_post_meta( 10, '_reviewbird_product_id', true ), 'A product needs a saved identity before translations are installed.' );
    check( array( 10 ) === reviewbird_product_translation_ids( 10 ), 'A single product must have only its own purchase ID.' );
    $single = ( new reviewbird\Api\ProductsController() )->get_product( new WP_REST_Request( array( 'id' => 10 ) ) );
    check( array() === $single->data['legacy_external_ids'] && '1' === $single->headers['X-Reviewbird-Product-Identities'], 'An untranslated product must expose an empty redirect list and the identity contract.' );
    $products[11] = new SharedTestProduct( 11, 'product_variation', 10 );
    $purchases = array( 11 );
    $buyer = ( new reviewbird\Api\RatingsController() )->check_verified_purchase( new WP_REST_Request( array( 'product_id' => 11, 'customer_email' => 'buyer@example.com' ) ) );
    check( true === $buyer->data['verified_purchase'], 'A variation must verify its native purchase without a language plugin.' );
    add_filter( 'reviewbird_product_translation_ids', static function ( $ids, $id ) { return array_merge( $ids, array( $id, (string) $id, 0, -1, 'invalid', 11 ) ); }, 20, 2 );
    check( array( 10 ) === reviewbird_product_translation_ids( 10 ), 'Custom translation filters must retain ID validation and remove duplicates.' );
    echo $checks . ' shared-product checks passed (plain).' . PHP_EOL;
    exit;
}
$products[10] = new SharedTestProduct( 10, 'product', 0, array( 11 ) );
$products[20] = new SharedTestProduct( 20, 'product', 0, array( 21 ) );
$products[11] = new SharedTestProduct( 11, 'product_variation', 10 );
$products[21] = new SharedTestProduct( 21, 'product_variation', 20 );
group( array( 'en' => 10, 'fi' => 20 ) );
group( array( 'en' => 11, 'fi' => 21 ) );
$languages[20] = 'fi_FI';
check( 10 === reviewbird_product_id( 20 ), 'Translations must use one product identity.' );
check( 11 === reviewbird_product_id( 21 ), 'Variations must use their own shared identity.' );
$default_language = 'fi';
check( 10 === reviewbird_product_id( 20 ), 'A default-language change must preserve identity.' );
$products[30] = new SharedTestProduct( 30 );
group( array( 'en' => 10, 'fi' => 20, 'de' => 30 ) );
check( 10 === reviewbird_product_id( 30 ), 'A new translation must inherit the saved identity.' );
$products[40] = new SharedTestProduct( 40 );
check( 40 === reviewbird_product_id( 40 ), 'A singleton needs a stable identity.' );
$products[50] = new SharedTestProduct( 50 );
group( array( 'en' => 40, 'fi' => 50 ) );
check( 40 === reviewbird_product_id( 50 ), 'A singleton must retain identity after translation.' );
$integration = new reviewbird\Integration\WooCommerce();
$order = new class { public function get_meta( $key ) { return 'fi_FI'; } public function get_item( $id ) { return 101 === $id ? new WC_Order_Item_Product() : false; } };
$original = array( 'line_items' => array( array( 'id' => 101, 'product_id' => 20, 'variation_id' => 21, 'name' => 'Finnish name' ) ) );
$untouched = $integration->add_locale_to_order_response( new WP_REST_Response( $original ), $order, new WP_REST_Request() );
check( 20 === $untouched->data['line_items'][0]['product_id'], 'Other REST clients must keep actual IDs.' );
$request = new WP_REST_Request( array(), array( 'X-Reviewbird-Request' => '1' ) );
// WCML can rewrite response fields before our filter. Source order data must win.
$rewritten = $original;
$rewritten['line_items'][0] = array( 'id' => 101, 'product_id' => 30, 'variation_id' => 0, 'name' => 'Wrong request language' );
$normalized = $integration->add_locale_to_order_response( new WP_REST_Response( $rewritten ), $order, $request );
$item = $normalized->data['line_items'][0];
check( 10 === $item['product_id'] && 11 === $item['variation_id'], 'Order references must use shared IDs.' );
check( 'Finnish name' === $item['name'] && 'https://store.test/product/21' === $item['product_url'], 'Order display data must retain the purchase language.' );
$comment = (object) array( 'comment_ID' => 1, 'comment_post_ID' => 20 );
$review = $integration->add_cusrev_review_data_to_rest_response( new WP_REST_Response( array( 'product_id' => 20 ) ), $comment, $request );
check( 10 === $review->data['product_id'], 'Review imports must use shared IDs.' );
check( 'fi_FI' === $review->data['locale'], 'Known source review language must be retained.' );
$comment_meta[1]['_reviewbird_locale'] = 'sv_SE';
$saved_review = $integration->add_cusrev_review_data_to_rest_response( new WP_REST_Response( array() ), $comment, $request );
check( 'sv_SE' === $saved_review->data['locale'], 'Saved review locale must take priority over product language.' );
unset( $comment_meta[1] );
$unknown_review = $integration->add_cusrev_review_data_to_rest_response( new WP_REST_Response( array() ), (object) array( 'comment_ID' => 2, 'comment_post_ID' => 30 ), $request );
check( ! isset( $unknown_review->data['locale'] ), 'Missing source language must not fall back to a site or request language.' );
$plain_review = $integration->add_cusrev_review_data_to_rest_response( new WP_REST_Response( array( 'product_id' => 20 ) ), $comment, new WP_REST_Request() );
check( 20 === $plain_review->data['product_id'] && ! isset( $plain_review->data['locale'] ), 'Other review clients must keep actual IDs.' );
$query = $integration->query_all_languages( array(), $request );
check( array( 'lang' => '' ) === $query, 'Reviewbird imports must include every language.' );
$review_args = array( 'number' => 1, 'meta_query' => array( 'relation' => 'OR', array( 'key' => 'source', 'value' => 'customer' ), array( 'key' => 'rating', 'value' => 5 ) ) );
$native_review_args = apply_filters( 'woocommerce_rest_product_review_query', $review_args, new WP_REST_Request() );
check( $review_args === $native_review_args, 'Other REST clients must retain their comment query filters.' );
$import_args = apply_filters( 'woocommerce_rest_product_review_query', $review_args, $request );
if ( $wpml_mode ) {
    check( 1 === $import_args['number'] && 'AND' === $import_args['meta_query']['relation'] && $review_args['meta_query'] === $import_args['meta_query'][0], 'The duplicate guard must preserve existing query filters and pagination.' );
    $copy_guard = $import_args['meta_query'][1];
    $native_reviews = array( array( 'id' => 1, 'meta' => array() ), array( 'id' => 2, 'meta' => array( '_icl_duplicate_of' => 1 ) ) );
    $original_reviews = array_values( array_filter( $native_reviews, static function ( $review ) use ( $copy_guard ) { return 'NOT EXISTS' === $copy_guard['compare'] && ! array_key_exists( $copy_guard['key'], $review['meta'] ); } ) );
    check( array( 1 ) === array_column( $original_reviews, 'id' ), 'An original review and its WPML copy must count as one imported review.' );
} else {
    check( $review_args['meta_query'] === $import_args['meta_query'], 'Polylang queries must not contain the WPML duplicate guard.' );
}

// Remove the original post as WordPress does, while retaining its translations.
unset( $products[10], $meta[10] );
group( array( 'fi' => 20, 'de' => 30 ) );
check( 10 === reviewbird_product_id( 20 ), 'Removing the original must preserve its external identity.' );
check( 20 === reviewbird_find_product( 10 )->get_id() && 20 === reviewbird_product_meta_id( 30 ), 'A deleted identity must resolve to a surviving product.' );
$controller = new reviewbird\Api\ProductsController();
$single = $controller->get_product( new WP_REST_Request( array( 'id' => 10 ) ) );
check( 10 === $single->data['id'] && '10' === $single->headers['X-Reviewbird-Product-ID'], 'Historical product requests must keep their shared ID.' );
check( array( 20, 30 ) === $single->data['legacy_external_ids'] && '1' === $single->headers['X-Reviewbird-Product-Identities'], 'Single products must expose all native redirects and the identity contract.' );
$variation = $controller->get_product( new WP_REST_Request( array( 'id' => 21 ) ) );
check( 10 === $variation->data['id'] && '11' === $variation->headers['X-Reviewbird-Product-ID'], 'A variation alias must expose its shared ID with the parent payload.' );
check( 11 === $variation->data['variations'][0]['id'], 'Parent payloads must use shared variation IDs.' );
check( array( 21 ) === $variation->data['variations'][0]['legacy_external_ids'], 'Variations must expose their native redirects without the shared ID.' );
$native_request = new WP_REST_Request();
apply_filters( 'rest_request_before_callbacks', null, array(), $native_request );
check( 'fi' === $wpml_language, 'Other REST clients must keep the current WPML language.' );
apply_filters( 'rest_request_after_callbacks', null, array(), $native_request );
apply_filters( 'rest_request_before_callbacks', null, array(), $request );
check( ! $wpml_mode || 'all' === $wpml_language, 'Marked REST imports must switch WPML to all languages.' );
$nested_request = new WP_REST_Request( array(), array( 'X-Reviewbird-Request' => '1' ) );
apply_filters( 'rest_request_before_callbacks', null, array(), $nested_request );
apply_filters( 'rest_request_after_callbacks', null, array(), $nested_request );
check( ! $wpml_mode || 'all' === $wpml_language, 'A nested request must restore the outer request language.' );
apply_filters( 'rest_request_after_callbacks', null, array(), $request );
check( 'fi' === $wpml_language, 'The WPML language must be restored after an import.' );
$purchase_request = new WP_REST_Request( array(), array(), '/reviewbird/v1/verified-purchase/check' );
apply_filters( 'rest_request_before_callbacks', null, array(), $purchase_request );
check( ! $wpml_mode || 'all' === $wpml_language, 'Purchase checks must include all languages without a request marker.' );
apply_filters( 'rest_request_after_callbacks', null, array(), $purchase_request );
check( 'fi' === $wpml_language && array() === $wpml_language_stack, 'Purchase checks must close their WPML language scope.' );
$catalog_request = new WP_REST_Request( array(), array(), '/reviewbird/v1/products' );
apply_filters( 'rest_request_before_callbacks', null, array(), $catalog_request );
check( ! $wpml_mode || 'all' === $wpml_language, 'Reviewbird product exports must include all WPML languages.' );
$one_row = $controller->get_products( new WP_REST_Request( array( 'status' => 'any', 'per_page' => 1, 'page' => 1 ) ) );
check( array( 20, 30 ) === $one_row->data[0]['legacy_external_ids'] && '1' === $one_row->headers['X-Reviewbird-Product-Identities'], 'One native catalog row must expose every peer redirect and the identity contract.' );
$first = $controller->get_products( new WP_REST_Request( array( 'status' => 'any', 'per_page' => 2, 'page' => 1 ) ) );
$second = $controller->get_products( new WP_REST_Request( array( 'status' => 'any', 'per_page' => 2, 'page' => 2 ) ) );
check( 2 === $first->headers['X-WP-TotalPages'] && 1 === count( $first->data ), 'Shortened shared pages must retain native traversal page counts.' );
check( 40 === $second->data[0]['id'], 'A shared first page must not hide later products.' );
apply_filters( 'rest_request_after_callbacks', null, array(), $catalog_request );
check( 'fi' === $wpml_language && array() === $wpml_language_stack, 'Product exports must restore and close the prior WPML language scope.' );
check( 100 === $registered_filters['woocommerce_rest_prepare_shop_order_object'][2], 'Order normalization must run after WCML response translation.' );
$products[20]->status = 'draft';
$published = $controller->get_product( new WP_REST_Request( array( 'id' => 10 ) ) );
check( 10 === $published->data['id'] && 'publish' === $published->data['status'] && 'Name 30' === $published->data['name'], 'A published translation must keep a shared product active when its anchor is draft.' );
$products[20]->status = 'publish';
check( '' === $last_product_query['lang'] && '1' === $single->headers['X-Reviewbird-Shared-Products'], 'Exports must identify the shared product contract.' );
$purchases = array( 20 );
$buyer = ( new reviewbird\Api\RatingsController() )->check_verified_purchase( new WP_REST_Request( array( 'product_id' => 10, 'customer_email' => 'buyer@example.com' ) ) );
check( true === $buyer->data['verified_purchase'] && 10 === $buyer->data['product_id'], 'Historical product IDs must verify actual translated purchases.' );
$purchases = array( 10 );
$buyer = ( new reviewbird\Api\RatingsController() )->check_verified_purchase( new WP_REST_Request( array( 'product_id' => 10, 'customer_email' => 'buyer@example.com' ) ) );
check( true === $buyer->data['verified_purchase'], 'Purchases of a deleted native anchor must verify its surviving translations.' );
$products[10] = new SharedTestProduct( 10 );
$buyer = ( new reviewbird\Api\RatingsController() )->check_verified_purchase( new WP_REST_Request( array( 'product_id' => 20, 'customer_email' => 'buyer@example.com' ) ) );
check( false === $buyer->data['verified_purchase'], 'An unrelated live object at the saved identity must not verify a purchase.' );
unset( $products[10] );
$products[22] = new SharedTestProduct( 22, 'product_variation', 20 );
$products[22]->attributes = array( 'attribute_size' => 'small' );
$orders = array( new class {
    public function get_items() { return array( new WC_Order_Item_Product( 20, 22 ), new WC_Order_Item_Product( 20, 21 ) ); }
} );
$purchases = array( 21 );
$purchase_checks = array();
$variation_buyer = ( new reviewbird\Api\RatingsController() )->check_verified_purchase( new WP_REST_Request( array( 'product_id' => 11, 'customer_email' => 'buyer@example.com' ) ) );
check( $variation_buyer instanceof WP_REST_Response && true === $variation_buyer->data['verified_purchase'] && array( 11, 21 ) === $purchase_checks, 'A variation review must check the actual native IDs across its translations.' );
check( array( array( 'name' => 'Size', 'value' => 'Large' ) ) === $variation_buyer->data['purchased_attributes'], 'Variation attributes must come from the exact purchased variation, not another variant of the same parent.' );
$purchases = array( 22 );
$other_variation = ( new reviewbird\Api\RatingsController() )->check_verified_purchase( new WP_REST_Request( array( 'product_id' => 11, 'customer_email' => 'buyer@example.com' ) ) );
check( false === $other_variation->data['verified_purchase'], 'Buying another variant of the same parent must not verify the reviewed variation.' );
$variation_rating = ( new reviewbird\Api\RatingsController() )->update_ratings( new WP_REST_Request( array( 'product_id' => 11, 'avg_stars' => 5, 'review_count' => 1 ) ) );
check( $variation_rating instanceof WP_Error, 'Rating updates must continue to reject variation IDs.' );
unset( $products[11], $meta[11] );
group( array( 'fi' => 21 ) );
$purchases = array( 11 );
$deleted_variation_buyer = ( new reviewbird\Api\RatingsController() )->check_verified_purchase( new WP_REST_Request( array( 'product_id' => 11, 'customer_email' => 'buyer@example.com' ) ) );
check( true === $deleted_variation_buyer->data['verified_purchase'], 'A deleted shared variation must verify a purchase of its original native ID.' );
$orders = array();
$products[60] = new SharedTestProduct( 60 );
$products[60]->status = 'private';
$meta[60]['_reviewbird_product_id'] = 60;
$products[70] = new SharedTestProduct( 70 );
group( array( 'en' => 60, 'fi' => 70 ) );
check( 60 === reviewbird_product_id( 70 ) && 60 === get_post_meta( 70, '_reviewbird_product_id', true ), 'A published peer must inherit a saved identity from a private anchor for public visitors.' );
add_filter( 'reviewbird_product_translation_ids', static function ( $ids, $id ) { return array_merge( $ids, array( $id, (string) $id, 0, -1, 'invalid', 21 ) ); }, 20, 2 );
$filtered_ids = reviewbird_product_translation_ids( 70 );
sort( $filtered_ids );
check( array( 60, 70 ) === $filtered_ids, 'Custom translation filters must retain valid peers and remove duplicates and other post types.' );
$products[80] = new SharedTestProduct( 80 );
$products[90] = new SharedTestProduct( 90 );
group( array( 'en' => 80, 'fi' => 90 ) );
add_filter( 'reviewbird_original_product_id', static function () { return 99999; }, 20, 2 );
check( 80 === reviewbird_product_id( 90 ), 'A custom original ID outside the translation group must use the valid fallback.' );
$plugin = ( new ReflectionClass( reviewbird\Core\Plugin::class ) )->newInstanceWithoutConstructor();
check( in_array( '_reviewbird_product_id', $plugin->exclude_reviewbird_meta_from_product_duplicates( array() ), true ), 'Independent product copies must not retain the saved identity.' );
echo $checks . ' shared-product checks passed (' . $mode . ').' . PHP_EOL;
