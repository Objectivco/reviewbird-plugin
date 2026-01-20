<?php
/**
 * Plugin Name: reviewbird
 * Plugin URI: https://www.reviewbird.com
 * Description: Automated review collection that blocks spam, catches complaints, and showcases your best feedback.
 * Version: 1.0.0
 * Author: reviewbird
 * Author URI: https://reviewbird.com
 * Text Domain: reviewbird-reviews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// If this file is called directly, abort.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'REVIEWBIRD_VERSION', '1.0.0' );

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
 * Determine the current environment.
 *
 * @return string 'production', 'staging', or 'development'
 */
function reviewbird_get_environment(): string {
	if ( defined( 'REVIEWBIRD_ENVIRONMENT' ) ) {
		return REVIEWBIRD_ENVIRONMENT;
	}

	if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
		return 'production';
	}

	$environment_map = array(
		'production' => 'production',
		'staging'    => 'staging',
	);

	return $environment_map[ WP_ENVIRONMENT_TYPE ] ?? 'production';
}

/**
 * Get the appropriate API URL based on environment.
 *
 * @return string The API URL for the current environment.
 */
function reviewbird_get_api_url(): string {
	$api_urls = array(
		'production'  => REVIEWBIRD_API_URL_PRODUCTION,
		'staging'     => REVIEWBIRD_API_URL_STAGING,
		'development' => REVIEWBIRD_API_URL_DEVELOPMENT,
	);

	return $api_urls[ reviewbird_get_environment() ] ?? REVIEWBIRD_API_URL_DEVELOPMENT;
}

/**
 * Determine if SSL verification should be disabled for HTTP requests.
 *
 * @return bool True to disable SSL verification, false to enable it.
 */
function reviewbird_should_disable_ssl_verify(): bool {
	if ( 'development' === reviewbird_get_environment() ) {
		return true;
	}

	if ( defined( 'REVIEWBIRD_DISABLE_SSL_VERIFY' ) ) {
		return (bool) REVIEWBIRD_DISABLE_SSL_VERIFY;
	}

	return false;
}

/**
 * Load Composer autoloader.
 */
require_once REVIEWBIRD_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Load core functions.
 */
require_once REVIEWBIRD_PLUGIN_DIR . 'src/functions.php';

/**
 * The code that runs during plugin activation.
 */
function activate_reviewbird() {
	\reviewbird\Core\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_reviewbird() {
	\reviewbird\Core\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_reviewbird' );
register_deactivation_hook( __FILE__, 'deactivate_reviewbird' );

/**
 * Declare compatibility with WooCommerce features.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Begins execution of the plugin.
 */
function run_reviewbird() {
	$plugin = new \reviewbird\Core\Plugin();
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
