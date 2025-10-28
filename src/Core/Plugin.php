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
use reviewbird\Api\ProductsController;
use reviewbird\Api\RatingsController;
use reviewbird\Integration\RatingOverride;
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

		// Admin hooks.
		if ( is_admin() ) {
			$settings = new Settings();
			add_action( 'admin_menu', array( $settings, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $settings, 'enqueue_scripts' ) );
		}

		// Public hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
		add_shortcode( 'reviewbird_widget', array( $this, 'widget_shortcode' ) );

		// WooCommerce integration.
		if ( class_exists( 'WooCommerce' ) ) {
			$woocommerce = new WooCommerce();

			// Rating override integration.
			new RatingOverride();

			// Schema markup for SEO - add to wp_head on product pages.
			add_action( 'wp_head', array( $woocommerce, 'output_product_schema' ), 5 );

			// Template override - use reviewbird template for product reviews.
			add_filter( 'comments_template', array( $this, 'comments_template_loader' ), 50 );
		}
	}

	/**
	 * Enqueue public scripts and styles.
	 */
	public function enqueue_public_scripts() {
		// Check if widget is enabled
		if ( get_option( 'reviewbird_enable_widget', 'yes' ) !== 'yes' ) {
			return;
		}

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

		// Products controller routes
		$products_controller = new ProductsController();
		$products_controller->register_routes();
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
		// Check if widget is enabled
		if ( get_option( 'reviewbird_enable_widget', 'yes' ) !== 'yes' ) {
			return $template;
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

}
