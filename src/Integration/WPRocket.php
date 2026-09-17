<?php
/**
 * WP Rocket compatibility for Reviewbird assets.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep remote Reviewbird assets out of WP Rocket's local file cache.
 */
class WPRocket {

	/**
	 * Register exclusions when WP Rocket is active.
	 */
	public function __construct() {
		if ( ! defined( 'WP_ROCKET_VERSION' ) ) {
			return;
		}

		// External JS needs this filter; rocket_exclude_js only matches file paths.
		add_filter( 'rocket_minify_excluded_external_js', array( $this, 'exclude_assets' ) );
		add_filter( 'rocket_exclude_css', array( $this, 'exclude_assets' ) );
	}

	/**
	 * Exclude the Reviewbird domain from file minification and combination.
	 *
	 * @param array $excluded Existing exclusions.
	 * @return array
	 */
	public function exclude_assets( array $excluded ): array {
		$excluded[] = 'app.reviewbird.com';
		return $excluded;
	}
}
