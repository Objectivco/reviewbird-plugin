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
foreach ( array( 'discount_type', 'amount', 'usage_limit', 'usage_limit_per_user', 'individual_use', 'minimum_amount' ) as $property ) {
	$getter = 'get_' . $property;
	check( $template->$getter() === $clone->$getter(), 'Template property changed: ' . $property );
}
check( $new_code === get_post( $clone->get_id() )->post_title, 'Coupon code lost uppercase letters.' );
check( '2030-01-01' === $clone->get_date_expires()->date( 'Y-m-d' ), 'Coupon expiry changed.' );
check( 'test-template-alias' === $clone->get_meta( '_reviewbird_template' ) && (bool) $clone->get_meta( '_reviewbird_generated' ), 'Coupon metadata changed.' );
remove_filter( 'woocommerce_get_coupon_id_from_code', $lookup, 10 );
check_error( $coupons->create_coupon( request( array() ) ), 'missing_code', 400 );
check_error( $coupons->create_coupon( request( array( 'code' => 'new', 'amount' => 0, 'discount_type' => 'percent' ) ) ), 'missing_parameters', 400 );
check_error( $coupons->create_coupon( request( array( 'code' => 'new', 'template_code' => 'no-such-template' ) ) ), 'template_not_found', 404 );

// Product responses retain gallery keys, embedded variations, and parent resolution.
$images = array();
for ( $i = 0; $i < 3; ++$i ) {
	$images[] = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Test image', 'guid' => 'https://example.test/image.jpg' ) );
}
$product = new WC_Product_Variable();
$product->set_name( 'Test variable product' );
$product->set_status( 'publish' );
$product->set_gallery_image_ids( $images );
if ( method_exists( $product, 'set_global_unique_id' ) ) { $product->set_global_unique_id( '1234567890123' ); }
$product->save();
$variation = new WC_Product_Variation();
$variation->set_parent_id( $product->get_id() );
$variation->set_regular_price( 12 );
if ( method_exists( $variation, 'set_global_unique_id' ) ) { $variation->set_global_unique_id( '1234567890130' ); }
$variation->save();
$attachment_url = function ( $url, $id ) use ( $images ) { return $images[1] === $id ? false : 'https://example.test/' . $id . '.jpg'; };
add_filter( 'wp_get_attachment_url', $attachment_url, 10, 2 );
$products = new ProductsController();
$result = $products->get_product( request( array( 'id' => $variation->get_id() ) ) );
$data = $result->get_data();
check( $product->get_id() === $data['id'] && $variation->get_id() === $data['variations'][0]['id'], 'Variation did not resolve to its parent.' );
check( ( version_compare( WC_VERSION, '9.1', '>=' ) ? '1234567890123' : '' ) === $data['global_unique_id'], 'Product GTIN failed on this WooCommerce version.' );
check( ( version_compare( WC_VERSION, '9.1', '>=' ) ? '1234567890130' : '' ) === $data['variations'][0]['global_unique_id'], 'Variation GTIN failed on this WooCommerce version.' );
check( array( 0, 2 ) === array_keys( $data['images'] ), 'Gallery keys changed when an image URL was missing.' );
check( array( 'id', 'name', 'slug', 'permalink', 'type', 'status', 'sku', 'global_unique_id', 'brand', 'price', 'image', 'images', 'stock_status', 'in_stock', 'tags', 'categories', 'variations' ) === array_keys( $data ), 'Product response fields changed.' );
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
$result = $ratings->update_ratings( request( array( 'product_id' => $product->get_id(), 'avg_stars' => '4.5', 'review_count' => '2' ) ) );
check( array( 'success' => true, 'product_id' => $product->get_id(), 'avg_stars' => 4.5, 'review_count' => 2 ) === $result->get_data(), 'Rating response changed.' );

$schema = new SchemaMarkup();
$original = array( 'review' => array( 'native review' ) );
foreach ( array( '', 'bad metadata', array() ) as $reviews ) {
	update_post_meta( $product->get_id(), SchemaScheduler::META_KEY, $reviews );
	check( $original['review'] === $schema->filter_woocommerce_structured_data( $original, $product )['review'], 'Invalid schema metadata replaced native reviews.' );
}
update_post_meta( $product->get_id(), SchemaScheduler::META_KEY, array( 'cached review' ) );
$markup = $schema->filter_woocommerce_structured_data( $original, $product );
check( array( 'cached review' ) === $markup['review'] && 4.5 === $markup['aggregateRating']['ratingValue'], 'Cached schema changed.' );
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

$stars = new StarRatingDisplay();
foreach ( array( '' => '#ffa500', '#123456' => '#123456' ) as $value => $expected ) {
	set_transient( 'reviewbird_star_color', $value );
	check( false !== strpos( $stars->filter_rating_html( '', 4.5, 2 ), 'color: ' . $expected ), 'Star color fallback changed.' );
}
$order->delete( true );
$variation->delete( true );
$product->delete( true );
$clone->delete( true );
$template->delete( true );
foreach ( $images as $id ) { wp_delete_post( $id, true ); }
echo "Class integration: {$checks} checks passed.\n";
