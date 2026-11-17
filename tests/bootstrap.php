<?php
/**
 * PHPUnit bootstrap: a small in-memory WordPress and WooCommerce that runs the real plugin code.
 *
 * Every test starts from WP_Fake::reset(). The standalone *Check.php files keep their own stubs.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Test doubles for WordPress and WooCommerce functions.

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPINC', 'wp-includes' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/** Test state for the fake WordPress functions below. */
final class WP_Fake {
	public static $options;
	public static $transients;
	public static $ttls;
	public static $meta;
	public static $post_types;
	public static $products;
	public static $hooks;
	public static $actions;
	public static $doing;
	public static $scripts;
	public static $script_calls;
	public static $printed;
	public static $styles;
	public static $localized;
	public static $inline;
	public static $requests;
	public static $response;
	public static $comment_meta;
	public static $attachments;
	public static $comments_open;
	public static $context;
	public static $caps;
	public static $routes;
	public static $rest_fields;
	public static $purchases;
	public static $purchase_checks;
	public static $orders;
	public static $order_queries;
	public static $locale;
	public static $deleted_wc_transients;
	public static $fail_writes;
	public static $removed_hooks;

	public static function reset(): void {
		self::$options               = array();
		self::$transients            = array();
		self::$ttls                  = array();
		self::$meta                  = array();
		self::$post_types            = array();
		self::$products              = array();
		self::$hooks                 = array();
		self::$actions               = array();
		self::$doing                 = null;
		self::$scripts               = array();
		self::$script_calls          = array();
		self::$printed               = array();
		self::$styles                = array();
		self::$localized             = array();
		self::$inline                = array();
		self::$requests              = array();
		self::$response              = array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
		self::$comment_meta          = array();
		self::$attachments           = array();
		self::$comments_open         = true;
		self::$context               = array(
			'is_product'     => false,
			'queried_id'     => 0,
			'logged_in'      => false,
			'locale'         => 'en_US',
			'view_order'     => false,
			'order_received' => false,
		);
		self::$caps                  = array();
		self::$routes                = array();
		self::$rest_fields           = array();
		self::$purchases             = array();
		self::$purchase_checks       = array();
		self::$orders                = array();
		self::$order_queries         = array();
		self::$locale                = 'fi';
		self::$deleted_wc_transients = array();
		self::$fail_writes           = false;
		self::$removed_hooks         = array();
		$_GET                        = array();
		$_POST                       = array();
		unset( $GLOBALS['product'], $GLOBALS['post'] );
	}

	/** Store a connected store with the widget and schema settings on. */
	public static function connect( int $store_id = 37 ): void {
		self::$options['reviewbird_store_id']      = $store_id;
		self::$options['reviewbird_enable_widget'] = 'yes';
		self::$options['reviewbird_enable_schema'] = 'yes';
		self::$options['reviewbird_store_status']  = array(
			'status'                  => 'healthy',
			'has_active_subscription' => true,
		);
	}

	/** Add a product that wc_get_product() returns. */
	public static function product( int $id, array $props = array() ): WC_Product {
		$product                 = new WC_Product( $id, $props );
		self::$products[ $id ]   = $product;
		self::$post_types[ $id ] = 'variation' === $product->get_type() ? 'product_variation' : 'product';
		return $product;
	}
}

/** Thrown by wp_send_json_*() so handlers can be tested without exiting. */
final class WP_Fake_Json_Response extends RuntimeException {
	public $data;
	public function __construct( $data, int $status ) {
		parent::__construct( '', $status );
		$this->data = $data;
	}
}

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_REST_Request {
	private $params;
	private $headers;
	public function __construct( array $params = array(), array $headers = array() ) {
		$this->params  = $params;
		$this->headers = array_change_key_case( $headers );
	}
	public function get_param( $key ) { return $this->params[ $key ] ?? null; }
	public function get_header( $key ) { return $this->headers[ strtolower( $key ) ] ?? null; }
}

