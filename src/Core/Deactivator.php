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
		self::unschedule_actions();
		self::clear_transients();
	}

	/**
	 * Unschedule all Action Scheduler jobs.
	 */
	private static function unschedule_actions(): void {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( 'reviewbird_refresh_health_status', array(), 'reviewbird' );
		as_unschedule_all_actions( 'reviewbird_refresh_schema_reviews', null, 'reviewbird' );
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
