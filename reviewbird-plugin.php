<?php
/**
 * Plugin Name: reviewbird
 * Plugin URI: https://www.reviewbird.com
 * Description: Automated review collection that blocks spam, catches complaints, and showcases your best feedback.
 * Version: 1.0.9
 * Author: reviewbird
 * Author URI: https://reviewbird.com
 * Text Domain: reviewbird-reviews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.4
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use reviewbird\Core\Activator;
use reviewbird\Core\Deactivator;
use reviewbird\Core\Plugin;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'REVIEWBIRD_VERSION', '1.0.9' );

/**
 * Plugin directory path.
 */
define( 'REVIEWBIRD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'REVIEWBIRD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'REVIEWBIRD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * reviewbird API URLs for different environments.
 */
define( 'REVIEWBIRD_API_URL_PRODUCTION', 'https://app.reviewbird.com' );
define( 'REVIEWBIRD_API_URL_STAGING', 'https://staging.app.reviewbird.com' );
define( 'REVIEWBIRD_API_URL_DEVELOPMENT', 'https://reviewapp.test' );

/**
 * Load Composer autoloader.
 */
require_once REVIEWBIRD_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Load core functions.
 */
require_once REVIEWBIRD_PLUGIN_DIR . 'src/functions.php';

/**
 * Declare compatibility with WooCommerce features.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Begins execution of the plugin.
 */
function run_reviewbird() {
	$plugin = new Plugin();
	$plugin->run();
}

run_reviewbird();

/**
 * Initialize Plugin Update Checker for automatic updates from GitHub releases.
 */
$reviewbird_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/Objectivco/reviewbird-plugin/',
	__FILE__,
	'reviewbird'
);

// Use GitHub releases for updates.
$reviewbird_update_checker->setBranch( 'master' );
$reviewbird_update_checker->getVcsApi()->enableReleaseAssets();
