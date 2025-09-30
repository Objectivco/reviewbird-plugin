<?php
/**
 * Plugin deactivation handler.
 *
 * @package ReviewApp
 */

namespace ReviewApp\Core;

/**
 * Plugin deactivation handler.
 */
class Deactivator {

	/**
	 * Run deactivation tasks.
	 */
	public static function deactivate() {
		// Clean up scheduled events.
		wp_clear_scheduled_hook( 'reviewapp_sync_products' );
		wp_clear_scheduled_hook( 'reviewapp_sync_reviews' );
		wp_clear_scheduled_hook( 'reviewapp_cleanup_oauth_states' );

		// Clean up expired OAuth states.
		self::cleanup_oauth_states();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Clean up expired OAuth states.
	 */
	private static function cleanup_oauth_states() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reviewapp_oauth_states';
		
		// Delete expired states.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE expires_at < %s",
				current_time( 'mysql' )
			)
		);
	}
}