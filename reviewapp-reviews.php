<?php
/**
 * Plugin Name: reviewbird Reviews
 * Plugin URI: https://reviewbird.com
 * Description: Connect your WooCommerce store to reviewbird for advanced review collection and display.
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
 * Text domain for translations.
 */
define( 'REVIEWBIRD_TEXT_DOMAIN', 'reviewbird-reviews' );

/**
 * reviewbird API URLs for different environments.
 */
define( 'REVIEWBIRD_API_URL_PRODUCTION', 'https://app.reviewbird.com' );
define( 'REVIEWBIRD_API_URL_STAGING', 'https://6d4e16252666.ngrok-free.app' );
define( 'REVIEWBIRD_API_URL_DEVELOPMENT', 'https://reviewbird.test' );


/**
 * Determine the current environment.
 *
 * @return string 'production', 'staging', or 'development'
 */
function reviewbird_get_environment() {
	// Check for explicit environment constant first.
	if ( defined( 'REVIEWBIRD_ENVIRONMENT' ) ) {
		return REVIEWBIRD_ENVIRONMENT;
	}

	// Fall back to WordPress environment type.
	if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
		switch ( WP_ENVIRONMENT_TYPE ) {
			case 'production':
				return 'production';
			case 'staging':
				return 'staging';
			case 'development':
			case 'local':
			default:
				return 'development';
		}
	}

	// Default to development for safety.
	return 'development';
}

/**
 * Get the appropriate API URL based on environment.
 *
 * @return string The API URL for the current environment.
 */
function reviewbird_get_api_url() {
	$environment = reviewbird_get_environment();

	switch ( $environment ) {
		case 'production':
			return REVIEWBIRD_API_URL_PRODUCTION;
		case 'staging':
			return REVIEWBIRD_API_URL_STAGING;
		case 'development':
		default:
			return REVIEWBIRD_API_URL_DEVELOPMENT;
	}
}

/**
 * Determine if SSL verification should be disabled for HTTP requests.
 *
 * @return bool True to disable SSL verification, false to enable it.
 */
function reviewbird_should_disable_ssl_verify() {
	$environment = reviewbird_get_environment();

	// Always disable SSL verification in development/local environments
	if ( 'development' === $environment ) {
		return true;
	}

	// Allow override via constant
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
 * Begins execution of the plugin.
 */
function run_reviewbird() {
	$plugin = new \reviewbird\Core\Plugin();
	$plugin->run();
}

run_reviewbird();
