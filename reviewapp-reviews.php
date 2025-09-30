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
 * Build an environment-specific option key.
 *
 * @param string $key Base key name.
 * @return string Environment-scoped key name.
 */
function reviewapp_get_env_option_key( $key ) {
	return sprintf( 'reviewapp_%s_%s', $key, reviewapp_get_environment() );
}

/**
 * Get the OAuth callback URL used during authorization.
 *
 * @return string Callback URL.
 */
function reviewapp_get_oauth_callback_url() {
	return add_query_arg( 'reviewapp_oauth_callback', '1', admin_url( 'admin.php?page=reviewapp-settings' ) );
}

/**
 * Determine if SSL verification should be disabled for HTTP requests.
 *
 * @return bool True to disable SSL verification, false to enable it.
 */
function reviewapp_should_disable_ssl_verify() {
	$environment = reviewapp_get_environment();
	
	// Always disable SSL verification in development/local environments
	if ( 'development' === $environment ) {
		return true;
	}
	
	// Allow override via constant
	if ( defined( 'REVIEWAPP_DISABLE_SSL_VERIFY' ) ) {
		return (bool) REVIEWAPP_DISABLE_SSL_VERIFY;
	}
	
	return false;
}

/**
 * Retrieve the stored OAuth client ID for the current environment.
 *
 * @return string Stored client ID or empty string if not set.
 */
function reviewapp_get_stored_oauth_client_id() {
	$client_id = get_option( reviewapp_get_env_option_key( 'oauth_client_id' ) );

	if ( ! $client_id ) {
		$client_id = apply_filters( 'reviewapp_oauth_client_id', '' );
	}

	return is_string( $client_id ) ? $client_id : '';
}

/**
 * Persist the OAuth client ID for the current environment.
 *
 * @param string $client_id OAuth client identifier.
 * @return void
 */
function reviewapp_store_oauth_client_id( $client_id ) {
	update_option( reviewapp_get_env_option_key( 'oauth_client_id' ), sanitize_text_field( $client_id ) );
}

/**
 * Get the OAuth client identifier. For WordPress integration, we use a single public client.
 *
 * @return string Client ID.
 */
function reviewapp_get_oauth_client_id() {
	// Use a single public OAuth client for all WordPress sites
	switch ( reviewapp_get_environment() ) {
		case 'production':
			// TODO: Configure production OAuth client ID after creating OAuth client in production ReviewApp
			return '';
		case 'staging':
			// TODO: Configure staging OAuth client ID after creating OAuth client in staging ReviewApp
			return '';
		case 'development':
		default:
			return '01999bdc-973a-71fa-9ef7-4d27f82d4348'; // Development client
	}
}

/**
 * Ensure an OAuth client identifier is available for the current environment.
 *
 * @return string|\WP_Error
 */
function reviewapp_ensure_oauth_client() {
	$client_id = reviewapp_get_oauth_client_id();

	if ( ! empty( $client_id ) ) {
		return $client_id;
	}

	$stored_client_id = reviewapp_get_stored_oauth_client_id();

	if ( ! empty( $stored_client_id ) ) {
		return $stored_client_id;
	}

	return new \WP_Error(
		'reviewapp_missing_client',
		__( 'ReviewApp OAuth client is not configured for this environment. Please contact support.', 'reviewapp-reviews' )
	);
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