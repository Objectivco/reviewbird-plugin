<?php
/**
 * Admin settings tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- PHPUnit tests use the fake WordPress in bootstrap.php.

use PHPUnit\Framework\TestCase;
use reviewbird\Admin\Settings;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		WP_Fake::reset();
	}

		public function test_setting_update_validates_access_value_and_write(): void {
		$valid = array( 'nonce' => 'nonce-reviewbird_admin_nonce', 'setting' => 'enable_widget', 'value' => '0' );
		$cases = array(
			array( $valid, true, false, null, 200 ),
			array( array_merge( $valid, array( 'value' => '1' ) ), true, false, null, 200 ),
			array( array_merge( $valid, array( 'nonce' => 'wrong' ) ), true, false, null, 403 ),
			array( array_merge( $valid, array( 'nonce' => array() ) ), true, false, null, 403 ),
			array( $valid, false, false, null, 403 ),
			array( array_merge( $valid, array( 'value' => 'yes' ) ), true, false, null, 400 ),
			array( array_merge( $valid, array( 'value' => array() ) ), true, false, null, 400 ),
			array( array_merge( $valid, array( 'setting' => array() ) ), true, false, null, 400 ),
			array( $valid, true, true, null, 500 ),
			array( $valid, true, true, 'no', 200 ),
			array( array_merge( $valid, array( 'setting' => 'enable_gcr_prompt' ) ), true, false, null, 400 ),
			array( array_merge( $valid, array( 'setting' => 'use_gcr_standard_modal' ) ), true, false, null, 400 ),
		);
		try {
			foreach ( $cases as [ $request, $admin, $write_fails, $existing, $expected ] ) {
				$_POST = $request;
				WP_Fake::$caps = $admin ? array( 'manage_options' ) : array();
				WP_Fake::$fail_writes = $write_fails;
				WP_Fake::$options = null === $existing ? array() : array( 'reviewbird_enable_widget' => $existing );
				try {
					( new Settings() )->handle_setting_update();
					self::fail( 'The handler must send a JSON response.' );
				} catch ( WP_Fake_Json_Response $response ) {
					self::assertSame( $expected, $response->getCode() );
					if ( 200 === $expected ) {
						self::assertSame( '1' === $request['value'], $response->data['value'] );
						self::assertSame( '1' === $request['value'] ? 'yes' : 'no', WP_Fake::$options[ 'reviewbird_' . $request['setting'] ] );
					} else {
						self::assertSame( array(), WP_Fake::$options );
					}
				}
			}
		} finally {
			$_POST = array();
			WP_Fake::$fail_writes = false;
		}
	}

	public function test_suppresses_unrelated_notices_on_get_started_page(): void {
		$settings = new Settings();
		$settings->add_admin_menu();

		self::assertSame(
			array( $settings, 'suppress_admin_notices' ),
			WP_Fake::$hooks['load-toplevel_page_reviewbird-get-started'][10][0][0]
		);

		$settings->suppress_admin_notices();

		self::assertSame(
			array( 'admin_notices', 'all_admin_notices' ),
			WP_Fake::$removed_hooks
		);
		self::assertSame(
			array( $settings, 'display_review_notice' ),
			WP_Fake::$hooks['admin_notices'][10][0][0]
		);
	}
}
