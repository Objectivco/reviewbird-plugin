<?php
/**
 * The core plugin class.
 *
 * @package reviewbird
 */

namespace reviewbird\Core;

use reviewbird\Admin\Settings;
use reviewbird\Api\ConnectionController;
use reviewbird\Api\CouponController;
use reviewbird\Api\ProductsController;
use reviewbird\Api\RatingsController;
use reviewbird\Integration\RatingOverride;
use reviewbird\Integration\SchemaMarkup;
use reviewbird\Integration\WooCommerce;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 */
class Plugin {

	/**
	 * The unique identifier of this plugin.
	 *
	 * @var string
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->plugin_name = 'reviewbird-reviews';
		$this->version     = REVIEWBIRD_VERSION;
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		$this->load_textdomain();
		$this->init_hooks();
	}

	/**
	 * Load plugin textdomain.
	 */
	private function load_textdomain() {
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'reviewbird-reviews',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Initialize plugin hooks.
	 */
	private function init_hooks() {
		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Enable WooCommerce authentication for reviewbird REST API endpoints.
		add_filter( 'woocommerce_rest_is_request_to_rest_api', array( $this, 'enable_wc_auth_for_reviewbird_api' ), 10, 1 );

		// Admin hooks.
		if ( is_admin() ) {
			$settings = new Settings();
			add_action( 'admin_menu', array( $settings, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $settings, 'enqueue_scripts' ) );
		}

		// Public hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
		add_shortcode( 'reviewbird_widget', array( $this, 'widget_shortcode' ) );

		// Rating override integration.
		new RatingOverride();

		// WooCommerce integration (adds CusRev media to reviews REST API).
		new WooCommerce();

		// Schema markup for SEO - add to wp_head on product pages.
		add_action( 'wp_head', array( $this, 'output_product_schema' ), 5 );

		// Template override - use reviewbird template for product reviews.
		add_filter( 'comments_template', array( $this, 'comments_template_loader' ), 50 );
	}

	/**
	 * Enqueue public scripts and styles.
	 */
	public function enqueue_public_scripts() {
		// Check if widget is enabled AND store can show widget
		if ( ! reviewbird_can_show_widget() ) {
			return;
		}

		// Only load on product pages or pages with shortcode.
		if ( ! is_product() && ! has_shortcode( $post->post_content, 'reviewbird_widget' ) ) {
			return;
		}

		// Enqueue the new Svelte widget JS (CSS is inlined in the JS bundle).
		wp_enqueue_script(
			'reviewbird-widget',
			reviewbird_get_api_url() . '/build/review-widget-v2.js',
			array(),
			$this->version,
			true
		);

		// Pass configuration to widget JavaScript.
		wp_localize_script(
			'reviewbird-widget',
			'reviewbirdConfig',
			array(
				'apiUrl'       => reviewbird_get_api_url(),
				'storeId'      => get_option( 'reviewbird_store_id' ),
				'widgetPrefix' => 'reviewbird-widget-container-',
			)
		);
	}

	/**
	 * Handle reviewbird widget shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function widget_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(),
			$atts,
			'reviewbird_widget'
		);

		return reviewbird_render_widget( $atts['product_id'] );
	}


	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$ratings_controller = new RatingsController();

		register_rest_route(
			'reviewbird/v1',
			'/ratings/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $ratings_controller, 'update_ratings' ),
				'permission_callback' => array( 'reviewbird\Api\RatingsController', 'permission_callback' ),
			)
		);

		register_rest_route(
			'reviewbird/v1',
			'/verified-purchase/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $ratings_controller, 'check_verified_purchase' ),
				'permission_callback' => array( 'reviewbird\Api\RatingsController', 'permission_callback' ),
			)
		);

		// Connection controller routes
		$connection_controller = new ConnectionController();
		$connection_controller->register_routes();

		// Coupon controller routes
		$coupon_controller = new CouponController();
		$coupon_controller->register_routes();

		// Products controller routes
		$products_controller = new ProductsController();
		$products_controller->register_routes();
	}

	/**
	 * Output product schema markup in head.
	 */
	public function output_product_schema() {
		if ( ! is_product() ) {
			return;
		}

		// Check if store has subscription
		if ( ! reviewbird_is_store_connected() ) {
			return; // Don't output schema without subscription
		}

		// Get product ID from the current post.
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}

		// Verify this is actually a product.
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		// Load schema markup class.
		$schema_markup = new SchemaMarkup();
		$schema_markup->output_product_schema( $product_id );
	}

	/**
	 * Load comments template for products.
	 *
	 * Override the comments template for WooCommerce products to use reviewbird widget.
	 *
	 * @param string $template Template path.
	 * @return string Template file path.
	 */
	public function comments_template_loader( $template ) {
		// Check if widget is enabled AND store can show widget
		if ( ! reviewbird_can_show_widget() ) {
			return $template; // Falls back to WooCommerce default reviews
		}

		if ( get_post_type() !== 'product' ) {
			return $template;
		}

		$reviewbird_template = trailingslashit( REVIEWBIRD_PLUGIN_DIR ) . 'templates/woocommerce/single-product-reviews.php';

		if ( file_exists( $reviewbird_template ) ) {
			return $reviewbird_template;
		}

		return $template;
	}


	/**
	 * Enable WooCommerce authentication for reviewbird REST API endpoints.
	 *
	 * @param bool $is_request_to_wc_api Whether this is a request to WC API.
	 * @return bool
	 */
	public function enable_wc_auth_for_reviewbird_api( $is_request_to_wc_api ) {
		// If already determined to be a WC API request, return true.
		if ( $is_request_to_wc_api ) {
			return true;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Enable WooCommerce authentication for reviewbird/v1 endpoints.
		return ( false !== strpos( $request_uri, $rest_prefix . 'reviewbird/' ) );
	}
}
