<?php
/**
 * Plugin Name: ReviewApp Reviews
 * Plugin URI: https://reviewapp.com
 * Description: Connect your WooCommerce store to ReviewApp for advanced review collection and display.
 * Version: 1.0.0
 * Author: ReviewApp
 * Author URI: https://reviewapp.com
 * Text Domain: reviewapp-reviews
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
define( 'REVIEWAPP_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'REVIEWAPP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'REVIEWAPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'REVIEWAPP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Text domain for translations.
 */
define( 'REVIEWAPP_TEXT_DOMAIN', 'reviewapp-reviews' );

/**
 * ReviewApp API URLs for different environments.
 */
define( 'REVIEWAPP_API_URL_PRODUCTION', 'https://app.reviewapp.com' );
define( 'REVIEWAPP_API_URL_STAGING', 'https://staging.reviewapp.com' );
define( 'REVIEWAPP_API_URL_DEVELOPMENT', 'https://reviewapp.test' );

/**
 * ReviewApp OAuth URLs for different environments.
 */
define( 'REVIEWAPP_OAUTH_URL_PRODUCTION', 'https://app.reviewapp.com/oauth' );
define( 'REVIEWAPP_OAUTH_URL_STAGING', 'https://staging.reviewapp.com/oauth' );
define( 'REVIEWAPP_OAUTH_URL_DEVELOPMENT', 'https://reviewapp.test/oauth' );

/**
 * OAuth Client IDs for different environments.
 */
define( 'REVIEWAPP_OAUTH_CLIENT_ID_PRODUCTION', '' ); // To be defined later
define( 'REVIEWAPP_OAUTH_CLIENT_ID_STAGING', '' ); // To be defined later  
define( 'REVIEWAPP_OAUTH_CLIENT_ID_DEVELOPMENT', '4fb4de08-afa9-4944-bbc0-2cc696ecbd68' );

/**
 * Determine the current environment.
 * 
 * @return string 'production', 'staging', or 'development'
 */
function reviewapp_get_environment() {
	// Check for explicit environment constant first.
	if ( defined( 'REVIEWAPP_ENVIRONMENT' ) ) {
		return REVIEWAPP_ENVIRONMENT;
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
function reviewapp_get_api_url() {
	$environment = reviewapp_get_environment();
	
	switch ( $environment ) {
		case 'production':
			return REVIEWAPP_API_URL_PRODUCTION;
		case 'staging':
			return REVIEWAPP_API_URL_STAGING;
		case 'development':
		default:
			return REVIEWAPP_API_URL_DEVELOPMENT;
	}
}

/**
 * Get the appropriate OAuth URL based on environment.
 *
 * @return string The OAuth URL for the current environment.
 */
function reviewapp_get_oauth_url() {
	$environment = reviewapp_get_environment();
	
	switch ( $environment ) {
		case 'production':
			return REVIEWAPP_OAUTH_URL_PRODUCTION;
		case 'staging':
			return REVIEWAPP_OAUTH_URL_STAGING;
		case 'development':
		default:
			return REVIEWAPP_OAUTH_URL_DEVELOPMENT;
	}
}

/**
 * Get the appropriate OAuth client ID based on environment.
 *
 * @return string The OAuth client ID for the current environment.
 */
function reviewapp_get_oauth_client_id() {
	$environment = reviewapp_get_environment();
	
	switch ( $environment ) {
		case 'production':
			return REVIEWAPP_OAUTH_CLIENT_ID_PRODUCTION;
		case 'staging':
			return REVIEWAPP_OAUTH_CLIENT_ID_STAGING;
		case 'development':
		default:
			return REVIEWAPP_OAUTH_CLIENT_ID_DEVELOPMENT;
	}
}

/**
 * Load Composer autoloader.
 */
require_once REVIEWAPP_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * The code that runs during plugin activation.
 */
function activate_reviewapp() {
	\ReviewApp\Core\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_reviewapp() {
	\ReviewApp\Core\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_reviewapp' );
register_deactivation_hook( __FILE__, 'deactivate_reviewapp' );

/**
 * Begins execution of the plugin.
 */
function run_reviewapp() {
	$plugin = new \ReviewApp\Core\Plugin();
	$plugin->run();
}

run_reviewapp();