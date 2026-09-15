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

	class SettingsJsonResponse extends \RuntimeException {
		public $data;
		public function __construct( $data, $code ) {
			parent::__construct( '', $code );
			$this->data = $data;
		}
	}

	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
	function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $value ) ); }
	function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
	function wp_verify_nonce( $value ) { return 'valid-nonce' === $value; }
	function current_user_can( $capability ) { return $GLOBALS['gcr_test_admin'] && 'manage_options' === $capability; }
	function update_option( $key, $value ) {
		if ( $GLOBALS['gcr_test_write_fails'] ) { return false; }
		$GLOBALS['gcr_test_options'][ $key ] = $value;
		return true;
	}
	function get_option( $key ) { return $GLOBALS['gcr_test_options'][ $key ] ?? false; }
	function wp_send_json_error( $data, $code ) { throw new SettingsJsonResponse( $data, $code ); }
	function wp_send_json_success( $data ) { throw new SettingsJsonResponse( $data, 200 ); }

	require_once dirname( __DIR__ ) . '/src/Admin/Settings.php';
}

namespace reviewbird\Tests {

	use PHPUnit\Framework\TestCase;
	use reviewbird\Admin\Settings;

	final class SettingsTest extends TestCase {


		public function test_prompt_save_requires_admin_nonce_and_valid_text(): void {
			$valid = array( 'nonce' => 'valid-nonce', 'heading' => 'Heading', 'message' => 'Message', 'yes_label' => 'Yes', 'no_label' => 'No' );
			$cases = array(
				array( $valid, true, false, 200 ),
				array( array_merge( $valid, array( 'nonce' => 'wrong' ) ), true, false, 403 ),
				array( array_merge( $valid, array( 'nonce' => array() ) ), true, false, 403 ),
				array( $valid, false, false, 403 ),
				array( array_merge( $valid, array( 'heading' => array() ) ), true, false, 400 ),
				array( array_merge( $valid, array( 'heading' => str_repeat( 'a', 161 ) ) ), true, false, 400 ),
				array( array_merge( $valid, array( 'message' => '  ' ) ), true, false, 400 ),
				array( $valid, true, true, 500 ),
			);
			try {
				foreach ( $cases as [ $request, $admin, $write_fails, $expected ] ) {
					$_POST = $request;
					$GLOBALS['gcr_test_admin'] = $admin;
					$GLOBALS['gcr_test_write_fails'] = $write_fails;
					$GLOBALS['gcr_test_options'] = array();
					try {
						( new Settings() )->handle_gcr_prompt_update();
						self::fail( 'The handler must send a JSON response.' );
					} catch ( \reviewbird\Admin\SettingsJsonResponse $response ) {
						self::assertSame( $expected, $response->getCode() );
						if ( 200 === $expected ) {
							self::assertSame( 'Heading', $GLOBALS['gcr_test_options']['reviewbird_google_customer_reviews_prompt']['heading'] );
							self::assertCount( 4, $response->data['prompt'] );
						} else {
							self::assertSame( array(), $GLOBALS['gcr_test_options'] );
						}
					}
				}
			} finally {
				$_POST = array();
			}
		}

		public function test_local_prompt_toggle_validates_access_value_and_write(): void {
			$valid = array( 'nonce' => 'valid-nonce', 'setting' => 'enable_gcr_prompt', 'value' => '0' );
			$cases = array(
				array( $valid, true, false, null, 200 ),
				array( array_merge( $valid, array( 'value' => '1' ) ), true, false, null, 200 ),
				array( array_merge( $valid, array( 'nonce' => 'wrong' ) ), true, false, null, 403 ),
				array( $valid, false, false, null, 403 ),
				array( array_merge( $valid, array( 'value' => 'yes' ) ), true, false, null, 400 ),
				array( array_merge( $valid, array( 'value' => array() ) ), true, false, null, 400 ),
				array( array_merge( $valid, array( 'setting' => array() ) ), true, false, null, 400 ),
				array( $valid, true, true, null, 500 ),
				array( $valid, true, true, 'no', 200 ),
			);
			try {
				foreach ( $cases as [ $request, $admin, $write_fails, $existing, $expected ] ) {
					$_POST = $request;
					$GLOBALS['gcr_test_admin'] = $admin;
					$GLOBALS['gcr_test_write_fails'] = $write_fails;
					$GLOBALS['gcr_test_options'] = null === $existing ? array() : array( 'reviewbird_enable_gcr_prompt' => $existing );
					try {
						( new Settings() )->handle_setting_update();
						self::fail( 'The handler must send a JSON response.' );
					} catch ( \reviewbird\Admin\SettingsJsonResponse $response ) {
						self::assertSame( $expected, $response->getCode() );
						if ( 200 === $expected ) {
							self::assertSame( '1' === $request['value'], $response->data['value'] );
							self::assertSame( '1' === $request['value'] ? 'yes' : 'no', $GLOBALS['gcr_test_options']['reviewbird_enable_gcr_prompt'] );
						} else {
							self::assertSame( array(), $GLOBALS['gcr_test_options'] );
						}
					}
				}
			} finally {
				$_POST = array();
			}
		}

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
