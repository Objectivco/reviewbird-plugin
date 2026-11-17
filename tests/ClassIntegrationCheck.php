<?php
/**
 * Run: php tests/ClassIntegrationCheck.php /path/to/disposable/wordpress
 *
 * Load WooCommerce and Reviewbird on a site with a reviewbird_scheduler_test_ database.
 * Disable WP-Cron, the AS async runner, and external HTTP, as for SchedulerIntegrationCheck.php.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This check uses a disposable WordPress and WooCommerce database.

use reviewbird\Api\CouponController;
use reviewbird\Api\ProductsController;
use reviewbird\Api\RatingsController;
use reviewbird\Core\Plugin;
use reviewbird\Integration\SchemaMarkup;
use reviewbird\Integration\SchemaScheduler;
use reviewbird\Integration\StarRatingDisplay;
use reviewbird\Integration\WooCommerce;

$site = $argv[1] ?? '';
$config = is_file( $site . '/wp-config.php' ) ? file_get_contents( $site . '/wp-config.php' ) : '';
if ( ! preg_match( "/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]reviewbird_scheduler_test_[a-z0-9_]+['\"]\s*\)/", $config ) ) {
	fwrite( STDERR, "Use a disposable site with a reviewbird_scheduler_test_ database.\n" );
	exit( 2 );
}
require $site . '/wp-load.php';
if ( ! preg_match( '/^reviewbird_scheduler_test_[a-z0-9_]+$/', DB_NAME ) ) { exit( 2 ); }

$checks = 0;
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['checks'];
}
function request( array $params ) {
	$request = new WP_REST_Request();
	foreach ( $params as $key => $value ) { $request->set_param( $key, $value ); }
	return $request;
}
function check_error( $result, $code, $status ) {
	check( is_wp_error( $result ) && $code === $result->get_error_code() && $status === $result->get_error_data()['status'], 'Wrong REST error: ' . $code );
}

update_option( 'reviewbird_store_id', 7 );
update_option( 'reviewbird_store_status', array( 'status' => 'healthy', 'has_active_subscription' => true ) );
update_option( 'reviewbird_enable_widget', 'yes' );
update_option( 'reviewbird_enable_schema', 'yes' );
$plugin = new Plugin();

// Use WordPress's real script queue, including scripts queued or removed by other code.
foreach ( array( 'reviewbird-widget', 'reviewbird-carousel' ) as $handle ) {
	$enqueue = 'reviewbird-widget' === $handle ? 'reviewbird_enqueue_widget_script' : function () use ( $plugin ) { return $plugin->carousel_shortcode( array( 'id' => 'showcase' ) ); };
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	$enqueue();
	$data = wp_scripts()->get_data( $handle, 'data' );
	check( wp_script_is( $handle ) && is_string( $data ), 'Script or configuration missing: ' . $handle );
	$enqueue();
	check( $data === wp_scripts()->get_data( $handle, 'data' ), 'Repeated calls duplicated configuration: ' . $handle );
	wp_dequeue_script( $handle );
	$enqueue();
	check( wp_script_is( $handle ), 'A removed script was not queued again: ' . $handle );
	$GLOBALS['wp_scripts'] = new WP_Scripts();
	wp_enqueue_script( $handle, 'https://example.test/script.js' );
	$enqueue();
	check( false === wp_scripts()->get_data( $handle, 'data' ), 'Changed a script already queued by other code: ' . $handle );
	wp_dequeue_script( $handle );
	wp_scripts()->done[] = $handle;
	$enqueue();
	check( ! wp_script_is( $handle ) && false === wp_scripts()->get_data( $handle, 'data' ), 'Queued a script after it was printed: ' . $handle );
}

// Native coupon lookup must honor WooCommerce filters and retain the template properties.
$template = new WC_Coupon();
$template->set_code( 'template-' . wp_generate_password( 10, false ) );
$template->set_discount_type( 'percent' );
$template->set_amount( 17 );
$template->set_usage_limit( 9 );
$template->set_usage_limit_per_user( 2 );
$template->set_individual_use( true );
$template->set_minimum_amount( 20 );
$template->save();
$lookup = function ( $id, $code ) use ( $template ) { return 'test-template-alias' === $code ? $template->get_id() : $id; };
add_filter( 'woocommerce_get_coupon_id_from_code', $lookup, 10, 2 );
$coupons = new CouponController();
$new_code = 'REVIEW-' . strtoupper( wp_generate_password( 10, false ) );
$result = $coupons->create_coupon( request( array( 'code' => $new_code, 'template_code' => 'test-template-alias', 'expiry_date' => '2030-01-01' ) ) );
check( $result instanceof WP_REST_Response && 200 === $result->get_status(), 'Template clone failed.' );
$clone = new WC_Coupon( $result->get_data()['coupon_id'] );
foreach ( array( 'discount_type', 'amount', 'individual_use', 'minimum_amount' ) as $property ) {
	$getter = 'get_' . $property;
	check( $template->$getter() === $clone->$getter(), 'Template property changed: ' . $property );
}
check( 1 === $clone->get_usage_limit() && 1 === $clone->get_usage_limit_per_user(), 'Review coupons must be single use.' );
check( wc_format_coupon_code( $new_code ) === $clone->get_code(), 'Coupon code did not use native normalization.' );
check( '2030-01-01' === $clone->get_date_expires()->date( 'Y-m-d' ), 'Coupon expiry changed.' );
check( 'test-template-alias' === $clone->get_meta( '_reviewbird_template' ) && (bool) $clone->get_meta( '_reviewbird_generated' ), 'Coupon metadata changed.' );
remove_filter( 'woocommerce_get_coupon_id_from_code', $lookup, 10 );
check_error( $coupons->create_coupon( request( array() ) ), 'missing_code', 400 );
check_error( $coupons->create_coupon( request( array( 'code' => 'new', 'amount' => 0, 'discount_type' => 'percent' ) ) ), 'missing_parameters', 400 );
check_error( $coupons->create_coupon( request( array( 'code' => 'new', 'template_code' => 'no-such-template' ) ) ), 'template_not_found', 404 );

// Use WordPress's route validation before WooCommerce coupon setters.
$previous_user = get_current_user_id();
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
check( ! empty( $admins ), 'The disposable site needs an administrator.' );
wp_set_current_user( $admins[0]->ID );
foreach ( array(
	array( 'amount' => 'not-money' ), array( 'amount' => array( 10 ) ), array( 'amount' => '12,50' ),
	array( 'amount' => -1 ), array( 'amount' => 101 ), array( 'discount_type' => 'invalid' ),
	array( 'expiry_date' => array( '2030-01-01' ) ), array( 'expiry_date' => '2030-02-30' ),
) as $invalid ) {
	$code = 'invalid-' . wp_generate_password( 10, false );
	$request = new WP_REST_Request( 'POST', '/reviewbird/v1/coupons/create' );
	$request->set_body_params( array_merge( array( 'code' => $code, 'discount_type' => 'percent', 'amount' => 10 ), $invalid ) );
	check( 400 === rest_do_request( $request )->get_status() && ! wc_get_coupon_id_by_code( $code ), 'Invalid coupon input created a coupon.' );
}
foreach ( array( array( 'per_page' => 0 ), array( 'per_page' => 101 ), array( 'page' => 0 ) ) as $invalid ) {
	$request = new WP_REST_Request( 'GET', '/reviewbird/v1/products' );
	$request->set_query_params( $invalid );
	check( 400 === rest_do_request( $request )->get_status(), 'Invalid catalog pagination was accepted.' );
}
wp_set_current_user( $previous_user );

// Product responses retain gallery keys, embedded variations, and parent resolution.
$images = array();
for ( $i = 0; $i < 3; ++$i ) {
	$images[] = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Test image', 'guid' => 'https://example.test/image.jpg' ) );
}
$gtin_prefix = (string) wp_rand( 100000000000, 999999999999 );
$product_gtin = $gtin_prefix . '0';
$variation_gtin = $gtin_prefix . '1';
$product = new WC_Product_Variable();
$product->set_name( 'Test variable product' );
$product->set_status( 'publish' );
$product->set_gallery_image_ids( $images );
$size = new WC_Product_Attribute();
$size->set_name( 'Size' );
$size->set_options( array( 'Small', 'Large' ) );
$size->set_variation( true );
$product->set_attributes( array( $size ) );
if ( method_exists( $product, 'set_global_unique_id' ) ) { $product->set_global_unique_id( $product_gtin ); }
$product->save();
$variation = new WC_Product_Variation();
$variation->set_parent_id( $product->get_id() );
$variation->set_regular_price( 12 );
if ( method_exists( $variation, 'set_global_unique_id' ) ) { $variation->set_global_unique_id( $variation_gtin ); }
$variation->save();
$attachment_url = function ( $url, $id ) use ( $images ) { return $images[1] === $id ? false : 'https://example.test/' . $id . '.jpg'; };
add_filter( 'wp_get_attachment_url', $attachment_url, 10, 2 );
$products = new ProductsController();
$result = $products->get_product( request( array( 'id' => $variation->get_id() ) ) );
$data = $result->get_data();
check( $product->get_id() === $data['id'] && $variation->get_id() === $data['variations'][0]['id'], 'Variation did not resolve to its parent.' );
check( ( version_compare( WC_VERSION, '9.1', '>=' ) ? $product_gtin : '' ) === $data['global_unique_id'], 'Product GTIN failed on this WooCommerce version.' );
check( ( version_compare( WC_VERSION, '9.1', '>=' ) ? $variation_gtin : '' ) === $data['variations'][0]['global_unique_id'], 'Variation GTIN failed on this WooCommerce version.' );
check( array( 0, 1 ) === array_keys( $data['images'] ), 'Gallery images must remain a JSON array when an image URL is missing.' );
check( array( 'id', 'legacy_external_ids', 'name', 'slug', 'permalink', 'type', 'status', 'sku', 'global_unique_id', 'brand', 'price', 'image', 'images', 'stock_status', 'in_stock', 'tags', 'categories', 'variations' ) === array_keys( $data ), 'Product response fields changed.' );
check( REVIEWBIRD_VERSION === $result->get_headers()['X-Reviewbird-Version'], 'Version header changed.' );
remove_filter( 'wp_get_attachment_url', $attachment_url, 10 );
check_error( $products->get_product( request( array( 'id' => 9999999 ) ) ), 'product_not_found', 404 );

$ratings = new RatingsController();
check_error( $ratings->update_ratings( request( array() ) ), 'missing_product_id', 400 );
check_error( $ratings->update_ratings( request( array( 'product_id' => 'invalid' ) ) ), 'invalid_product_id', 400 );
check_error( $ratings->update_ratings( request( array( 'product_id' => 9999999 ) ) ), 'product_not_found', 404 );
foreach ( array( 'avg_stars' => 6, 'review_count' => -1, 'rating_counts' => 'invalid' ) as $field => $value ) {
	$params = array( 'product_id' => $product->get_id(), 'avg_stars' => 4.5, 'review_count' => 2 );
	$params[ $field ] = $value;
	check( is_wp_error( $ratings->update_ratings( request( $params ) ) ), 'Invalid rating data was accepted: ' . $field );
}
$overrides = new \reviewbird\Integration\RatingOverride();
foreach ( array( array( 0, 1, array_fill( 1, 5, 0 ) ), array( 0.83, 6, array( 5 => 1 ) ) ) as list( $average, $total, $buckets ) ) {
	$result = $ratings->update_ratings( request( array( 'product_id' => $product->get_id(), 'avg_stars' => $average, 'review_count' => $total, 'rating_counts' => $buckets ) ) );
	check( $result instanceof WP_REST_Response && 200 === $result->get_status(), 'Imported unrated reviews must be accepted.' );
	check( $total === $product->get_review_count() && array_sum( $buckets ) === $product->get_rating_count(), 'Native review and rating totals must remain distinct.' );
}
$result = $ratings->update_ratings( request( array( 'product_id' => $product->get_id(), 'avg_stars' => '4.5', 'review_count' => '2', 'rating_counts' => array( 4 => 1, 5 => 1 ) ) ) );
check( array( 'success' => true, 'product_id' => $product->get_id(), 'avg_stars' => 4.5, 'review_count' => 2 ) === $result->get_data(), 'Rating response changed.' );
check( 2 === $product->get_rating_count(), 'Native rating count must use the saved distribution.' );

// Read the selected attributes and guest country from the saved order.
$purchase_email = 'reviewbird-' . wp_generate_password( 10, false ) . '@example.test';
$purchase_order = wc_create_order();
$purchase_order->set_billing_email( $purchase_email );
$purchase_order->set_billing_country( 'NG' );
$purchase_order->set_status( 'completed' );
$purchase_item = new WC_Order_Item_Product();
$purchase_item->set_product( $variation );
$purchase_item->set_variation( array( 'attribute_size' => 'Large' ) );
$purchase_item->add_meta_data( 'Gift message', 'Keep this private' );
$purchase_item->add_meta_data( 'Engraving', 'Private text' );
$purchase_order->add_item( $purchase_item );
$purchase_order->save();
$purchase = $ratings->check_verified_purchase( request( array( 'product_id' => $product->get_id(), 'customer_email' => $purchase_email ) ) )->get_data();
check( true === $purchase['verified_purchase'] && 'Nigeria' === $purchase['location'], 'Guest purchase country was not read from the order.' );
check( array( array( 'name' => 'Size', 'value' => 'Large' ) ) === $purchase['purchased_attributes'], 'Selected order attributes were lost.' );

$schema = new SchemaMarkup();
$original = array( 'review' => array( 'native review' ) );
foreach ( array( '', 'bad metadata', array() ) as $reviews ) {
	update_post_meta( $product->get_id(), SchemaScheduler::META_KEY, $reviews );
	check( $original['review'] === $schema->filter_woocommerce_structured_data( $original, $product )['review'], 'Invalid schema metadata replaced native reviews.' );
}
update_post_meta( $product->get_id(), SchemaScheduler::META_KEY, array( 'cached review' ) );
$markup = $schema->filter_woocommerce_structured_data( $original, $product );
check( array( 'cached review' ) === $markup['review'] && 4.5 === $markup['aggregateRating']['ratingValue'], 'Cached schema changed.' );
// A zero total must also hide old cached reviews before the background refresh runs.
set_transient( 'reviewbird_ssr_' . $product->get_id(), array( 'reviews' => array( 'old review' ) ), HOUR_IN_SECONDS );
$result = $ratings->update_ratings( request( array( 'product_id' => $product->get_id(), 'avg_stars' => 0, 'review_count' => 0 ) ) );
check( 200 === $result->get_status(), 'Zero ratings were rejected.' );
$overrides = new \reviewbird\Integration\RatingOverride();
check( 0.0 === $overrides->override_average_rating( 4.5, $product ), 'Zero average restored old ratings.' );
check( 0 === $overrides->override_rating_count( 2, $product ), 'Zero count restored old ratings.' );
check( array_fill( 1, 5, 0 ) === $overrides->override_rating_counts( array( 5 => 2 ), $product ), 'Zero total left a rating distribution.' );
$markup['name'] = 'Keep product fields';
check( array( 'name' => 'Keep product fields' ) === $schema->filter_woocommerce_structured_data( $markup, $product ), 'Zero ratings retained old review schema.' );
check( array() === reviewbird_get_cached_product_reviews( $product->get_id() ), 'Zero ratings retained old review HTML.' );
update_option( 'reviewbird_enable_schema', 'no' );
check( $original === $schema->filter_woocommerce_structured_data( $original, $product ), 'Disabled schema changed native markup.' );

$integration = new WooCommerce();
$order = wc_create_order();
foreach ( array( array( $order ), array( $order->get_id(), $order ), array( $order->get_id() ) ) as $args ) {
	$integration->save_order_locale( ...$args );
	check( get_locale() === wc_get_order( $order->get_id() )->get_meta( '_reviewbird_locale' ), 'Order hook lost the locale.' );
}
$integration->register_system_status_field();
$field = $GLOBALS['wp_rest_additional_fields']['system_status']['reviewbird_widget_enabled'];
foreach ( array( 'yes' => true, 'no' => false ) as $value => $expected ) {
	update_option( 'reviewbird_enable_widget', $value );
	check( $expected === call_user_func( $field['get_callback'], array(), 'reviewbird_widget_enabled', new WP_REST_Request() ), 'System status callback changed.' );
}

// Keep signed links available and remove obsolete prompt writes from WordPress REST.
update_option( 'reviewbird_store_status', array(
	'store_id' => 7, 'status' => 'healthy', 'has_active_subscription' => true,
	'google_customer_reviews' => array( 'enabled' => true, 'expires_at' => time() + 600, 'merchant_id' => '12345', 'estimated_delivery_days' => 3 ),
) );
update_option( 'reviewbird_enable_gcr_prompt', 'no' );
update_option( 'reviewbird_use_gcr_standard_modal', 'yes' );
$order->set_billing_email( 'buyer@example.test' );
$order->set_billing_country( 'US' );
$order->set_status( 'processing' );
$order->save();
$url = reviewbird\Integration\GoogleCustomerReviews::opt_in_url( $order );
check( is_string( $url ), 'A valid order did not get an opt-in link.' );
parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $link );
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ( array( 'yes', 'no' ) as $choice ) {
	$request = new WP_REST_Request( 'POST', '/reviewbird/v1/google-customer-reviews/' . $order->get_id() . '/prompt-' . $choice );
	$request->set_param( 'token', $link['token'] );
	check( 404 === rest_do_request( $request )->get_status(), 'An obsolete prompt route still accepts requests.' );
}
$gcr = new reviewbird\Integration\GoogleCustomerReviews();
$response = $gcr->add_order_response( new WP_REST_Response( array() ), wc_get_order( $order->get_id() ) );
check( $url === $response->get_data()['reviewbird_google_customer_reviews']['opt_in_url'], 'Order API lost the email link.' );
check( null === $response->get_data()['reviewbird_google_customer_reviews']['prompt_yes_at'], 'A stale prompt saved Yes.' );
check( 0 === $response->get_data()['reviewbird_google_customer_reviews']['no_click_count'], 'A stale prompt saved No.' );
$_SERVER['REQUEST_METHOD'] = $method;

update_option( 'reviewbird_enable_widget', 'yes' );
delete_option( 'reviewbird_star_color' );
$saved_product = $product;
foreach ( array( 'product-slug', new stdClass() ) as $product ) {
	check( '' === $plugin->widget_shortcode( array() ), 'A product slug must not reach the widget renderer.' );
	check( '' === reviewbird_render_widget(), 'The shared widget renderer must reject a non-product global.' );
	ob_start();
	reviewbird_display_single_rating();
	check( '' === ob_get_clean(), 'The classic rating callback must reject a non-product global.' );
}
$product = $saved_product;
$stars = new StarRatingDisplay();
foreach ( array( '' => '#ffa500', '#123456' => '#123456' ) as $value => $expected ) {
	set_transient( 'reviewbird_star_color', $value );
	check( false !== strpos( $stars->filter_rating_html( '', 4.5, 2 ), 'color: ' . $expected ), 'Star color fallback changed.' );
}

// Render real blocks on a normal page, then use the native block-template fallback.
$page_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Reviewbird block check' ) );
$previous_query = $GLOBALS['wp_query'];
$previous_post = $GLOBALS['post'] ?? null;
$GLOBALS['wp_query'] = new WP_Query( array( 'page_id' => $page_id ) );
$GLOBALS['post'] = get_post( $page_id );
check( ! is_product(), 'The embedded block check must run on a normal page.' );
$GLOBALS['wp_styles'] = new WP_Styles();
$plugin->enqueue_star_styles();
check( wp_style_is( 'reviewbird-stars' ), 'Product ratings on a normal page need the star stylesheet.' );
$widget_id = 'id="reviewbird-widget-container-' . $product->get_id() . '"';
$block = '<!-- wp:woocommerce/single-product {"productId":' . $product->get_id() . '} --><div class="wp-block-woocommerce-single-product"><!-- wp:group --><div class="wp-block-group"><!-- wp:group --><div class="wp-block-group"><!-- wp:woocommerce/product-reviews /--></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:woocommerce/single-product -->';
$suppressed = false;
$observe_reviews = function ( $content, $parsed, $parent ) use ( &$suppressed ) {
	if ( 'woocommerce/product-reviews' === $parsed['blockName'] ) {
		check( ! empty( $parent->context['singleProduct'] ), 'WooCommerce must pass Single Product context through nested Groups.' );
		$suppressed = '' === $content;
	}
	return $content;
};
add_filter( 'pre_render_block', $observe_reviews, 99, 3 );
$html = do_blocks( $block . $block );
remove_filter( 'pre_render_block', $observe_reviews, 99 );
check( 1 === substr_count( $html, $widget_id ), 'Repeated Single Product blocks must render one review widget.' );
check( false === strpos( $html, 'id="reviews"' ), 'Embedded product widgets must not claim a page-wide reviews anchor.' );
check( $suppressed, 'Embedded product blocks must suppress native reviews before their queries run.' );
$plugin->register_product_widget_hook();
$GLOBALS['wp_query'] = new WP_Query( array( 'p' => $product->get_id(), 'post_type' => 'product' ) );
$GLOBALS['post'] = get_post( $product->get_id() );
check( is_product(), 'The fallback check must use a product query.' );
$ratings->update_ratings( request( array( 'product_id' => $product->get_id(), 'avg_stars' => 4.5, 'review_count' => 2, 'rating_counts' => array( 4 => 1, 5 => 1 ) ) ) );
foreach ( array( 'product-rating', 'product-rating-counter' ) as $rating_block ) {
	$html = do_blocks( '<!-- wp:woocommerce/' . $rating_block . ' {"isDescendentOfSingleProductTemplate":true} /-->' );
	check( false !== strpos( $html, 'rb-wc-rating' ), 'The real rating block must use the replacement: ' . $rating_block );
	check( false === strpos( $html, '#reviews' ), 'The rating HTML filter must remove native review links: ' . $rating_block );
}
// Themes can still call the native template directly, outside the replaced action.
$native_rating = wc_get_template_html( 'single-product/rating.php' );
check( false !== strpos( $native_rating, 'href="#reviews"' ), 'The native template must expose the review link covered by the widget anchor.' );
$compatibility = new \Automattic\WooCommerce\Blocks\Templates\SingleProductTemplateCompatibility();
$compatibility->init();
apply_filters( 'template_include', ABSPATH . 'index.php' );
$html = do_blocks( $compatibility::add_compatibility_layer( '<!-- wp:woocommerce/product-price /-->' ) );
check( 1 === substr_count( $html, $widget_id ), 'A block template without Product Details needs the fallback widget.' );
check( 1 === substr_count( $html, 'id="reviews"' ), 'The main product widget must provide one target for native template review links.' );
ob_start();
do_action( 'woocommerce_after_single_product' );
check( false === strpos( ob_get_clean(), $widget_id ), 'The final product hook must not repeat a rendered widget.' );
// The filtered widget HTML is sanitized once at each output boundary.
$kses_passes = 0;
$count_kses = function ( $html ) use ( &$kses_passes, $widget_id ) { if ( false !== strpos( $html, $widget_id ) ) { ++$kses_passes; } return $html; };
$unsafe_widget = function ( $html ) { return $html . '<script>unsafe()</script><img src="x" onerror="unsafe()">'; };
add_filter( 'pre_kses', $count_kses );
add_filter( 'reviewbird_widget_html', $unsafe_widget );
foreach ( array( 'hook', 'shortcode', 'block' ) as $path ) {
	$instance = new Plugin();
	$kses_passes = 0;
	if ( 'hook' === $path ) { ob_start(); $instance->render_product_widget(); $html = ob_get_clean(); }
	elseif ( 'shortcode' === $path ) { $html = $instance->widget_shortcode( array() ); }
	else { $html = $instance->render_block_product_widget( 'Product', array( 'attrs' => array( 'productId' => $product->get_id() ) ), (object) array( 'context' => array() ) ); }
	check( 1 === $kses_passes && false === strpos( $html, '<script' ) && false === strpos( $html, 'onerror' ), 'Sanitize widget HTML once at the output boundary: ' . $path );
}
remove_filter( 'pre_kses', $count_kses );
remove_filter( 'reviewbird_widget_html', $unsafe_widget );
$GLOBALS['wp_query'] = $previous_query;
$GLOBALS['post'] = $previous_post;

// Use WordPress's real action priorities and WooCommerce's rating HTML filter.
remove_action( 'woocommerce_single_product_summary', 'reviewbird_display_single_rating', 6 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 20 );
$stars->replace_single_product_rating();
check( false === has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating' ), 'The native rating at priority 20 was not removed.' );
check( 6 === has_action( 'woocommerce_single_product_summary', 'reviewbird_display_single_rating' ), 'The replacement rating callback is missing.' );
update_option( 'reviewbird_enable_widget', 'no' );
$html = wc_get_rating_html( 4.5, 2 );
check( false !== strpos( $html, 'star-rating' ) && false === strpos( $html, 'rb-wc-rating' ), 'A disabled widget must retain native rating HTML.' );
check( isset( $plugin->remove_reviews_tab( array( 'reviews' => array() ) )['reviews'] ), 'A disabled widget must retain its native reviews tab.' );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 20 );
$stars->replace_single_product_rating();
check( 20 === has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating' ), 'Disabling the widget after construction must preserve the native rating callback.' );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 20 );
update_option( 'reviewbird_enable_widget', 'yes' );
check( false !== strpos( wc_get_rating_html( 4.5, 2 ), 'rb-wc-rating' ), 'The registered rating filter must resume when the widget is enabled again.' );

// Authenticate through WooCommerce with a real temporary key and resolved route.
$consumer_key = 'ck_' . wp_generate_password( 32, false );
$consumer_secret = 'cs_' . wp_generate_password( 32, false );
$wpdb->insert( $wpdb->prefix . 'woocommerce_api_keys', array( 'user_id' => $admins[0]->ID, 'description' => 'Reviewbird regression check', 'permissions' => 'read', 'consumer_key' => wc_api_hash( $consumer_key ), 'consumer_secret' => $consumer_secret, 'truncated_key' => substr( $consumer_key, -7 ) ) );
$key_id = $wpdb->insert_id;
check( $key_id > 0, 'The temporary API key was not created.' );
$previous_server = $_SERVER;
$previous_route = $GLOBALS['wp']->query_vars['rest_route'] ?? null;
if ( ! defined( 'REST_REQUEST' ) ) { define( 'REST_REQUEST', true ); }
try {
	$_SERVER['HTTPS'] = 'on';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['PHP_AUTH_USER'] = $consumer_key;
	$_SERVER['PHP_AUTH_PW'] = $consumer_secret;
	foreach ( array(
		array( '/wp-json/reviewbird/v1/products', '/reviewbird/v1/products', true ),
		array( '/?rest_route=%2Freviewbird%2Fv1%2Fproducts', '/reviewbird/v1/products', true ),
		array( '/wp-json/wp/v2/users?hint=/wp-json/reviewbird/', '/wp/v2/users', false ),
		array( '/wp-json/reviewbird/v1/products?rest_route=/wp/v2/users', '/wp/v2/users', false ),
	) as list( $uri, $route, $allowed ) ) {
		$_SERVER['REQUEST_URI'] = $uri;
		$GLOBALS['wp']->query_vars['rest_route'] = $route;
		$auth = ( new ReflectionClass( WC_REST_Authentication::class ) )->newInstanceWithoutConstructor();
		$user_id = $auth->authenticate( false );
		check( $allowed ? (int) $admins[0]->ID === (int) $user_id : false === $user_id, 'WooCommerce authentication used the wrong route: ' . $uri );
	}
} finally {
	$wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => $key_id ) );
	$_SERVER = $previous_server;
	if ( null === $previous_route ) { unset( $GLOBALS['wp']->query_vars['rest_route'] ); } else { $GLOBALS['wp']->query_vars['rest_route'] = $previous_route; }
}
wp_delete_post( $page_id, true );
$purchase_order->delete( true );
$order->delete( true );
$variation->delete( true );
$product->delete( true );
$clone->delete( true );
$template->delete( true );
foreach ( $images as $id ) { wp_delete_post( $id, true ); }
echo "Class integration: {$checks} checks passed.\n";
