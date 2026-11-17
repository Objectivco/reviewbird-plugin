<?php
/**
 * Widget, showcase, badge, and rating sync tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- PHPUnit tests use the fake WordPress in bootstrap.php.

use PHPUnit\Framework\TestCase;

final class WidgetEnqueueTest extends TestCase {

	protected function setUp(): void {
		WP_Fake::reset();
		WP_Fake::$options  = array(
			'reviewbird_enable_widget' => 'yes',
			'reviewbird_store_status'  => array( 'status' => 'healthy', 'has_active_subscription' => true ),
		);
		WP_Fake::$response = array(
			'response' => array( 'code' => 200 ),
			'body'     => '{"statistics":{"total_reviews":259,"average_rating":4.8,"rating_distribution":{"5":228,"4":19,"3":6,"2":3,"1":3}}}',
		);
		foreach ( array( 3932, 4100, 4101 ) as $id ) {
			WP_Fake::product( $id );
		}
	}

	public function test_showcase_locale_keeps_regional_language_variants(): void {
		WP_Fake::$options['reviewbird_store_id'] = 148;
		foreach ( array( 'pt_BR' => 'pt-BR', 'pt_PT' => 'pt-PT', 'zh_TW' => 'zh-TW', 'de_DE_formal' => 'de-DE', 'pt_PT_ao90' => 'pt-PT', 'ja' => 'ja' ) as $locale => $expected ) {
			WP_Fake::$context['locale'] = $locale;
			self::assertStringContainsString( 'data-locale="' . $expected . '"', reviewbird_carousel_shortcode( array( 'id' => 'showcase' ) ) );
		}
	}

	public function test_badge_uses_connected_store_and_enqueues_once_for_multiple_placements(): void {
		WP_Fake::$options['reviewbird_store_id'] = 37;
		self::assertSame( array(), WP_Fake::$script_calls );
		$expected = '<div data-reviewbird-store-badge data-store-id="37" data-layout="horizontal" data-theme="light" data-alignment="left"></div>';
		self::assertSame( $expected, reviewbird_badge_shortcode( array() ) );
		self::assertSame( $expected, reviewbird_badge_shortcode( array( 'store_id' => '999' ) ) );
		self::assertSame( array( 'reviewbird-badge' ), WP_Fake::$script_calls );
		self::assertSame( array( REVIEWBIRD_API_URL_PRODUCTION . '/build/review-badge.js', true ), WP_Fake::$scripts['reviewbird-badge'] );

		// A removed script can be queued again, but a printed script cannot.
		unset( WP_Fake::$scripts['reviewbird-badge'] );
		reviewbird_badge_shortcode( array() );
		self::assertCount( 2, WP_Fake::$script_calls );
		unset( WP_Fake::$scripts['reviewbird-badge'] );
		WP_Fake::$printed['reviewbird-badge'] = true;
		self::assertSame( $expected, reviewbird_badge_shortcode( array() ) );
		self::assertCount( 2, WP_Fake::$script_calls );
	}

	public function test_badge_accepts_supported_appearance_attributes(): void {
		WP_Fake::$options['reviewbird_store_id'] = 148;
		foreach ( array( 'left', 'center', 'right' ) as $alignment ) {
			self::assertSame(
				'<div data-reviewbird-store-badge data-store-id="148" data-layout="seal" data-theme="dark" data-alignment="' . $alignment . '"></div>',
				reviewbird_badge_shortcode( array( 'layout' => 'seal', 'theme' => 'dark', 'alignment' => $alignment ) )
			);
		}
	}

	public function test_badge_replaces_invalid_attributes_with_defaults(): void {
		WP_Fake::$options['reviewbird_store_id'] = 148;
		self::assertSame(
			'<div data-reviewbird-store-badge data-store-id="148" data-layout="horizontal" data-theme="light" data-alignment="left"></div>',
			reviewbird_badge_shortcode( array( 'layout' => '"><script>alert(1)</script>', 'theme' => 'unknown', 'alignment' => array( 'center' ), 'onclick' => 'alert(1)' ) )
		);
	}

	public function test_badge_does_not_render_or_load_script_without_a_connected_store(): void {
		WP_Fake::$options['reviewbird_store_id'] = '';
		self::assertSame( '<!-- Reviewbird Badge: Store not connected -->', reviewbird_badge_shortcode( array( 'store_id' => '999' ) ) );
		self::assertSame( array(), WP_Fake::$scripts );
		self::assertSame( array(), WP_Fake::$script_calls );
	}

	public function test_store_id_falls_back_to_health_status(): void {
		WP_Fake::$options['reviewbird_store_id']     = '';
		WP_Fake::$options['reviewbird_store_status'] = array( 'store_id' => 37 );

		self::assertSame( 37, reviewbird_get_store_id() );
	}

	public function test_store_id_option_wins_over_health_status(): void {
		WP_Fake::$options['reviewbird_store_id']     = 12;
		WP_Fake::$options['reviewbird_store_status'] = array( 'store_id' => 37 );

		self::assertSame( 12, reviewbird_get_store_id() );
	}

	// Rendering state lasts for the request, so each test below uses its own product IDs.
	public function test_each_product_renders_one_widget_across_hooks_blocks_and_shortcodes(): void {
		WP_Fake::connect( 7 );
		WP_Fake::$context['queried_id'] = 21;
		$GLOBALS['product']             = WP_Fake::product( 21 );
		WP_Fake::product( 22 );

		$html = reviewbird_render_block_product_widget( '<div>Embedded product</div>', array( 'attrs' => array( 'productId' => 21 ) ), (object) array( 'context' => array() ) );
		self::assertStringContainsString( 'reviewbird-widget-container-21', $html );
		self::assertStringNotContainsString( 'id="reviews"', $html, 'An embedded product must not claim the main product review anchor.' );
		ob_start();
		reviewbird_render_product_widget();
		self::assertSame( '', ob_get_clean(), 'The classic fallback must not duplicate a block widget.' );
		self::assertSame( '', reviewbird_widget_shortcode( array( 'product_id' => 21 ) ), 'A shortcode must not duplicate the product widget.' );
		self::assertStringContainsString( 'reviewbird-widget-container-22', reviewbird_widget_shortcode( array( 'product_id' => 22 ) ) );
		self::assertSame( array( 'if(typeof reviewbird !== "undefined") reviewbird.init();' ), WP_Fake::$inline['reviewbird-widget'] );
	}

	public function test_main_product_widget_keeps_the_native_reviews_anchor(): void {
		WP_Fake::connect( 7 );
		WP_Fake::$context['is_product'] = true;
		WP_Fake::$context['queried_id'] = 23;
		$GLOBALS['product']             = WP_Fake::product( 23 );
		WP_Fake::product( 24 );

		ob_start();
		reviewbird_render_product_widget();
		reviewbird_render_product_widget();
		$html = ob_get_clean();
		self::assertSame( 1, substr_count( $html, 'reviewbird-widget-container-23' ), 'The two native hooks must output one widget.' );
		self::assertSame( 1, substr_count( $html, 'id="reviews"' ), 'The main product needs one native review link target.' );
		$html = reviewbird_widget_shortcode( array( 'product_id' => 24 ) );
		self::assertStringContainsString( 'reviewbird-widget-container-24', $html );
		self::assertStringNotContainsString( 'id="reviews"', $html, 'Another product on the main product page must not repeat the review anchor.' );
	}

	public function test_ignores_a_non_product_global_before_the_product_loop_starts(): void {
		WP_Fake::connect( 7 );
		foreach ( array( 'product-slug', new stdClass() ) as $product ) {
			$GLOBALS['product'] = $product;
			self::assertSame( '', reviewbird_widget_shortcode( array() ) );
			self::assertSame( '', reviewbird_render_widget() );
		}
	}

	public function test_widget_setting_controls_automatic_placement_only(): void {
		WP_Fake::connect( 7 );
		WP_Fake::$options['reviewbird_enable_widget'] = 'no';
		WP_Fake::$context['is_product']               = true;
		WP_Fake::$context['queried_id']               = 25;
		$GLOBALS['product']                           = WP_Fake::product( 25 );

		self::assertSame( '', reviewbird_widget_shortcode( array() ) );
		$html = reviewbird_render_widget();
		self::assertStringContainsString( 'reviewbird-widget-container-25', $html, 'Themes can still call the template function directly.' );
		self::assertStringNotContainsString( 'id="reviews"', $html, 'The native reviews tab keeps its anchor while the widget is off.' );
	}

	public function test_sync_skips_api_when_rating_meta_exists(): void {
		WP_Fake::$meta[3932]['_reviewbird_reviews_count'] = 259;

		reviewbird_sync_product_rating( 3932 );

		self::assertSame( array(), WP_Fake::$requests );
	}

	public function test_sync_writes_rating_meta_when_missing(): void {
		WP_Fake::$options['reviewbird_store_id'] = 37;

		reviewbird_sync_product_rating( 4100 );

		self::assertSame( 4.8, WP_Fake::$meta[4100]['_reviewbird_avg_stars'] );
		self::assertSame( 259, WP_Fake::$meta[4100]['_reviewbird_reviews_count'] );
		self::assertSame( array( 1 => 3, 2 => 3, 3 => 6, 4 => 19, 5 => 228 ), WP_Fake::$meta[4100]['_reviewbird_rating_counts'] );
	}

	public function test_zero_ratings_do_not_fetch_or_restore_old_api_totals(): void {
		WP_Fake::$options['reviewbird_store_id'] = 37;
		WP_Fake::$meta[4101]                     = array(
			'_reviewbird_avg_stars'     => '0',
			'_reviewbird_reviews_count' => '0',
			'_reviewbird_rating_counts' => array_fill( 1, 5, 0 ),
		);
		reviewbird_sync_product_rating( 4101 );
		self::assertSame( array(), WP_Fake::$requests );
		self::assertSame( '0', get_post_meta( 4101, '_reviewbird_reviews_count', true ) );
	}
}