class WP_REST_Response {
	public $data;
	public $status;
	public $headers;
	public function __construct( $data = null, $status = 200, $headers = array() ) {
		$this->data    = $data;
		$this->status  = $status;
		$this->headers = $headers;
	}
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
	public function get_headers() { return $this->headers; }
}

class WC_Product {
	public $id;
	public $props;
	public function __construct( $id = 0, array $props = array() ) {
		$this->id    = $id;
		$this->props = $props + array(
			'type'           => 'simple',
			'status'         => 'publish',
			'parent_id'      => 0,
			'attributes'     => array(),
			'average_rating' => 0,
			'rating_count'   => 0,
		);
	}
	public function get_id() { return $this->id; }
	public function get_type() { return $this->props['type']; }
	public function is_type( $type ) { return in_array( $this->props['type'], (array) $type, true ); }
	public function get_status() { return $this->props['status']; }
	public function get_parent_id() { return $this->props['parent_id']; }
	public function get_attributes( $context = 'view' ) { return $this->props['attributes']; }
	public function get_average_rating() { return $this->props['average_rating']; }
	public function get_rating_count() { return $this->props['rating_count']; }
	public function exists() { return true; }
}

class WC_Order {}

class WC_Countries {
	public function get_countries() {
		return array(
			'NG' => 'Nigeria',
			'US' => 'United States (US)',
			'GB' => 'United Kingdom (UK)',
			'UM' => 'United States (US) Minor Outlying Islands',
			'VI' => 'Virgin Islands (US)',
			'CW' => 'Cura&ccedil;ao',
		);
	}
}

// Hooks.
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	WP_Fake::$hooks[ $hook ][ $priority ][] = array( $callback, $accepted_args );
	return true;
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return add_filter( $hook, $callback, $priority, $accepted_args ); }
function remove_filter( $hook, $callback, $priority = 10 ) {
	foreach ( WP_Fake::$hooks[ $hook ][ $priority ] ?? array() as $index => $registered ) {
		if ( $registered[0] === $callback ) {
			unset( WP_Fake::$hooks[ $hook ][ $priority ][ $index ] );
			return true;
		}
	}
	return false;
}
function remove_action( $hook, $callback, $priority = 10 ) { return remove_filter( $hook, $callback, $priority ); }
function remove_all_actions( $hook ) {
	unset( WP_Fake::$hooks[ $hook ] );
	WP_Fake::$removed_hooks[] = $hook;
}
function has_filter( $hook, $callback = false ) {
	foreach ( WP_Fake::$hooks[ $hook ] ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $registered ) {
			if ( false === $callback ) {
				return true;
			}
			if ( $registered[0] === $callback ) {
				return $priority;
			}
		}
	}
	return false;
}
function has_action( $hook, $callback = false ) { return has_filter( $hook, $callback ); }
function apply_filters( $hook, $value, ...$args ) {
	$priorities = WP_Fake::$hooks[ $hook ] ?? array();
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as list( $callback, $accepted_args ) ) {
			$value = $callback( ...array_slice( array_merge( array( $value ), $args ), 0, $accepted_args ) );
		}
	}
	return $value;
}
function do_action( $hook, ...$args ) {
	WP_Fake::$actions[] = array_merge( array( $hook ), $args );
	$priorities         = WP_Fake::$hooks[ $hook ] ?? array();
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as list( $callback, $accepted_args ) ) {
			$callback( ...array_slice( $args, 0, $accepted_args ) );
		}
	}
}
function doing_action( $hook = null ) { return $hook === WP_Fake::$doing; }
function add_shortcode( $tag, $callback ) { WP_Fake::$hooks[ 'shortcode_' . $tag ][10][] = array( $callback, 1 ); }
function wp_is_block_theme() { return false; }

// Plugin loading.
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://store.test/wp-content/plugins/reviewbird/'; }
function plugin_basename( $file ) { return 'reviewbird/' . basename( $file ); }
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function is_admin() { return false; }
function is_network_admin() { return false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }

