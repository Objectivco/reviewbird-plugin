<?php
/**
 * The core plugin class.
 *
 * @package ReviewBop
 */

namespace ReviewBop\Core;

use ReviewBop\Admin\Settings;
use ReviewBop\Api\Client;
use ReviewBop\Api\CouponController;
use ReviewBop\Api\ProductEndpoint;
use ReviewBop\Api\RatingsController;
use ReviewBop\Api\SettingsController;
use ReviewBop\Core\ActionScheduler;
use ReviewBop\Integration\OrderSync;
use ReviewBop\Integration\ProductSync;
use ReviewBop\Integration\RatingOverride;
use ReviewBop\Integration\ReviewSync;
use ReviewBop\Integration\WooCommerce;
use ReviewBop\OAuth\Handler;

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
		$this->plugin_name = 'reviewbop-reviews';
		$this->version     = REVIEWBOP_VERSION;
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
			REVIEWBOP_TEXT_DOMAIN,
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	/**
	 * Initialize plugin hooks.
	 */
	private function init_hooks() {
		// Initialize Action Scheduler integration.
		ActionScheduler::init();
		
		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		
		// Admin hooks.
		if ( is_admin() ) {
			$settings = new Settings();
			add_action( 'admin_menu', array( $settings, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $settings, 'enqueue_scripts' ) );
		}

		// OAuth hooks.
		$oauth_handler = new Handler();
		add_action( 'init', array( $oauth_handler, 'handle_oauth_callback' ) );
		add_action( 'wp_ajax_reviewbop_start_oauth', array( $oauth_handler, 'start_oauth_flow' ) );

		// Public hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
		add_shortcode( 'reviewbop_widget', array( $this, 'widget_shortcode' ) );

		// Sync Action Scheduler hooks.
		add_action( 'reviewbop_sync_product_batch', array( $this, 'process_sync_batch' ), 10, 2 );
		add_action( 'reviewbop_sync_review_batch', array( $this, 'process_review_sync_batch' ), 10, 2 );

		// WooCommerce integration.
		if ( class_exists( 'WooCommerce' ) ) {
			$woocommerce = new WooCommerce();
			$api_client = new Client();

			// Rating override integration.
			new RatingOverride();

			// Product sync hooks.
			add_action( 'woocommerce_new_product', array( $woocommerce, 'sync_product' ) );
			add_action( 'woocommerce_update_product', array( $woocommerce, 'sync_product' ) );

			// Product lifecycle hooks.
			add_action( 'added_post_meta', array( $woocommerce, 'handle_product_meta_update' ), 10, 4 );
			add_action( 'updated_post_meta', array( $woocommerce, 'handle_product_meta_update' ), 10, 4 );
			add_action( 'woocommerce_product_duplicate', array( $woocommerce, 'handle_product_duplicate' ), 10, 2 );
			add_action( 'wp_trash_post', array( $woocommerce, 'handle_product_trash' ) );
			add_action( 'untrash_post', array( $woocommerce, 'handle_product_untrash' ) );
			add_action( 'before_delete_post', array( $woocommerce, 'handle_product_delete' ) );

			// Review sync hooks.
			add_action( 'comment_post', array( $woocommerce, 'sync_review' ) );
			add_action( 'wp_set_comment_status', array( $woocommerce, 'sync_review_status' ), 10, 2 );
			add_action( 'delete_comment', array( $woocommerce, 'delete_review' ) );

			// Order event hooks.
			add_action( 'woocommerce_order_status_changed', array( $woocommerce, 'track_order_event' ), 10, 3 );

			// Order sync for review requests.
			$order_sync = new OrderSync( $api_client );
			$order_sync->init();

			// Schema markup for SEO - add to wp_head on product pages.
			add_action( 'wp_head', array( $woocommerce, 'output_product_schema' ), 5 );

			// Template override - use ReviewBop template for product reviews.
			add_filter( 'comments_template', array( $this, 'comments_template_loader' ), 50 );
		}
	}

	/**
	 * Process product sync batch via Action Scheduler.
	 *
	 * @param array $batch       Array of product IDs.
	 * @param int   $batch_index Batch index number.
	 */
	public function process_sync_batch( $batch, $batch_index ) {
		$product_sync = new ProductSync();
		$product_sync->process_batch( $batch, $batch_index );
	}

	/**
	 * Process review sync batch via Action Scheduler.
	 *
	 * @param array $batch       Array of review comment IDs.
	 * @param int   $batch_index Batch index number.
	 */
	public function process_review_sync_batch( $batch, $batch_index ) {
		$review_sync = new ReviewSync();
		$review_sync->process_batch( $batch, $batch_index );
	}

	/**
	 * Enqueue public scripts and styles.
	 */
	public function enqueue_public_scripts() {
		// Only load on product pages or pages with shortcode.
		if ( is_product() || $this->has_reviewbop_shortcode() ) {
			// Enqueue the new modular TypeScript widget CSS.
			wp_enqueue_style(
				'reviewbop-widget',
				reviewbop_get_api_url() . '/build/review-widget.css',
				array(),
				$this->version
			);

			// Enqueue the new modular TypeScript widget JS.
			wp_enqueue_script(
				'reviewbop-widget',
				reviewbop_get_api_url() . '/build/review-widget.js',
				array(),
				$this->version,
				true
			);

			// Pass configuration to widget JavaScript.
			wp_localize_script(
				'reviewbop-widget',
				'ReviewBopConfig',
				array(
					'apiUrl' => reviewbop_get_api_url(),
					'storeId' => get_option( 'reviewbop_store_id' ),
					'widgetPrefix' => 'reviewbop-widget-container-',
				)
			);
		}
	}

	/**
	 * Check if current page has ReviewBop shortcode.
	 *
	 * @return bool
	 */
	private function has_reviewbop_shortcode() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'reviewbop_widget' );
	}

	/**
	 * Handle ReviewBop widget shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function widget_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'product_id' => '',
			),
			$atts,
			'reviewbop_widget'
		);

		if ( ! $atts['product_id'] ) {
			return '<p>' . esc_html__( 'ReviewBop: Product ID is required.', 'reviewbop-reviews' ) . '</p>';
		}

		$api_client = new Client();
		$store_id = $api_client->get_store_id_for_frontend();

		if ( ! $store_id ) {
			return '<p>' . esc_html__( 'ReviewBop: Store not connected.', 'reviewbop-reviews' ) . '</p>';
		}

		$widget_id = 'reviewbop-widget-' . uniqid();

		return sprintf(
			'<div id="%s" data-store-id="%s" data-product-key="%s"></div><script>if(typeof ReviewBop !== "undefined") ReviewBop.init();</script>',
			esc_attr( $widget_id ),
			esc_attr( $store_id ),
			esc_attr( $atts['product_id'] )
		);
	}

	/**
	 * Get plugin name.
	 *
	 * @return string
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$settings_controller = new SettingsController();
		$settings_controller->register_routes();

		$ratings_controller = new RatingsController();

		register_rest_route(
			'reviewbop/v1',
			'/ratings/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $ratings_controller, 'update_ratings' ),
				'permission_callback' => array( 'ReviewBop\Api\RatingsController', 'permission_callback' ),
			)
		);

		register_rest_route(
			'reviewbop/v1',
			'/verified-purchase/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( $ratings_controller, 'check_verified_purchase' ),
				'permission_callback' => array( 'ReviewBop\Api\RatingsController', 'permission_callback' ),
			)
		);

		// Coupon controller routes
		$coupon_controller = new CouponController();
		$coupon_controller->register_routes();

		// Product endpoint for ReviewBop to fetch product data
		$product_endpoint = new ProductEndpoint();
		register_rest_route(
			'reviewbop/v1',
			'/products/(?P<external_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $product_endpoint, 'get_product' ),
				'permission_callback' => array( 'ReviewBop\Api\ProductEndpoint', 'permission_callback' ),
			)
		);
	}

	/**
	 * Load comments template for products.
	 *
	 * Override the comments template for WooCommerce products to use ReviewBop widget.
	 *
	 * @param string $template Template path.
	 * @return string Template file path.
	 */
	public function comments_template_loader( $template ) {
		if ( get_post_type() !== 'product' ) {
			return $template;
		}

		$reviewbop_template = trailingslashit( REVIEWBOP_PLUGIN_DIR ) . 'templates/woocommerce/single-product-reviews.php';

		if ( file_exists( $reviewbop_template ) ) {
			return $reviewbop_template;
		}

		return $template;
	}

}