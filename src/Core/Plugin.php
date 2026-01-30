<?php
/**
 * The core plugin class.
 *
 * @package reviewbird
 */

namespace reviewbird\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use reviewbird\Admin\Settings;
use reviewbird\Api\ConnectionController;
use reviewbird\Api\CouponController;
use reviewbird\Api\ProductsController;
use reviewbird\Api\RatingsController;
use reviewbird\Integration\HealthScheduler;
use reviewbird\Integration\RatingOverride;
use reviewbird\Integration\SchemaMarkup;
use reviewbird\Integration\SchemaScheduler;
use reviewbird\Integration\StarRatingDisplay;
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
	 * Whether the carousel script has been enqueued.
	 *
	 * @var bool
	 */
	private static $carousel_script_enqueued = false;

	/**
	 * Initialize the plugin.
	 */
	public function __construct() {
		$this->plugin_name = 'reviewbird';
		$this->version     = REVIEWBIRD_VERSION;
	}

	/**
	 * Run the plugin.
	 */
	public function run() {
		$this->init_hooks();
	}

	/**
	 * Initialize plugin hooks.
	 */
	private function init_hooks() {
		// Load translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Enable WooCommerce authentication for reviewbird REST API endpoints.
		add_filter( 'woocommerce_rest_is_request_to_rest_api', array( $this, 'enable_wc_auth_for_reviewbird_api' ), 10, 1 );

		// Admin hooks.
		if ( is_admin() ) {
			$settings = new Settings();
			add_action( 'admin_menu', array( $settings, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $settings, 'enqueue_scripts' ) );
			add_filter( 'plugin_action_links_' . REVIEWBIRD_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
		}

		// Public hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_star_styles' ) );
		add_shortcode( 'reviewbird_widget', array( $this, 'widget_shortcode' ) );
		add_shortcode( 'reviewbird_showcase', array( $this, 'carousel_shortcode' ) );

		// Rating override integration.
		new RatingOverride();

		// Star rating display override.
		new StarRatingDisplay();

		// WooCommerce integration (adds CusRev media to reviews REST API).
		new WooCommerce();

		// Action Scheduler integrations for async API calls.
		$health_scheduler = new HealthScheduler();
		$health_scheduler->init();

		$schema_scheduler = new SchemaScheduler();
		$schema_scheduler->init();

		// Schema markup for SEO - filter WooCommerce's structured data to add reviewbird reviews.
		$schema_markup = new SchemaMarkup();
		add_filter( 'woocommerce_structured_data_product', array( $schema_markup, 'filter_woocommerce_structured_data' ), 10, 2 );

		// Widget display on product pages (only when connected).
		add_filter( 'woocommerce_product_tabs', array( $this, 'remove_reviews_tab' ), 98 );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_product_widget' ), 14 );

		// Force reviews open on products when enabled.
		add_filter( 'comments_open', array( $this, 'maybe_force_comments_open' ), 10, 2 );
		add_filter( 'woocommerce_product_get_reviews_allowed', array( $this, 'maybe_force_reviews_allowed' ), 10, 2 );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'reviewbird',
			false,
			dirname( REVIEWBIRD_PLUGIN_BASENAME ) . '/i18n/languages'
		);
	}

	/**
	 * Enqueue public scripts and styles.
	 */
	public function enqueue_public_scripts() {
		// Check if widget is enabled AND store can show widget.
		if ( ! reviewbird_can_show_widget() ) {
			return;
		}

		// Only load on product pages or pages with shortcode.
		global $post;
		if ( ! is_product() && ( ! $post || ! has_shortcode( $post->post_content, 'reviewbird_widget' ) ) ) {
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
	 * Enqueue star rating styles on WooCommerce pages.
	 */
	public function enqueue_star_styles() {
		// Only load when store is connected.
		if ( ! reviewbird_get_store_id() ) {
			return;
		}

		// Load on WooCommerce pages (products, shop, archives).
		if ( ! is_woocommerce() ) {
			return;
		}

		wp_enqueue_style(
			'reviewbird-stars',
			REVIEWBIRD_PLUGIN_URL . 'assets/css/reviewbird-stars.css',
			array(),
			$this->version
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
			array(
				'product_id' => null,
			),
			$atts,
			'reviewbird_widget'
		);

		return reviewbird_render_widget( $atts['product_id'] );
	}

	/**
	 * Handle reviewbird carousel shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function carousel_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => '',
			),
			$atts,
			'reviewbird_showcase'
		);

		$carousel_id = $atts['id'];
		$store_id    = get_option( 'reviewbird_store_id' );

		if ( empty( $carousel_id ) ) {
			return '<!-- reviewbird Showcase: Missing showcase ID -->';
		}

		if ( empty( $store_id ) ) {
			return '<!-- reviewbird Showcase: Store not connected -->';
		}

		$this->enqueue_carousel_script();

		$locale = $this->get_language_code();

		return sprintf(
			'<div data-reviewbird-carousel data-store-id="%s" data-carousel-id="%s" data-locale="%s"></div>',
			esc_attr( $store_id ),
			esc_attr( $carousel_id ),
			esc_attr( $locale )
		);
	}

	/**
	 * Enqueue carousel script and configuration.
	 */
	private function enqueue_carousel_script() {
		if ( self::$carousel_script_enqueued ) {
			return;
		}

		wp_enqueue_script(
			'reviewbird-carousel',
			reviewbird_get_api_url() . '/build/review-carousel.js',
			array(),
			$this->version,
			true
		);

		wp_localize_script(
			'reviewbird-carousel',
			'reviewbirdCarouselConfig',
			array(
				'apiUrl' => reviewbird_get_api_url(),
			)
		);

		self::$carousel_script_enqueued = true;
	}

	/**
	 * Get the two-letter language code from the current locale.
	 *
	 * @return string Two-letter language code (e.g., 'en' from 'en_US').
	 */
	private function get_language_code() {
		return strtolower( substr( get_locale(), 0, 2 ) );
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

		// Connection controller routes.
		$connection_controller = new ConnectionController();
		$connection_controller->register_routes();

		// Coupon controller routes.
		$coupon_controller = new CouponController();
		$coupon_controller->register_routes();

		// Products controller routes.
		$products_controller = new ProductsController();
		$products_controller->register_routes();
	}

	/**
	 * Remove the reviews tab when ReviewBird is connected.
	 *
	 * @param array $tabs Product tabs.
	 * @return array Modified tabs.
	 */
	public function remove_reviews_tab( array $tabs ): array {
		if ( reviewbird_can_show_widget() ) {
			unset( $tabs['reviews'] );
		}

		return $tabs;
	}

	/**
	 * Render the ReviewBird widget after product summary.
	 */
	public function render_product_widget(): void {
		if ( reviewbird_can_show_widget() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped in reviewbird_render_widget()
			echo reviewbird_render_widget();
		}
	}

	/**
	 * Force comments open on product pages when setting is enabled.
	 *
	 * @param bool $open    Whether comments are open.
	 * @param int  $post_id The post ID.
	 * @return bool Whether comments are open.
	 */
	public function maybe_force_comments_open( bool $open, int $post_id ): bool {
		if ( ! reviewbird_is_force_reviews_open() ) {
			return $open;
		}

		if ( 'product' === get_post_type( $post_id ) ) {
			return true;
		}

		return $open;
	}

	/**
	 * Force reviews allowed on products when setting is enabled.
	 *
	 * @param bool        $allowed Whether reviews are allowed.
	 * @param \WC_Product $product The product object.
	 * @return bool Whether reviews are allowed.
	 */
	public function maybe_force_reviews_allowed( bool $allowed, $product ): bool {
		if ( ! reviewbird_is_force_reviews_open() ) {
			return $allowed;
		}

		return true;
	}

	/**
	 * Add settings link to plugin action links.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=reviewbird-settings' ),
			__( 'Settings', 'reviewbird' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Enable WooCommerce authentication for reviewbird REST API endpoints.
	 *
	 * @param bool $is_request_to_wc_api Whether this is a request to WC API.
	 * @return bool
	 */
	public function enable_wc_auth_for_reviewbird_api( $is_request_to_wc_api ) {
		if ( $is_request_to_wc_api || empty( $_SERVER['REQUEST_URI'] ) ) {
			return $is_request_to_wc_api;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		return strpos( $request_uri, $rest_prefix . 'reviewbird/' ) !== false;
	}
}
