<?php
/**
 * Setup preview and star color tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- PHPUnit tests use the fake WordPress in bootstrap.php.

use PHPUnit\Framework\TestCase;

final class StoreConnectionTest extends TestCase {

	protected function setUp(): void {
		WP_Fake::reset();
		WP_Fake::$options['reviewbird_store_id'] = 148;
	}

	private static function respond( string $body ): void {
		WP_Fake::$response = array( 'response' => array( 'code' => 200 ), 'body' => $body );
	}

	public function test_setup_preview_requires_api_approval_and_keeps_public_billing_status(): void {
		WP_Fake::$options['reviewbird_store_status'] = array( 'status' => 'billing_required', 'has_active_subscription' => false );
		self::assertFalse( reviewbird_is_store_connected() );
		$_GET['reviewbird_setup'] = 'invalid';
		self::assertFalse( reviewbird_is_onboarding_preview() );
		$_GET['reviewbird_setup'] = str_repeat( 'a', 64 );
		self::respond( '{"onboarding_preview_allowed":true}' );
		self::assertTrue( reviewbird_is_store_connected() );
		self::assertTrue( WP_Fake::$context['no_cache'] );
		self::assertStringContainsString( 'onboarding_preview=' . str_repeat( 'a', 64 ), end( WP_Fake::$requests )[0] );
		self::assertFalse( reviewbird_get_store_status()['has_active_subscription'] );
		self::assertSame( array(), reviewbird_get_cached_product_reviews( 1 ) );
		unset( $_GET['reviewbird_setup'] );
		self::assertFalse( reviewbird_is_store_connected() );
	}

	public function test_fetches_star_color_from_current_widget_config(): void {
		self::respond( '{"widget_settings":{"star_color":"#123456"}}' );
		reviewbird_refresh_star_color();
		self::assertSame( REVIEWBIRD_API_URL_PRODUCTION . '/api/widget/148/config', end( WP_Fake::$requests )[0] );
		self::assertSame( 'https://store.test', end( WP_Fake::$requests )[1]['headers']['Origin'] );
		self::assertSame( 1, get_transient( 'reviewbird_star_color' ) );
		self::assertSame( DAY_IN_SECONDS, WP_Fake::$ttls['reviewbird_star_color'] );
		self::assertSame( '#123456', get_option( 'reviewbird_star_color' ) );
	}

	public function test_failed_color_refresh_retains_last_valid_color(): void {
		WP_Fake::$options['reviewbird_star_color'] = '#abcdef';
		foreach ( array( '{}', '{"widget_settings":{"star_color":"red;display:none"}}', '{"widget_settings":{"star_color":[]}}' ) as $body ) {
			self::respond( $body );
			reviewbird_refresh_star_color();
			self::assertSame( '#abcdef', get_option( 'reviewbird_star_color' ) );
			self::assertSame( 1, get_transient( 'reviewbird_star_color' ) );
			self::assertSame( HOUR_IN_SECONDS, WP_Fake::$ttls['reviewbird_star_color'] );
		}
	}
}
