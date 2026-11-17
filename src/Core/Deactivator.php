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
	 *
	 * @param bool $network_wide Whether this is a network deactivation.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 0,
					'network_id' => get_current_network_id(),
				)
			) as $site_id ) {
				switch_to_blog( $site_id );
				try {
					self::deactivate();
				} finally {
					restore_current_blog();
				}
			}
			return;
		}
		wp_clear_scheduled_hook( \reviewbird\Integration\HealthScheduler::CLEANUP_HOOK );
		if ( class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( 'reviewbird_refresh_health_status' );
			as_unschedule_all_actions( 'reviewbird_refresh_schema_reviews' );
			as_unschedule_all_actions( 'reviewbird_sync_product_rating' );
			as_unschedule_all_actions( \reviewbird\Integration\HealthScheduler::CLEANUP_HOOK );
		}
		delete_transient( 'reviewbird_oauth_error' );
		delete_transient( 'reviewbird_oauth_success' );
		delete_transient( 'reviewbird_star_color' );
		delete_option( 'reviewbird_preview_check_lock' );
	}
}
