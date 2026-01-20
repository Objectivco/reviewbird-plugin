<?php
/**
 * Plugin activation handler.
 *
 * @package reviewbird
 */

namespace reviewbird\Core;

/**
 * Plugin activation handler.
 */
class Activator {

	/**
	 * Run activation tasks.
	 */
	public static function activate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
