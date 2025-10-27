<?php
/**
 * The core plugin class.
 *
 * @package reviewbird
 */

namespace reviewbird\Core;

use reviewbird\Admin\Settings;
use reviewbird\Api\Client;
use reviewbird\Api\CouponController;
use reviewbird\Api\ProductEndpoint;
use reviewbird\Api\RatingsController;
use reviewbird\Api\SettingsController;
use reviewbird\Core\ActionScheduler;
use reviewbird\Integration\OrderSync;
use reviewbird\Integration\ProductSync;
use reviewbird\Integration\RatingOverride;
use reviewbird\Integration\ReviewSync;
use reviewbird\Integration\WooCommerce;
use reviewbird\OAuth\Handler;

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
			REVIEWBIRD_TEXT_DOMAIN,
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
		add_action( 'wp_ajax_reviewbird_start_oauth', array( $oauth_handler, 'start_oauth_flow' ) );

		// Public hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
		add_shortcode( 'reviewbird_widget', array( $this, 'widget_shortcode' ) );

		// Sync Action Scheduler hooks.
		add_action( 'reviewbird_sync_product_batch', array( $this, 'process_sync_batch' ), 10, 2 );
		add_action( 'reviewbird_sync_review_batch', array( $this, 'process_review_sync_batch' ), 10, 2 );

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

			// Template override - use reviewbird template for product reviews.
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
		if ( is_product() || $this->has_reviewbird_shortcode() ) {
			// Enqueue the widget CSS.
			wp_enqueue_style(
				'reviewbird-widget',
				reviewbird_get_api_url() . '/build/review-widget.css',
				array(),
				$this->version
			);

			// Enqueue the new Svelte widget JS.
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
					'apiUrl' => reviewbird_get_api_url(),
					'storeId' => get_option( 'reviewbird_store_id' ),
					'widgetPrefix' => 'reviewbird-widget-container-',
				)
			);
		}
	}

	/**
	 * Check if current page has reviewbird shortcode.
	 *
	 * @return bool
	 */
	private function has_reviewbird_shortcode() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'reviewbird_widget' );
	}

	/**
	 * Handle reviewbird widget shortcode.
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
			'reviewbird_widget'
		);

		if ( ! $atts['product_id'] ) {
			return '<p>' . esc_html__( 'reviewbird: Product ID is required.', 'reviewbird-reviews' ) . '</p>';
		}

		$api_client = new Client();
		$store_id = $api_client->get_store_id_for_frontend();

		if ( ! $store_id ) {
			return '<p>' . esc_html__( 'reviewbird: Store not connected.', 'reviewbird-reviews' ) . '</p>';
		}

		$widget_id = 'reviewbird-widget-' . uniqid();

		return sprintf(
			'<div id="%s" data-store-id="%s" data-product-key="%s"></div><script>if(typeof reviewbird !== "undefined") reviewbird.init();</script>',
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

		// Coupon controller routes
		$coupon_controller = new CouponController();
		$coupon_controller->register_routes();

		// Product endpoint for reviewbird to fetch product data
		$product_endpoint = new ProductEndpoint();
		register_rest_route(
			'reviewbird/v1',
			'/products/(?P<external_id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $product_endpoint, 'get_product' ),
				'permission_callback' => array( 'reviewbird\Api\ProductEndpoint', 'permission_callback' ),
			)
		);
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
		if ( get_post_type() !== 'product' ) {
			return $template;
		}

		$reviewbird_template = trailingslashit( REVIEWBIRD_PLUGIN_DIR ) . 'templates/woocommerce/single-product-reviews.php';

		if ( file_exists( $reviewbird_template ) ) {
			return $reviewbird_template;
		}

		return $template;
	}

}