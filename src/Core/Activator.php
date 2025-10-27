<?php
/**
 * Plugin activation handler.
 *
 * @package reviewbird
 */

namespace reviewbird\Core;

use reviewbird\Api\Client;

/**
 * Plugin activation handler.
 */
class Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		// No default options needed - using opinionated defaults

		// Configure media domains if store is already connected.
		$store_token = get_option( 'reviewbird_store_token' );
		if ( $store_token ) {
			self::configure_media_domains();
		}

		// Create OAuth state table if needed.
		self::create_oauth_table();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Configure media domains for video playback.
	 */
	private static function configure_media_domains() {
		$domains = array( parse_url( get_site_url(), PHP_URL_HOST ) );

		// Add environment-specific domains.
		$environment = reviewbird_get_environment();
		if ( 'staging' === $environment ) {
			$domains[] = 'staging.' . parse_url( get_site_url(), PHP_URL_HOST );
		}

		$api_client = new Client();
		$result = $api_client->configure_media_domains( array_unique( $domains ) );

		if ( is_wp_error( $result ) ) {
			error_log( 'reviewbird: Failed to configure media domains during activation: ' . $result->get_error_message() );
		}
	}

	/**
	 * Create OAuth state table for secure OAuth flows.
	 */
	private static function create_oauth_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reviewbird_oauth_states';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			state varchar(255) NOT NULL,
			redirect_uri text NOT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			code_verifier varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY state (state),
			KEY expires_at (expires_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}