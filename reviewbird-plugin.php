<?php
/**
 * Plugin Name: Reviewbird
 * Plugin URI: https://reviewbird.com
 * Description: Automated review collection for WooCommerce that blocks spam, catches complaints, and showcases your best feedback.
 * Version: 1.0.20
 * Author: Reviewbird
 * Text Domain: reviewbird
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 10.9.1
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package reviewbird
 */

// If this file is called directly, abort.
use Automattic\WooCommerce\Utilities\FeaturesUtil;
use reviewbird\Core\Activator;
use reviewbird\Core\Deactivator;
use reviewbird\Core\Plugin;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'REVIEWBIRD_VERSION', '1.0.20' );

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
 * Reviewbird API URLs for different environments.
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
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( FeaturesUtil::class ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Plugin activation hook.
 */
register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );

/**
 * Plugin deactivation hook.
 */
register_deactivation_hook( __FILE__, array( Deactivator::class, 'deactivate' ) );

/**
 * Begins execution of the plugin.
 */
function reviewbird_run() {
	$plugin = new Plugin();
	$plugin->run();
}

reviewbird_run();