// Text and data.
function __( $text ) { return $text; }
function esc_html__( $text ) { return esc_html( $text ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function esc_attr_e( $text ) { echo esc_attr( $text ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function wp_kses_post( $html ) { return $html; }
function wp_kses( $html ) { return $html; }
function number_format_i18n( $number, $decimals = 0 ) { return number_format( $number, $decimals ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ); }
function sanitize_email( $email ) { return $email; }
function sanitize_hex_color( $color ) { return preg_match( '/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/i', $color ) ? $color : null; }
function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_parse_list( $list ) { return is_array( $list ) ? $list : preg_split( '/[\s,]+/', $list, -1, PREG_SPLIT_NO_EMPTY ); }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function shortcode_atts( $defaults, $atts ) { return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) ); }
function wp_date( $format, $timestamp ) { return gmdate( $format, $timestamp ); }

// Options and transients.
function get_option( $key, $default = false ) { return array_key_exists( $key, WP_Fake::$options ) ? WP_Fake::$options[ $key ] : $default; }
function update_option( $key, $value, $autoload = null ) {
	if ( WP_Fake::$fail_writes ) {
		return false;
	}
	WP_Fake::$options[ $key ] = $value;
	return true;
}
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	if ( array_key_exists( $key, WP_Fake::$options ) ) {
		return false;
	}
	WP_Fake::$options[ $key ] = $value;
	return true;
}
function delete_option( $key ) { unset( WP_Fake::$options[ $key ] ); return true; }
function get_transient( $key ) { return WP_Fake::$transients[ $key ] ?? false; }
function set_transient( $key, $value, $ttl = 0 ) {
	WP_Fake::$transients[ $key ] = $value;
	WP_Fake::$ttls[ $key ]       = $ttl;
	return true;
}
function delete_transient( $key ) { unset( WP_Fake::$transients[ $key ] ); return true; }
function wp_cache_delete( ...$args ) {}

/** Options table queries used by reviewbird_acquire_lock(). */
$GLOBALS['wpdb'] = new class() {
	public $options = 'wp_options';
	public function prepare( $query, ...$args ) { return array( $query, $args ); }
	public function query( $prepared ) {
		list( $query, $args ) = $prepared;
		if ( 0 === strpos( $query, 'INSERT IGNORE' ) ) {
			list( $key, $expires ) = $args;
			if ( isset( WP_Fake::$options[ $key ] ) ) {
				return 0;
			}
		} else {
			list( $expires, $key, $now ) = $args;
			if ( ! isset( WP_Fake::$options[ $key ] ) || WP_Fake::$options[ $key ] > $now ) {
				return 0;
			}
		}
		WP_Fake::$options[ $key ] = $expires;
		return 1;
	}
};

// Posts, comments, and attachments.
function get_post_meta( $id, $key = '', $single = false ) { return WP_Fake::$meta[ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { WP_Fake::$meta[ $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( WP_Fake::$meta[ $id ][ $key ] ); return true; }
function get_post_type( $id = null ) { return WP_Fake::$post_types[ $id ] ?? false; }
function get_posts( $args ) {
	$ids = array();
	foreach ( WP_Fake::$meta as $id => $meta ) {
		if ( (string) ( $meta[ $args['meta_key'] ] ?? '' ) === (string) $args['meta_value'] ) {
			$ids[] = $id;
		}
	}
	sort( $ids );
	return array_slice( $ids, 0, $args['posts_per_page'] );
}
function comments_open( $post_id = null ) {
	WP_Fake::$context['comments_checked'] = $post_id;
	return WP_Fake::$comments_open;
}
function post_password_required( $post = null ) { return false; }
function get_comment_meta( $id, $key, $single = false ) { return WP_Fake::$comment_meta[ $id ][ $key ] ?? ( $single ? '' : array() ); }
function wp_get_attachment_url( $id ) { return WP_Fake::$attachments[ $id ] ?? false; }

// Request context.
function is_product() { return WP_Fake::$context['is_product']; }
function get_queried_object_id() { return WP_Fake::$context['queried_id']; }
function is_user_logged_in() { return WP_Fake::$context['logged_in']; }
function is_wc_endpoint_url( $endpoint ) { return 'view-order' === $endpoint && WP_Fake::$context['view_order']; }
function is_order_received_page() { return WP_Fake::$context['order_received']; }
function get_locale() { return WP_Fake::$context['locale']; }
function get_user_locale() { return WP_Fake::$context['locale']; }
function wp_get_environment_type() { return 'production'; }
function home_url( $path = '' ) { return 'https://store.test' . $path; }
function admin_url( $path = '' ) { return 'https://store.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function nocache_headers() { WP_Fake::$context['no_cache'] = true; }
function current_user_can( $capability ) { return in_array( $capability, WP_Fake::$caps, true ); }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function check_ajax_referer( $action, $query_arg, $stop = true ) { return wp_create_nonce( $action ) === ( $_POST[ $query_arg ] ?? null ); }
function wp_send_json_success( $data ) { throw new WP_Fake_Json_Response( $data, 200 ); }
function wp_send_json_error( $data, $status ) { throw new WP_Fake_Json_Response( $data, $status ); }
function add_menu_page() { return 'toplevel_page_reviewbird-get-started'; }
function add_submenu_page() {}

// Scripts and styles.
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	WP_Fake::$scripts[ $handle ] = array( $src, $in_footer );
	WP_Fake::$script_calls[]     = $handle;
}
function wp_script_is( $handle, $status = 'enqueued' ) {
	return 'done' === $status ? ! empty( WP_Fake::$printed[ $handle ] ) : isset( WP_Fake::$scripts[ $handle ] );
}
function wp_localize_script( $handle, $name, $data ) { WP_Fake::$localized[ $handle ] = array( $name, $data ); }
function wp_add_inline_script( $handle, $script ) { WP_Fake::$inline[ $handle ][] = $script; }
function wp_set_script_translations() {}
function wp_enqueue_style( $handle ) { WP_Fake::$styles[] = $handle; }
function wp_style_add_data() {}

// HTTP.
function wp_remote_request( $url, $args ) {
	WP_Fake::$requests[] = array( $url, $args );
	return WP_Fake::$response;
}
function wp_remote_retrieve_response_code( $response ) { return $response['response']['code']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }

// REST API.
function register_rest_route( $namespace, $route, $args ) { WP_Fake::$routes[ $namespace . $route ] = $args; }
function register_rest_field( $type, $field, $args ) { WP_Fake::$rest_fields[ $type ][ $field ] = $args; }

// WooCommerce.
function WC() {
	return (object) array( 'customer' => null );
}
function wc_get_product( $id ) { return WP_Fake::$products[ $id ] ?? false; }
function wc_get_logger() {
	return new class() {
		public function __call( $level, $args ) {}
	};
}
function wc_delete_product_transients( $id ) { WP_Fake::$deleted_wc_transients[] = $id; }
function wc_attributes_array_filter_variation( $attribute ) { return $attribute && $attribute->get_variation(); }
function wc_customer_bought_product( $email, $user_id, $product_id ) {
	WP_Fake::$purchase_checks[] = array( $email, $product_id );
	return in_array( $product_id, WP_Fake::$purchases[ $email ] ?? array(), true );
}
function wc_get_is_paid_statuses() { return array( 'processing', 'completed' ); }
function wc_get_orders( $args ) {
	WP_Fake::$order_queries[] = $args;
	return array_slice( WP_Fake::$orders, ( $args['page'] - 1 ) * $args['limit'], $args['limit'] );
}
function wc_get_coupon_types() { return array( 'percent' => 'Percentage' ); }
function wc_rest_check_post_permissions() { return true; }
function switch_to_locale( $locale ) { WP_Fake::$locale = $locale; return true; }
function restore_previous_locale() { WP_Fake::$locale = 'fi'; }

WP_Fake::reset();
require dirname( __DIR__ ) . '/reviewbird-plugin.php';
