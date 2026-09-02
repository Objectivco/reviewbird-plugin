<?php
/**
 * Admin settings tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This isolated test defines small WordPress function stubs.

namespace reviewbird\Admin {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	function add_action( $hook, $callback ) {
		$GLOBALS['reviewbird_settings_test_actions'][ $hook ] = $callback;
	}

	function add_menu_page() {
		return 'toplevel_page_reviewbird-get-started';
	}

	function add_submenu_page() {}

	function __( $text ) {
		return $text;
	}

	function remove_all_actions( $hook ) {
		unset( $GLOBALS['reviewbird_settings_test_actions'][ $hook ] );
		$GLOBALS['reviewbird_settings_test_removed_actions'][] = $hook;
	}

	require_once dirname( __DIR__ ) . '/src/Admin/Settings.php';
}

namespace reviewbird\Tests {

	use PHPUnit\Framework\TestCase;
	use reviewbird\Admin\Settings;

	final class SettingsTest extends TestCase {

		public function test_suppresses_unrelated_notices_on_get_started_page(): void {
			$GLOBALS['reviewbird_settings_test_actions']         = array();
			$GLOBALS['reviewbird_settings_test_removed_actions'] = array();

			$settings = new Settings();
			$settings->add_admin_menu();

			self::assertSame(
				array( $settings, 'suppress_admin_notices' ),
				$GLOBALS['reviewbird_settings_test_actions']['load-toplevel_page_reviewbird-get-started']
			);

			$settings->suppress_admin_notices();

			self::assertSame(
				array( 'admin_notices', 'all_admin_notices' ),
				$GLOBALS['reviewbird_settings_test_removed_actions']
			);
			self::assertSame(
				array( $settings, 'display_oauth_notices' ),
				$GLOBALS['reviewbird_settings_test_actions']['admin_notices']
			);
		}
	}
}
