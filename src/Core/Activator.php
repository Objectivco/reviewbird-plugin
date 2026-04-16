<?php
/**
 * Fired during plugin activation.
 *
 * @package reviewbird
 */

namespace reviewbird\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles setup when the plugin is activated.
 */
class Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate(): void {
		update_option( 'reviewbird_do_activation_redirect', 'yes' );
	}
}
