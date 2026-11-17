<?php
/**
 * Plugin Name: Reviewbird
 * Plugin URI: https://reviewbird.com
 * Description: Automated review collection for WooCommerce that blocks spam, catches complaints, and showcases your best feedback.
 * Version: 1.3.3
 * Author: Reviewbird
 * Text Domain: reviewbird
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 11.0.1
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package reviewbird
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use reviewbird\Admin\Settings;
use reviewbird\Integration\GoogleCustomerReviews;
use reviewbird\Integration\StarRatingDisplay;
use reviewbird\Integration\WPML;

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'REVIEWBIRD_VERSION', '1.3.3' );
define( 'REVIEWBIRD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REVIEWBIRD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'REVIEWBIRD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'REVIEWBIRD_API_URL_PRODUCTION', 'https://app.reviewbird.com' );
define( 'REVIEWBIRD_API_URL_STAGING', 'https://staging.app.reviewbird.com' );
define( 'REVIEWBIRD_API_URL_DEVELOPMENT', 'https://reviewapp.test' );

require_once REVIEWBIRD_PLUGIN_DIR . 'vendor/autoload.php';
require_once REVIEWBIRD_PLUGIN_DIR . 'src/functions.php';
require_once REVIEWBIRD_PLUGIN_DIR . 'src/hooks.php';
require_once REVIEWBIRD_PLUGIN_DIR . 'src/api.php';
require_once REVIEWBIRD_PLUGIN_DIR . 'src/scheduler.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, 'reviewbird_activate' );
register_deactivation_hook( __FILE__, 'reviewbird_deactivate' );

/**
 * Register the plugin's hooks when WooCommerce is active.
 */
function reviewbird_run(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	add_action(
		'init',
		static function () {
			load_plugin_textdomain( 'reviewbird', false, dirname( REVIEWBIRD_PLUGIN_BASENAME ) . '/languages' );
		}
	);

	// REST API and the WooCommerce API data Reviewbird imports.
	add_action( 'rest_api_init', 'reviewbird_register_rest_routes' );
	add_filter( 'woocommerce_rest_is_request_to_rest_api', 'reviewbird_enable_wc_auth' );
	add_filter( 'woocommerce_rest_prepare_product_review', 'reviewbird_add_review_data_to_rest_response', 100, 3 );
	add_filter( 'woocommerce_rest_prepare_shop_order_object', 'reviewbird_add_locale_to_order_response', 100, 3 );
	add_filter( 'woocommerce_rest_shop_order_object_query', 'reviewbird_query_all_languages', 10, 2 );
	add_filter( 'woocommerce_rest_product_review_query', 'reviewbird_query_all_languages', 10, 2 );
	add_action( 'woocommerce_new_order', 'reviewbird_save_order_locale', 10, 2 );

	// Admin.
	if ( is_admin() ) {
		$settings = new Settings();
		add_action( 'admin_init', 'reviewbird_activation_redirect' );
		add_action( 'admin_menu', array( $settings, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $settings, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . REVIEWBIRD_PLUGIN_BASENAME, 'reviewbird_plugin_action_links' );
	}

	// Storefront.
	add_action( 'template_redirect', 'reviewbird_is_onboarding_preview', 0 );
	add_action( 'wp_enqueue_scripts', 'reviewbird_enqueue_star_styles' );
	add_shortcode( 'reviewbird_widget', 'reviewbird_widget_shortcode' );
	add_shortcode( 'reviewbird_showcase', 'reviewbird_carousel_shortcode' );
	add_shortcode( 'reviewbird_badge', 'reviewbird_badge_shortcode' );
	add_shortcode( 'reviewbird_stars', 'reviewbird_stars_shortcode' );
	add_filter( 'render_block_woocommerce/single-product', 'reviewbird_render_block_product_widget', 10, 3 );
	add_filter( 'woocommerce_product_tabs', 'reviewbird_remove_reviews_tab', 98 );
	// On `init` so a theme's functions.php can filter reviewbird_product_widget_priority.
	add_action( 'init', 'reviewbird_register_product_widget_hook' );
	add_filter( 'comments_open', 'reviewbird_force_comments_open', 10, 2 );
	add_filter( 'woocommerce_product_get_reviews_allowed', 'reviewbird_force_reviews_allowed' );
	add_filter( 'woocommerce_structured_data_product', 'reviewbird_filter_structured_data', 10, 2 );
	add_filter( 'woocommerce_duplicate_product_exclude_meta', 'reviewbird_exclude_duplicate_meta' );

	// The widget replaces WooCommerce's reviews, so skip their output and queries.
	add_filter( 'pre_render_block', 'reviewbird_suppress_product_reviews_block', 10, 3 );
	add_filter( 'render_block_data', 'reviewbird_remove_embedded_product_reviews' );
	add_filter( 'comments_pre_query', 'reviewbird_suppress_product_review_queries', 10, 2 );
	add_action( 'woocommerce_order_item_meta_end', 'reviewbird_add_account_review_link', 10, 4 );
	add_action( 'cfw_order_item_after_data', 'reviewbird_add_checkoutwc_account_review_link' );

	// Ratings from Reviewbird replace WooCommerce's.
	add_action( 'reviewbird_sync_product_rating', 'reviewbird_sync_product_rating' );
	if ( reviewbird_can_show_widget() ) {
		foreach ( array( 'average_rating', 'review_count', 'rating_counts' ) as $property ) {
			add_filter( "woocommerce_product_get_{$property}", "reviewbird_override_{$property}", 10, 2 );
			add_filter( "woocommerce_product_variation_get_{$property}", "reviewbird_override_{$property}", 10, 2 );
		}
	}

	// Background jobs.
	add_action( 'init', 'reviewbird_schedule_health_check' );
	add_action( 'reviewbird_refresh_health_status', 'reviewbird_run_health_check' );
	add_action( 'reviewbird_cleanup_health_actions', 'reviewbird_cleanup_legacy_health_actions' );
	add_action( 'reviewbird_refresh_schema_reviews', 'reviewbird_refresh_schema_reviews' );
	add_action( 'reviewbird_rating_updated', 'reviewbird_invalidate_schema_reviews' );
	add_action( 'update_option_reviewbird_enable_schema', 'reviewbird_cancel_disabled_jobs', 10, 3 );
	add_action( 'update_option_reviewbird_enable_widget', 'reviewbird_cancel_disabled_jobs', 10, 3 );
	add_action( 'before_delete_post', 'reviewbird_cancel_deleted_product_jobs', 10, 2 );
	add_action( 'wp_trash_post', 'reviewbird_cancel_deleted_product_jobs' );

	reviewbird_register_compatibility();
	new StarRatingDisplay();
	new GoogleCustomerReviews();
	new WPML();
}
add_action( 'plugins_loaded', 'reviewbird_run' );
