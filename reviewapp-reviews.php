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
 * reviewbird OAuth URLs for different environments.
 */
define( 'REVIEWBIRD_OAUTH_URL_PRODUCTION', 'https://app.reviewbird.com/oauth' );
define( 'REVIEWBIRD_OAUTH_URL_STAGING', 'https://6d4e16252666.ngrok-free.app/oauth' );
define( 'REVIEWBIRD_OAUTH_URL_DEVELOPMENT', 'https://reviewbird.test/oauth' );

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
 * Get the appropriate OAuth URL based on environment.
 *
 * @return string The OAuth URL for the current environment.
 */
function reviewbird_get_oauth_url() {
	$environment = reviewbird_get_environment();

	switch ( $environment ) {
		case 'production':
			return REVIEWBIRD_OAUTH_URL_PRODUCTION;
		case 'staging':
			return REVIEWBIRD_OAUTH_URL_STAGING;
		case 'development':
		default:
			return REVIEWBIRD_OAUTH_URL_DEVELOPMENT;
	}
}

/**
 * Build an environment-specific option key.
 *
 * @param string $key Base key name.
 * @return string Environment-scoped key name.
 */
function reviewbird_get_env_option_key( $key ) {
	return sprintf( 'reviewbird_%s_%s', $key, reviewbird_get_environment() );
}

/**
 * Get the OAuth callback URL used during authorization.
 *
 * @return string Callback URL.
 */
function reviewbird_get_oauth_callback_url() {
	return add_query_arg( 'reviewbird_oauth_callback', '1', admin_url( 'admin.php?page=reviewbird-settings' ) );
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
 * Retrieve the stored OAuth client ID for the current environment.
 *
 * @return string Stored client ID or empty string if not set.
 */
function reviewbird_get_stored_oauth_client_id() {
	$client_id = get_option( reviewbird_get_env_option_key( 'oauth_client_id' ) );

	if ( ! $client_id ) {
		$client_id = apply_filters( 'reviewbird_oauth_client_id', '' );
	}

	return is_string( $client_id ) ? $client_id : '';
}

/**
 * Persist the OAuth client ID and domain for the current environment.
 *
 * @param string $client_id OAuth client identifier.
 * @return void
 */
function reviewbird_store_oauth_client_id( $client_id ) {
	update_option( reviewbird_get_env_option_key( 'oauth_client_id' ), sanitize_text_field( $client_id ) );
	update_option( reviewbird_get_env_option_key( 'oauth_client_domain' ), get_site_url() );
}

/**
 * Get the stored OAuth client domain for the current environment.
 *
 * @return string Stored domain or empty string if not set.
 */
function reviewbird_get_stored_oauth_client_domain() {
	return get_option( reviewbird_get_env_option_key( 'oauth_client_domain' ), '' );
}

/**
 * Check if the stored OAuth client ID is valid for the current domain.
 *
 * @return bool True if valid, false if domain has changed or not set.
 */
function reviewbird_is_oauth_client_valid() {
	$stored_domain = reviewbird_get_stored_oauth_client_domain();
	$current_domain = get_site_url();

	return ! empty( $stored_domain ) && $stored_domain === $current_domain;
}

/**
 * Clear the stored OAuth client ID and domain (used when domain changes).
 *
 * @return void
 */
function reviewbird_clear_oauth_client() {
	delete_option( reviewbird_get_env_option_key( 'oauth_client_id' ) );
	delete_option( reviewbird_get_env_option_key( 'oauth_client_domain' ) );
}

/**
 * Get the OAuth client identifier by checking storage or registering a new client.
 *
 * @return string Client ID.
 */
function reviewbird_get_oauth_client_id() {
	$stored_client_id = reviewbird_get_stored_oauth_client_id();

	if ( ! empty( $stored_client_id ) ) {
		return $stored_client_id;
	}

	return '';
}

/**
 * Register this WordPress site as an OAuth client with reviewbird.
 *
 * @return string|\WP_Error Client ID on success, WP_Error on failure.
 */
function reviewbird_register_oauth_client() {
	$current_user = wp_get_current_user();

	if ( ! $current_user || ! $current_user->user_email ) {
		return new \WP_Error(
			'reviewbird_no_user',
			__( 'No user email found. Please ensure you are logged in.', 'reviewbird-reviews' )
		);
	}

	// Get WooCommerce country if available
	$country = null;
	if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) ) {
		$wc_countries = WC()->countries;
		if ( $wc_countries ) {
			$country = $wc_countries->get_base_country();
		}
	}

	$response = wp_remote_post(
		reviewbird_get_api_url() . '/api/oauth/register-client',
		array(
			'body' => wp_json_encode( array(
				'domain' => get_site_url(),
				'site_name' => get_bloginfo( 'name' ),
				'admin_email' => $current_user->user_email,
				'timezone' => wp_timezone_string(),
				'language' => get_locale(),
				'country' => $country,
			) ),
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'timeout' => 30,
			'sslverify' => ! reviewbird_should_disable_ssl_verify(),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new \WP_Error(
			'reviewbird_register_failed',
			sprintf(
				__( 'Failed to register OAuth client: %s', 'reviewbird-reviews' ),
				$response->get_error_message()
			)
		);
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( $response_code === 404 && isset( $data['message'] ) ) {
		return new \WP_Error(
			'reviewbird_account_not_found',
			sprintf(
				__( 'reviewbird account not found. Please sign up at %s with the email %s first, then try connecting again.', 'reviewbird-reviews' ),
				reviewbird_get_api_url(),
				$current_user->user_email
			)
		);
	}

	if ( $response_code >= 400 ) {
		return new \WP_Error(
			'reviewbird_register_error',
			isset( $data['message'] ) ? $data['message'] : __( 'Failed to register OAuth client.', 'reviewbird-reviews' )
		);
	}

	if ( empty( $data['client_id'] ) ) {
		return new \WP_Error(
			'reviewbird_invalid_response',
			__( 'Invalid response from reviewbird API.', 'reviewbird-reviews' )
		);
	}

	reviewbird_store_oauth_client_id( $data['client_id'] );

	return $data['client_id'];
}

/**
 * Ensure an OAuth client identifier is available for the current environment.
 *
 * @return string|\WP_Error Client ID on success, WP_Error on failure.
 */
function reviewbird_ensure_oauth_client() {
	$stored_client_id = reviewbird_get_stored_oauth_client_id();

	if ( ! empty( $stored_client_id ) ) {
		return $stored_client_id;
	}

	return reviewbird_register_oauth_client();
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
