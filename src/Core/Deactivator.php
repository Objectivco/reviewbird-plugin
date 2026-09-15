<?php
/**
 * Fired during plugin deactivation.
 *
 * @package reviewbird
 */

namespace reviewbird\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cleanup when the plugin is deactivated.
 */
class Deactivator {

	/**
	 * Run deactivation cleanup.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( \reviewbird\Integration\HealthScheduler::CLEANUP_HOOK );
		if ( class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( 'reviewbird_refresh_health_status' );
			as_unschedule_all_actions( 'reviewbird_refresh_schema_reviews' );
			as_unschedule_all_actions( \reviewbird\Integration\HealthScheduler::CLEANUP_HOOK );
		}
		self::clear_transients();
	}

	/**
	 * Clear plugin transients.
	 */
	private static function clear_transients(): void {
		delete_transient( 'reviewbird_oauth_error' );
		delete_transient( 'reviewbird_oauth_success' );
		delete_transient( 'reviewbird_star_color' );
	}
}
