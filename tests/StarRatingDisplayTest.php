<?php
/**
 * Star rating configuration tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This isolated test defines small WordPress function stubs.

namespace {

	use PHPUnit\Framework\TestCase;
	use reviewbird\Integration\StarRatingDisplay;

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	define( 'REVIEWBIRD_API_URL_PRODUCTION', 'https://api.reviewbird.test' );
	define( 'REVIEWBIRD_API_URL_STAGING', 'https://staging.reviewbird.test' );
	define( 'REVIEWBIRD_API_URL_DEVELOPMENT', 'https://local.reviewbird.test' );
	define( 'REVIEWBIRD_VERSION', '1.0.0-test' );
	define( 'DAY_IN_SECONDS', 86400 );

	function get_option( $key, $default = false ) {
		if ( array_key_exists( $key, $GLOBALS['reviewbird_test_options'] ?? array() ) ) {
			return $GLOBALS['reviewbird_test_options'][ $key ];
		}

		return 'reviewbird_store_id' === $key ? 148 : $default;
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function nocache_headers() {
		$GLOBALS['reviewbird_test_no_cache'] = true;
	}

	function wp_parse_url( $url, $component ) {
		return parse_url( $url, $component );
	}

	function home_url() {
		return 'https://store.test';
	}

	function wp_remote_request( $url, $args ) {
		$GLOBALS['reviewbird_test_request'] = array( $url, $args );

		return array(
			'response' => array( 'code' => 200 ),
			'body'     => $GLOBALS['reviewbird_test_response'] ?? '{"widget_settings":{"star_color":"#123456"}}',
		);
	}

	function is_wp_error() {
		return false;
	}

	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'];
	}

	function wp_remote_retrieve_body( $response ) {
		return $response['body'];
	}

	function set_transient( $key, $value, $expiration ) {
		$GLOBALS['reviewbird_test_transient'] = array( $key, $value, $expiration );
	}

	require_once dirname( __DIR__ ) . '/src/functions.php';
	require_once dirname( __DIR__ ) . '/src/Integration/StarRatingDisplay.php';

	final class StarRatingDisplayTest extends TestCase {

		public function test_setup_preview_requires_api_approval_and_keeps_public_billing_status(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_status'] = array( 'status' => 'billing_required', 'has_active_subscription' => false );
			self::assertFalse( reviewbird_is_store_connected() );
			$_GET['reviewbird_setup'] = 'invalid';
			self::assertFalse( reviewbird_is_onboarding_preview() );
			$_GET['reviewbird_setup'] = str_repeat( 'a', 64 );
			$GLOBALS['reviewbird_test_response'] = '{"onboarding_preview_allowed":true}';
			try {
				self::assertTrue( reviewbird_is_store_connected() );
				self::assertTrue( $GLOBALS['reviewbird_test_no_cache'] );
				self::assertStringContainsString( 'onboarding_preview=' . str_repeat( 'a', 64 ), $GLOBALS['reviewbird_test_request'][0] );
				self::assertFalse( reviewbird_get_store_status()['has_active_subscription'] );
				self::assertSame( array(), reviewbird_get_cached_product_reviews( 1 ) );
			} finally {
				unset( $_GET['reviewbird_setup'], $GLOBALS['reviewbird_test_response'] );
			}
			self::assertFalse( reviewbird_is_store_connected() );
		}

		public function test_fetches_star_color_from_current_widget_config(): void {
			self::assertSame( '#123456', StarRatingDisplay::fetch_and_cache_star_color() );
			self::assertSame( 'https://api.reviewbird.test/api/widget/148/config', $GLOBALS['reviewbird_test_request'][0] );
			self::assertSame( 'https://store.test', $GLOBALS['reviewbird_test_request'][1]['headers']['Origin'] );
			self::assertSame(
				array( 'reviewbird_star_color', '#123456', DAY_IN_SECONDS ),
				$GLOBALS['reviewbird_test_transient']
			);
		}
	}
}
