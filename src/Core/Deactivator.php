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
		\reviewbird\Integration\Scheduler::deactivate();
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
