<?php
/**
 * Plugin Name: ReviewBop Reviews
 * Plugin URI: https://reviewbop.com
 * Description: Connect your WooCommerce store to ReviewBop for advanced review collection and display.
 * Version: 1.0.0
 * Author: ReviewBop
 * Author URI: https://reviewbop.com
 * Text Domain: reviewbop-reviews
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
define( 'REVIEWBOP_VERSION', '1.0.0' );

/**
 * Plugin directory path.
 */
define( 'REVIEWBOP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'REVIEWBOP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'REVIEWBOP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Text domain for translations.
 */
define( 'REVIEWBOP_TEXT_DOMAIN', 'reviewbop-reviews' );

/**
 * ReviewBop API URLs for different environments.
 */
define( 'REVIEWBOP_API_URL_PRODUCTION', 'https://app.reviewbop.com' );
define( 'REVIEWBOP_API_URL_STAGING', 'https://c96323be0adb.ngrok-free.app' );
define( 'REVIEWBOP_API_URL_DEVELOPMENT', 'https://reviewbop.test' );

/**
 * ReviewBop OAuth URLs for different environments.
 */
define( 'REVIEWBOP_OAUTH_URL_PRODUCTION', 'https://app.reviewbop.com/oauth' );
define( 'REVIEWBOP_OAUTH_URL_STAGING', 'https://c96323be0adb.ngrok-free.app/oauth' );
define( 'REVIEWBOP_OAUTH_URL_DEVELOPMENT', 'https://reviewbop.test/oauth' );

/**
 * Determine the current environment.
 *
 * @return string 'production', 'staging', or 'development'
 */
function reviewbop_get_environment() {
	// Check for explicit environment constant first.
	if ( defined( 'REVIEWBOP_ENVIRONMENT' ) ) {
		return REVIEWBOP_ENVIRONMENT;
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
function reviewbop_get_api_url() {
	$environment = reviewbop_get_environment();

	switch ( $environment ) {
		case 'production':
			return REVIEWBOP_API_URL_PRODUCTION;
		case 'staging':
			return REVIEWBOP_API_URL_STAGING;
		case 'development':
		default:
			return REVIEWBOP_API_URL_DEVELOPMENT;
	}
}

/**
 * Get the appropriate OAuth URL based on environment.
 *
 * @return string The OAuth URL for the current environment.
 */
function reviewbop_get_oauth_url() {
	$environment = reviewbop_get_environment();

	switch ( $environment ) {
		case 'production':
			return REVIEWBOP_OAUTH_URL_PRODUCTION;
		case 'staging':
			return REVIEWBOP_OAUTH_URL_STAGING;
		case 'development':
		default:
			return REVIEWBOP_OAUTH_URL_DEVELOPMENT;
	}
}

/**
 * Build an environment-specific option key.
 *
 * @param string $key Base key name.
 * @return string Environment-scoped key name.
 */
function reviewbop_get_env_option_key( $key ) {
	return sprintf( 'reviewbop_%s_%s', $key, reviewbop_get_environment() );
}

/**
 * Get the OAuth callback URL used during authorization.
 *
 * @return string Callback URL.
 */
function reviewbop_get_oauth_callback_url() {
	return add_query_arg( 'reviewbop_oauth_callback', '1', admin_url( 'admin.php?page=reviewbop-settings' ) );
}

/**
 * Determine if SSL verification should be disabled for HTTP requests.
 *
 * @return bool True to disable SSL verification, false to enable it.
 */
function reviewbop_should_disable_ssl_verify() {
	$environment = reviewbop_get_environment();

	// Always disable SSL verification in development/local environments
	if ( 'development' === $environment ) {
		return true;
	}

	// Allow override via constant
	if ( defined( 'REVIEWBOP_DISABLE_SSL_VERIFY' ) ) {
		return (bool) REVIEWBOP_DISABLE_SSL_VERIFY;
	}

	return false;
}

/**
 * Retrieve the stored OAuth client ID for the current environment.
 *
 * @return string Stored client ID or empty string if not set.
 */
function reviewbop_get_stored_oauth_client_id() {
	$client_id = get_option( reviewbop_get_env_option_key( 'oauth_client_id' ) );

	if ( ! $client_id ) {
		$client_id = apply_filters( 'reviewbop_oauth_client_id', '' );
	}

	return is_string( $client_id ) ? $client_id : '';
}

/**
 * Persist the OAuth client ID for the current environment.
 *
 * @param string $client_id OAuth client identifier.
 * @return void
 */
function reviewbop_store_oauth_client_id( $client_id ) {
	update_option( reviewbop_get_env_option_key( 'oauth_client_id' ), sanitize_text_field( $client_id ) );
}

/**
 * Get the OAuth client identifier by checking storage or registering a new client.
 *
 * @return string Client ID.
 */
function reviewbop_get_oauth_client_id() {
	$stored_client_id = reviewbop_get_stored_oauth_client_id();

	if ( ! empty( $stored_client_id ) ) {
		return $stored_client_id;
	}

	return '';
}

/**
 * Register this WordPress site as an OAuth client with ReviewBop.
 *
 * @return string|\WP_Error Client ID on success, WP_Error on failure.
 */
function reviewbop_register_oauth_client() {
	$current_user = wp_get_current_user();

	if ( ! $current_user || ! $current_user->user_email ) {
		return new \WP_Error(
			'reviewbop_no_user',
			__( 'No user email found. Please ensure you are logged in.', 'reviewbop-reviews' )
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
		reviewbop_get_api_url() . '/api/oauth/register-client',
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
			'sslverify' => ! reviewbop_should_disable_ssl_verify(),
		)
	);

	if ( is_wp_error( $response ) ) {
		return new \WP_Error(
			'reviewbop_register_failed',
			sprintf(
				__( 'Failed to register OAuth client: %s', 'reviewbop-reviews' ),
				$response->get_error_message()
			)
		);
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( $response_code === 404 && isset( $data['message'] ) ) {
		return new \WP_Error(
			'reviewbop_account_not_found',
			sprintf(
				__( 'ReviewBop account not found. Please sign up at %s with the email %s first, then try connecting again.', 'reviewbop-reviews' ),
				reviewbop_get_api_url(),
				$current_user->user_email
			)
		);
	}

	if ( $response_code >= 400 ) {
		return new \WP_Error(
			'reviewbop_register_error',
			isset( $data['message'] ) ? $data['message'] : __( 'Failed to register OAuth client.', 'reviewbop-reviews' )
		);
	}

	if ( empty( $data['client_id'] ) ) {
		return new \WP_Error(
			'reviewbop_invalid_response',
			__( 'Invalid response from ReviewBop API.', 'reviewbop-reviews' )
		);
	}

	reviewbop_store_oauth_client_id( $data['client_id'] );

	return $data['client_id'];
}

/**
 * Ensure an OAuth client identifier is available for the current environment.
 *
 * @return string|\WP_Error Client ID on success, WP_Error on failure.
 */
function reviewbop_ensure_oauth_client() {
	$stored_client_id = reviewbop_get_stored_oauth_client_id();

	if ( ! empty( $stored_client_id ) ) {
		return $stored_client_id;
	}

	return reviewbop_register_oauth_client();
}

/**
 * Load Composer autoloader.
 */
require_once REVIEWBOP_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * The code that runs during plugin activation.
 */
function activate_reviewbop() {
	\ReviewBop\Core\Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_reviewbop() {
	\ReviewBop\Core\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_reviewbop' );
register_deactivation_hook( __FILE__, 'deactivate_reviewbop' );

/**
 * Begins execution of the plugin.
 */
function run_reviewbop() {
	$plugin = new \ReviewBop\Core\Plugin();
	$plugin->run();
}

run_reviewbop();
