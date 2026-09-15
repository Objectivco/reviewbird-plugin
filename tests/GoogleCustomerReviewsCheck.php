<?php
/**
 * Standalone GCR checks: php tests/GoogleCustomerReviewsCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Isolated WordPress and WooCommerce stubs for this runnable check.

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'REVIEWBIRD_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	define( 'REVIEWBIRD_PLUGIN_URL', 'https://store.test/wp-content/plugins/reviewbird/' );
	define( 'REVIEWBIRD_VERSION', 'test' );

	class WC_Order {
		public $id;
		public $key;
		public $status = 'processing';
		public $customer = 0;
		public $email = 'buyer@example.com';
		public $shipping = 'CA';
		public $billing = 'US';
		public $type = 'shop_order';
		public $meta = array();
		public $created;
		public $modified = 1700000000;

		public function __construct( $id ) {
			$this->id = $id;
			$this->key = 'wc_order_key_' . $id;
			$this->created = new \DateTime( '2026-03-08T04:30:00+00:00' );
		}
		public function get_id() { return $this->id; }
		public function get_order_key() { return $this->key; }
		public function get_type() { return $this->type; }
		public function get_customer_id() { return $this->customer; }
		public function get_billing_email() { return $this->email; }
		public function get_shipping_country() { return $this->shipping; }
		public function get_billing_country() { return $this->billing; }
		public function get_date_created() { return $this->created; }
		public function has_status( $status ) { return in_array( $this->status, (array) $status, true ); }
		public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
		public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
		public function set_date_modified( $timestamp ) { $this->modified = $timestamp; }
		public function read_meta_data( $force = false ) {
			if ( $force ) { $this->meta = $GLOBALS['gcr_orders'][ $this->id ]->meta; }
		}
		public function save() {
			if ( $GLOBALS['gcr_save_fail'] ) { throw new \RuntimeException( 'Save failed.' ); }
			if ( $GLOBALS['gcr_save_swallow'] ) { return $this->id; }
			$GLOBALS['gcr_orders'][ $this->id ] = clone $this;
			++$GLOBALS['gcr_saves'];
			return $this->id;
		}
	}
	class WP_REST_Request {
		private $params;
		public function __construct( $params ) { $this->params = $params; }
		public function get_param( $name ) { return $this->params[ $name ] ?? null; }
	}
	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
	}
	class WP_Error {
		public $code;
		public $message;
		public $data;
		public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}

namespace reviewbird\Integration {
	function add_action( $hook, $callback, $priority, $args ) { $GLOBALS['gcr_hooks'][ $hook ] = array( $callback, $priority, $args ); }
	function add_filter( $hook, $callback, $priority, $args ) { add_action( $hook, $callback, $priority, $args ); }
	function doing_action( $hook ) { return $hook === $GLOBALS['gcr_current_action']; }
	function register_rest_route( $namespace, $route, $args ) { $GLOBALS['gcr_route'] = array( $namespace, $route, $args ); }
	function __( $text ) { return $text; }
	function esc_html__( $text ) { return esc_html( $text ); }
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return esc_html( $text ); }
	function esc_html_e( $text ) { echo esc_html( $text ); }
	function esc_attr_e( $text ) { echo esc_attr( $text ); }
	function sanitize_text_field( $text ) { return trim( strip_tags( str_replace( array( "\r", "\n" ), ' ', $text ) ) ); }
	function sanitize_textarea_field( $text ) { return trim( strip_tags( $text ) ); }
	function wp_unslash( $text ) { return $text; }
	function wp_json_encode( $data ) { return json_encode( $data ); }
	function get_option( $name, $default = false ) { return $GLOBALS['gcr_options'][ $name ] ?? $default; }
	function reviewbird_get_store_status() { return get_option( 'reviewbird_store_status' ); }
	function is_wp_error( $value ) { return $value instanceof \WP_Error; }
	function wp_parse_url( $url, $component ) { return parse_url( $url, $component ); }
	function absint( $value ) { return abs( (int) $value ); }
	function reviewbird_api_request( $endpoint ) { ++$GLOBALS['gcr_api_calls']; return $GLOBALS['gcr_api_response']; }
	function update_option( $name, $value, $autoload = null ) {
		if ( $GLOBALS['gcr_option_write_fail'] || get_option( $name ) === $value ) { return false; }
		$GLOBALS['gcr_options'][ $name ] = $value;
		return true;
	}
	function wp_salt() { return $GLOBALS['gcr_salt']; }
	function home_url() { return 'https://store.test/'; }
	function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
	function rest_url( $path ) { return 'https://store.test/wp-json/' . $path; }
	function wc_get_order( $id ) { return isset( $GLOBALS['gcr_orders'][ $id ] ) ? clone $GLOBALS['gcr_orders'][ $id ] : false; }
	function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ); }
	function wp_timezone() { return new \DateTimeZone( 'America/New_York' ); }
	function time() { return 1800000000; }
	function is_order_received_page() { return $GLOBALS['gcr_received']; }
	function get_query_var() { return $GLOBALS['gcr_endpoint_order']; }
	function get_current_user_id() { return $GLOBALS['gcr_user']; }
	function current_user_can() { return $GLOBALS['gcr_admin']; }
	function wp_enqueue_script( $handle ) { $GLOBALS['gcr_scripts'][] = $handle; }
	function wp_enqueue_style( $handle ) { $GLOBALS['gcr_styles'][] = $handle; }
	function nocache_headers() { $GLOBALS['gcr_no_cache'] = true; }
	function status_header( $status ) { $GLOBALS['gcr_http_status'] = $status; }
	function wp_die() { throw new \RuntimeException( 'Page unavailable.' ); }
	function WC() {
		return (object) array( 'countries' => new class() {
			public function get_countries() { return array( 'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom' ); }
		} );
	}
}

namespace reviewbird\Admin {
	class GcrAdminResponse extends \RuntimeException {
		public $data;
		public function __construct( $data, $code ) { parent::__construct( '', $code ); $this->data = $data; }
	}
	function add_action( $hook, $callback ) {}
	function __( $text ) { return $text; }
	function wp_unslash( $value ) { return $value; }
	function sanitize_text_field( $value ) { return $value; }
	function wp_verify_nonce( $value ) { return 'valid-nonce' === $value; }
	function current_user_can() { return $GLOBALS['gcr_admin']; }
	function is_wp_error( $value ) { return $value instanceof \WP_Error; }
	function reviewbird_get_store_status() { return \reviewbird\Integration\reviewbird_get_store_status(); }
	function reviewbird_get_store_id() { return \reviewbird\Integration\get_option( 'reviewbird_store_id' ); }
	function reviewbird_get_api_url() { return 'https://app.reviewbird.test'; }
	function wp_send_json_error( $data, $code ) { throw new GcrAdminResponse( $data, $code ); }
	function wp_send_json_success( $data ) { throw new GcrAdminResponse( $data, 200 ); }
}

namespace {
	// Included templates have their own namespace.
	function language_attributes() { echo 'lang="en"'; }
	function bloginfo() { echo 'UTF-8'; }
	function esc_html_e( $text ) { echo htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
	function wp_head() {}
	function body_class( $value ) { echo 'class="' . $value . '"'; }
	function wp_body_open() {}
	function wp_footer() {}

	require_once dirname( __DIR__ ) . '/src/Integration/GoogleCustomerReviews.php';
	require_once dirname( __DIR__ ) . '/src/Integration/HealthScheduler.php';
	require_once dirname( __DIR__ ) . '/src/Admin/Settings.php';
	use reviewbird\Integration\GoogleCustomerReviews;

	$checks = 0;
	function check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( $message ); }
		++$GLOBALS['checks'];
	}
	function card_config( $html ) {
		preg_match( '/data-reviewbird-gcr="([^"]+)"/', $html, $match );
		return json_decode( html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' ), true );
	}

	$GLOBALS['gcr_saves'] = 0;
	$GLOBALS['gcr_save_fail'] = false;
	$GLOBALS['gcr_save_swallow'] = false;
	$GLOBALS['gcr_current_action'] = '';
	$GLOBALS['gcr_api_calls'] = 0;
	$GLOBALS['gcr_option_write_fail'] = false;
	$GLOBALS['gcr_salt'] = 'test-site-secret';
	$GLOBALS['gcr_received'] = true;
	$GLOBALS['gcr_endpoint_order'] = 7;
	$GLOBALS['gcr_user'] = 0;
	$GLOBALS['gcr_admin'] = false;
	$GLOBALS['gcr_orders'] = array( 7 => new WC_Order( 7 ), 8 => new WC_Order( 8 ) );
	$GLOBALS['gcr_options'] = array(
		'reviewbird_store_id' => 148,
		'reviewbird_store_status' => array(
			'store_id' => 148,
			'status' => 'healthy',
			'has_active_subscription' => true,
			'google_customer_reviews' => array( 'enabled' => true, 'merchant_id' => '123456789', 'estimated_delivery_days' => 2, 'expires_at' => 1800086400 ),
		),
	);
	$_GET = array( 'key' => 'wc_order_key_7' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$integration = new GoogleCustomerReviews();
	$initial_status = $GLOBALS['gcr_options']['reviewbird_store_status'];
	$url = GoogleCustomerReviews::opt_in_url( $GLOBALS['gcr_orders'][7] );
	parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
	$token = $query['token'];

	if ( '--page' === ( $argv[1] ?? null ) ) {
		$_GET = $query;
		$GLOBALS['gcr_options']['reviewbird_enable_gcr_prompt'] = 'no';
		$GLOBALS['gcr_orders'][7]->meta[ GoogleCustomerReviews::YES_META ] = '2026-09-15T12:00:00+00:00';
		register_shutdown_function( function () {
			check( 0 === $GLOBALS['gcr_saves'], 'Direct GET wrote order metadata.' );
			echo "<!-- GCR_GET_NO_WRITES -->";
		} );
		$integration->render_link_page();
	}

	check( is_array( GoogleCustomerReviews::configuration() ), 'Valid configuration rejected.' );
	check( 'enabled' === GoogleCustomerReviews::status(), 'Enabled feature status is wrong.' );
	check( GoogleCustomerReviews::is_prompt_enabled(), 'Local prompt is not enabled by default.' );
	$GLOBALS['gcr_options']['reviewbird_enable_gcr_prompt'] = 'no';
	check( ! GoogleCustomerReviews::is_prompt_enabled(), 'Local prompt switch is ignored.' );
	ob_start(); ( new GoogleCustomerReviews() )->render_prompt( 7 ); check( '' === ob_get_clean(), 'Disabled local prompt is rendered.' );
	check( $url === GoogleCustomerReviews::opt_in_url( $GLOBALS['gcr_orders'][7] ), 'Local prompt switch disables signed links.' );
	check( 'enabled' === GoogleCustomerReviews::status(), 'Local prompt switch changes account feature status.' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$disabled_request = new WP_REST_Request( array( 'id' => 7, 'token' => $token ) );
	check( $integration->authorize_prompt_request( $disabled_request ) instanceof WP_Error, 'Stale prompt can bypass local switch.' );
	check( $integration->record_prompt_yes( $disabled_request ) instanceof WP_Error && 0 === $GLOBALS['gcr_saves'], 'Disabled prompt records Yes.' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	unset( $GLOBALS['gcr_options']['reviewbird_enable_gcr_prompt'] );
	check( false === strpos( $url, 'wc_order_key' ) && false === strpos( $url, 'buyer' ), 'Link exposes order key or email.' );
	check( 64 === strlen( $token ), 'Unexpected signature.' );
	check( 0 === $GLOBALS['gcr_saves'], 'URL GET caused a write.' );
	check( ! isset( $GLOBALS['gcr_hooks']['woocommerce_thankyou'] ), 'Widget still uses the late native thank-you hook.' );
	check( 'render_prompt' === $GLOBALS['gcr_hooks']['woocommerce_before_thankyou'][0][1] && 5 === $GLOBALS['gcr_hooks']['woocommerce_before_thankyou'][1], 'Native widget is not at the start of the thank-you page.' );
	check( 'render_checkoutwc_prompt' === $GLOBALS['gcr_hooks']['cfw_thank_you_content'][0][1] && 55 === $GLOBALS['gcr_hooks']['cfw_thank_you_content'][1] && 1 === $GLOBALS['gcr_hooks']['cfw_thank_you_content'][2], 'CheckoutWC widget is not below the order status section.' );
	$integration->register_routes();
	check( 'POST' === $GLOBALS['gcr_route'][2]['methods'], 'Yes route must be POST.' );

	foreach ( array(
		array( 'google_customer_reviews', null ),
		array( 'store_id', 149 ),
		array( 'status', 'billing_required' ),
		array( 'has_active_subscription', false ),
	) as $change ) {
		$GLOBALS['gcr_options']['reviewbird_store_status'][ $change[0] ] = $change[1];
		check( null === GoogleCustomerReviews::configuration(), 'Invalid store configuration accepted.' );
		$GLOBALS['gcr_options']['reviewbird_store_status'] = $initial_status;
	}
	foreach ( array(
		array( 'enabled', false ), array( 'enabled', 'true' ),
		array( 'merchant_id', '9007199254740992' ), array( 'merchant_id', '12x' ),
		array( 'estimated_delivery_days', -1 ), array( 'estimated_delivery_days', 366 ), array( 'estimated_delivery_days', '2' ),
		array( 'expires_at', 1800000000 ), array( 'expires_at', null ),
	) as $change ) {
		$GLOBALS['gcr_options']['reviewbird_store_status']['google_customer_reviews'][ $change[0] ] = $change[1];
		check( null === GoogleCustomerReviews::configuration(), 'Invalid GCR configuration accepted.' );
		$GLOBALS['gcr_options']['reviewbird_store_status'] = $initial_status;
	}
	$_GET['reviewbird_setup'] = 'preview';
	check( null === GoogleCustomerReviews::configuration(), 'Setup preview allows GCR.' );
	unset( $_GET['reviewbird_setup'] );

	$checkoutwc = new GoogleCustomerReviews();
	$GLOBALS['gcr_current_action'] = 'cfw_thank_you_main_container_start';
	ob_start(); call_user_func( $GLOBALS['gcr_hooks']['woocommerce_before_thankyou'][0], 7 );
	check( '' === ob_get_clean(), 'CheckoutWC replay puts the widget above its page layout.' );
	$GLOBALS['gcr_current_action'] = 'cfw_thank_you_content';
	ob_start(); call_user_func( $GLOBALS['gcr_hooks']['cfw_thank_you_content'][0], $GLOBALS['gcr_orders'][7] );
	check( 'prompt' === card_config( ob_get_clean() )['mode'], 'CheckoutWC callback does not render the order widget.' );
	$GLOBALS['gcr_current_action'] = '';
	ob_start(); $checkoutwc->render_prompt( 7 );
	check( '' === ob_get_clean(), 'CheckoutWC widget rendered twice.' );

	new GoogleCustomerReviews();
	ob_start(); call_user_func( $GLOBALS['gcr_hooks']['woocommerce_before_thankyou'][0], 7 );
	check( 'prompt' === card_config( ob_get_clean() )['mode'], 'Native thank-you hook does not render the widget.' );
	ob_start(); $integration->render_prompt( 7 ); $html = ob_get_clean();
	$config = card_config( $html );
	check( 'prompt' === $config['mode'] && $config['token'] === $token, 'Wrong frontend contract.' );
	check( '2026-03-09' === $config['google']['estimated_delivery_date'], 'Calendar delivery date is wrong across DST.' );
	check( '2026-03-08T04:30:00+00:00' === $GLOBALS['gcr_orders'][7]->created->format( 'c' ), 'Order creation date changed.' );
	check( 'CA' === $config['google']['delivery_country'], 'Shipping country lost.' );
	check( false === strpos( $html, 'apis.google.com' ), 'Prompt loads Google before Yes.' );
	ob_start(); $integration->render_prompt( 7 ); check( '' === ob_get_clean(), 'Prompt rendered twice.' );

	$GLOBALS['gcr_orders'][7]->shipping = '';
	ob_start(); ( new GoogleCustomerReviews() )->render_prompt( 7 ); $digital = card_config( ob_get_clean() );
	check( 'US' === $digital['google']['delivery_country'], 'Digital order lacks billing-country fallback.' );
	$GLOBALS['gcr_orders'][7]->shipping = 'ZZ';
	check( null === GoogleCustomerReviews::opt_in_url( $GLOBALS['gcr_orders'][7] ), 'Invalid country accepted.' );
	$GLOBALS['gcr_orders'][7]->shipping = 'CA';

	foreach ( array( 'failed', 'cancelled', 'refunded', 'draft', 'auto-draft', 'checkout-draft', 'trash' ) as $status ) {
		$GLOBALS['gcr_orders'][7]->status = $status;
		check( null === GoogleCustomerReviews::opt_in_url( $GLOBALS['gcr_orders'][7] ), 'Invalid order status accepted.' );
	}
	foreach ( array( 'pending', 'on-hold', 'processing', 'completed' ) as $status ) {
		$GLOBALS['gcr_orders'][7]->status = $status;
		check( null !== GoogleCustomerReviews::opt_in_url( $GLOBALS['gcr_orders'][7] ), 'Placed order incorrectly rejected.' );
	}

	$_GET['key'] = 'wrong';
	ob_start(); ( new GoogleCustomerReviews() )->render_prompt( 7 ); check( '' === ob_get_clean(), 'Wrong order key exposes prompt.' );
	$_GET['key'] = 'wc_order_key_7';
	$GLOBALS['gcr_orders'][7]->customer = 50;
	ob_start(); ( new GoogleCustomerReviews() )->render_prompt( 7 ); check( '' === ob_get_clean(), 'Wrong customer sees account order.' );
	$GLOBALS['gcr_user'] = 50;
	ob_start(); ( new GoogleCustomerReviews() )->render_prompt( 7 ); check( '' !== ob_get_clean(), 'Order owner cannot see prompt.' );
	$GLOBALS['gcr_orders'][7]->customer = 0;
	$GLOBALS['gcr_user'] = 0;
	$GLOBALS['gcr_endpoint_order'] = 8;
	ob_start(); ( new GoogleCustomerReviews() )->render_prompt( 7 ); check( '' === ob_get_clean(), 'Wrong endpoint order accepted.' );
	$GLOBALS['gcr_endpoint_order'] = 7;

	$request = new WP_REST_Request( array( 'id' => 7, 'token' => $token ) );
	check( $integration->authorize_prompt_request( $request ) instanceof WP_Error, 'GET transport permitted Yes.' );
	check( $integration->record_prompt_yes( $request ) instanceof WP_Error && 0 === $GLOBALS['gcr_saves'], 'GET method override saved Yes.' );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	check( true === $integration->authorize_prompt_request( $request ), 'Guest signature rejected.' );
	$wrong_order = new WP_REST_Request( array( 'id' => 8, 'token' => $token ) );
	check( $integration->record_prompt_yes( $wrong_order ) instanceof WP_Error, 'Cross-order signature accepted.' );
	$bad_token = new WP_REST_Request( array( 'id' => 7, 'token' => array( $token ) ) );
	check( $integration->record_prompt_yes( $bad_token ) instanceof WP_Error, 'Array token accepted.' );
	$GLOBALS['gcr_salt'] = 'rotated-secret';
	check( $integration->record_prompt_yes( $request ) instanceof WP_Error, 'Rotated signature accepted.' );
	$GLOBALS['gcr_salt'] = 'test-site-secret';
	$GLOBALS['gcr_save_fail'] = true;
	check( 'reviewbird_gcr_save_failed' === $integration->record_prompt_yes( $request )->code && 0 === $GLOBALS['gcr_saves'], 'Save failure not returned.' );
	$GLOBALS['gcr_save_fail'] = false;
	$GLOBALS['gcr_save_swallow'] = true;
	$failed_result = $integration->record_prompt_yes( $request );
	check( $failed_result instanceof WP_Error && 'reviewbird_gcr_save_failed' === $failed_result->code, 'A swallowed order save failure returned consent success.' );
	check( empty( $GLOBALS['gcr_orders'][7]->meta ) && 0 === $GLOBALS['gcr_saves'], 'Failed save persisted Yes.' );
	$GLOBALS['gcr_save_swallow'] = false;
	$result = $integration->record_prompt_yes( $request );
	check( $result instanceof WP_REST_Response && 200 === $result->status && 1 === $GLOBALS['gcr_saves'], 'Yes was not saved.' );
	check( 1800000000 === $GLOBALS['gcr_orders'][7]->modified, 'Yes did not advance the order modified date for the next sync.' );
	$again = $integration->record_prompt_yes( $request );
	check( $again->data === $result->data && 1 === $GLOBALS['gcr_saves'], 'Repeat Yes changed first timestamp.' );
	check( empty( $GLOBALS['gcr_orders'][8]->meta ), 'Yes affected another order.' );
	$GLOBALS['gcr_options']['reviewbird_store_status']['google_customer_reviews']['enabled'] = false;
	check( $integration->record_prompt_yes( $request ) instanceof WP_Error, 'Repeat Yes bypasses current feature check.' );
	$GLOBALS['gcr_options']['reviewbird_store_status'] = $initial_status;
	ob_start(); ( new GoogleCustomerReviews() )->render_prompt( 7 ); check( '' === ob_get_clean(), 'Saved Yes prompt reappears.' );
	check( $url === GoogleCustomerReviews::opt_in_url( $GLOBALS['gcr_orders'][7] ), 'Direct retry link changed after Yes.' );
	$response = $integration->add_order_response( new WP_REST_Response( array() ), $GLOBALS['gcr_orders'][7] );
	check( $response->data['reviewbird_google_customer_reviews']['prompt_yes_at'] === $result->data['prompt_yes_at'], 'Order API timestamp is wrong.' );
	check( $response->data['reviewbird_google_customer_reviews']['opt_in_url'] === $url, 'Order API link is wrong.' );
	check( 1 === $GLOBALS['gcr_saves'], 'Order API GET wrote metadata.' );

	$GLOBALS['gcr_options']['reviewbird_google_customer_reviews_prompt'] = array( 'heading' => '<script>unsafe</script>', 'message' => "Line one\nLine two", 'yes_label' => '', 'no_label' => array() );
	$prompt = GoogleCustomerReviews::prompt_settings();
	check( 'unsafe' === $prompt['heading'] && 'Yes' === $prompt['yes_label'] && 'No' === $prompt['no_label'], 'Prompt defaults or sanitization failed.' );
	check( "Line one\nLine two" === $prompt['message'], 'Prompt message line breaks lost.' );

	$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --page';
	exec( $command, $page_lines, $exit_code );
	$page = implode( "\n", $page_lines );
	check( 0 === $exit_code && false !== strpos( $page, 'GCR_GET_NO_WRITES' ), 'Direct page failed or changed metadata.' );
	check( 'direct' === card_config( $page )['mode'] && false === strpos( $page, 'data-gcr-yes' ), 'Direct page has a custom prompt.' );
	check( false !== strpos( $page, 'noindex, nofollow' ), 'Direct page can be indexed.' );

	// Manual refresh uses the scheduler and returns the same saved GCR state as the page.
	$settings = new \reviewbird\Admin\Settings();
	$_POST = array( 'nonce' => 'valid-nonce' );
	$GLOBALS['gcr_admin'] = true;
	$fresh = $initial_status;
	$fresh['google_customer_reviews']['enabled'] = false;
	$GLOBALS['gcr_api_response'] = $fresh;
	try { $settings->handle_clear_health_cache(); } catch ( \reviewbird\Admin\GcrAdminResponse $response ) {
		check( 200 === $response->getCode() && $response->data['status'] === $fresh, 'Refresh did not return the fresh response.' );
		check( 'disabled' === $response->data['googleCustomerReviews']['status'] && false === $response->data['googleCustomerReviews']['enabled'], 'Fresh disabled feature state is wrong.' );
		check( true === $response->data['googleCustomerReviews']['promptEnabled'], 'Refresh lost local prompt default.' );
	}
	check( $GLOBALS['gcr_options']['reviewbird_store_status'] === $fresh, 'Refresh did not update the saved cache.' );
	$before_calls = $GLOBALS['gcr_api_calls'];
	$_POST['nonce'] = 'wrong';
	try { $settings->handle_clear_health_cache(); } catch ( \reviewbird\Admin\GcrAdminResponse $response ) {
		check( 403 === $response->getCode() && $before_calls === $GLOBALS['gcr_api_calls'], 'Invalid nonce refreshed the cache.' );
	}
	$_POST['nonce'] = 'valid-nonce';
	$GLOBALS['gcr_admin'] = false;
	try { $settings->handle_clear_health_cache(); } catch ( \reviewbird\Admin\GcrAdminResponse $response ) {
		check( 403 === $response->getCode() && $before_calls === $GLOBALS['gcr_api_calls'], 'Unprivileged user refreshed the cache.' );
	}
	$GLOBALS['gcr_admin'] = true;
	foreach ( array( new WP_Error( 'network', 'Offline' ), new WP_Error( 'http', 'Server error', array( 'status' => 500, 'response' => array( 'status' => 'not_connected' ) ) ), null, array(), array( 'status' => array() ) ) as $bad_response ) {
		$GLOBALS['gcr_api_response'] = $bad_response;
		try { $settings->handle_clear_health_cache(); } catch ( \reviewbird\Admin\GcrAdminResponse $response ) {
			check( 502 === $response->getCode(), 'Failed refresh returned success.' );
			check( $GLOBALS['gcr_options']['reviewbird_store_status'] === $fresh, 'Failed refresh deleted the last good cache.' );
		}
	}
	$GLOBALS['gcr_api_response'] = $initial_status;
	$GLOBALS['gcr_option_write_fail'] = true;
	try { $settings->handle_clear_health_cache(); } catch ( \reviewbird\Admin\GcrAdminResponse $response ) {
		check( 502 === $response->getCode() && $GLOBALS['gcr_options']['reviewbird_store_status'] === $fresh, 'Cache write failure returned success.' );
	}
	$GLOBALS['gcr_option_write_fail'] = false;
	try { $settings->handle_clear_health_cache(); } catch ( \reviewbird\Admin\GcrAdminResponse $response ) {
		check( 'enabled' === $response->data['googleCustomerReviews']['status'] && true === $response->data['googleCustomerReviews']['enabled'], 'Refresh did not restore enabled feature state.' );
	}
	check( $initial_status === ( new \reviewbird\Integration\HealthScheduler() )->refresh_health_status(), 'Unchanged valid cache is treated as a write failure.' );
	$disconnected = array( 'status' => 'not_connected', 'message' => 'No store is connected.' );
	$GLOBALS['gcr_api_response'] = new WP_Error( 'http', 'Not connected', array( 'status' => 404, 'response' => $disconnected ) );
	try { $settings->handle_clear_health_cache(); } catch ( \reviewbird\Admin\GcrAdminResponse $response ) {
		check( 200 === $response->getCode() && 'disabled' === $response->data['googleCustomerReviews']['status'], 'Known 404 disconnect was not applied.' );
	}
	check( $disconnected === $GLOBALS['gcr_options']['reviewbird_store_status'] && null === GoogleCustomerReviews::configuration(), 'Disconnect left stale enabled config.' );
	$GLOBALS['gcr_options']['reviewbird_store_status'] = $initial_status;
	$GLOBALS['gcr_options']['reviewbird_store_status']['google_customer_reviews']['expires_at'] = 1800000000;
	check( 'unknown' === GoogleCustomerReviews::status(), 'Expired feature state is not unknown.' );
	unset( $GLOBALS['gcr_options']['reviewbird_store_status']['google_customer_reviews'] );
	check( 'unknown' === GoogleCustomerReviews::status(), 'Missing feature state is not unknown.' );
	$GLOBALS['gcr_options']['reviewbird_store_status'] = $initial_status;
	$_POST = array();

	echo 'Google Customer Reviews: ' . $checks . " checks passed.\n";
}
