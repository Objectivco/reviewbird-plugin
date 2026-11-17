<?php
/**
 * Widget enqueue helper tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This isolated test defines small WordPress function stubs.

namespace {

	use PHPUnit\Framework\TestCase;

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	if ( ! function_exists( 'has_filter' ) ) {
		function has_filter( $hook ) { return false; }
	}

	if ( ! function_exists( 'absint' ) ) {
		function absint( $value ) {
			return abs( (int) $value );
		}
	}

	if ( ! function_exists( 'is_product' ) ) {
		function is_product() {
			return ! empty( $GLOBALS['reviewbird_test_is_product'] );
		}
	}

	if ( ! function_exists( 'has_shortcode' ) ) {
		function has_shortcode( $content, $tag ) {
			return is_string( $content ) && false !== strpos( $content, '[' . $tag );
		}
	}

	if ( ! function_exists( 'has_block' ) ) {
		function has_block( $block_name, $post = null ) {
			return ! empty( $GLOBALS['reviewbird_test_has_block'][ $block_name ] );
		}
	}

	if ( ! function_exists( 'is_user_logged_in' ) ) {
		function is_user_logged_in() {
			return false;
		}
	}

	if ( ! function_exists( 'wp_enqueue_script' ) ) {
		function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
			$GLOBALS['reviewbird_test_enqueued_scripts'][ $handle ] = array( $src, $in_footer );
			$GLOBALS['reviewbird_test_enqueue_calls'][] = $handle;
		}
	}

	if ( ! function_exists( 'wp_localize_script' ) ) {
		function wp_localize_script( $handle, $object_name, $data ) {
			$GLOBALS['reviewbird_test_localized'][ $handle ] = array( $object_name, $data );
		}
	}

	function wp_script_is( $handle, $status = 'enqueued' ) {
		return 'done' === $status
			? ! empty( $GLOBALS['reviewbird_test_printed_scripts'][ $handle ] )
			: isset( $GLOBALS['reviewbird_test_enqueued_scripts'][ $handle ] );
	}

	function shortcode_atts( $defaults, $atts ) {
		return array_merge( $defaults, array_intersect_key( $atts, $defaults ) );
	}

	function wc_get_product( $id ) {
		return new class( $id ) {
			private $id;
			public function __construct( $id ) { $this->id = $id; }
			public function get_id() { return $this->id; }
			public function get_status() { return 'publish'; }
		};
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $value ) {
			return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $post_id, $key, $single = false ) {
			return $GLOBALS['reviewbird_test_post_meta'][ $post_id ][ $key ] ?? '';
		}
	}

	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $post_id, $key, $value ) {
			$GLOBALS['reviewbird_test_post_meta'][ $post_id ][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $key ) {
			return $GLOBALS['reviewbird_test_transients'][ $key ] ?? false;
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $key, $value, $expiration = 0 ) {
			$GLOBALS['reviewbird_test_transients'][ $key ] = $value;
			return true;
		}
	}

	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) {
			return $thing instanceof \WP_Error;
		}
	}

	if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
		function wp_remote_retrieve_response_code( $response ) {
			return $response['response']['code'] ?? 0;
		}
	}

	if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
		function wp_remote_retrieve_body( $response ) {
			return $response['body'] ?? '';
		}
	}

	if ( ! function_exists( 'wp_remote_request' ) ) {
		function wp_remote_request( $url, $args ) {
			$GLOBALS['reviewbird_test_request'] = array( $url, $args );

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $GLOBALS['reviewbird_test_response'],
			);
		}
	}

	if ( ! function_exists( 'home_url' ) ) {
		function home_url() { return 'https://store.test'; }
	}

	if ( ! function_exists( 'wp_get_environment_type' ) ) {
		function wp_get_environment_type() { return 'production'; }
	}

	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $id ) { return 'product'; }
	}

	if ( ! function_exists( 'get_locale' ) ) {
		function get_locale() {
			return $GLOBALS['reviewbird_test_showcase_locale'] ?? 'en_US';
		}
	}

	require_once dirname( __DIR__ ) . '/src/functions.php';
	require_once dirname( __DIR__ ) . '/src/hooks.php';

	final class WidgetEnqueueTest extends TestCase {

		public function test_showcase_locale_keeps_regional_language_variants(): void {
			foreach ( array( 'pt_BR' => 'pt-BR', 'pt_PT' => 'pt-PT', 'zh_TW' => 'zh-TW', 'de_DE_formal' => 'de-DE', 'pt_PT_ao90' => 'pt-PT', 'ja' => 'ja' ) as $locale => $expected ) {
				$GLOBALS['reviewbird_test_showcase_locale'] = $locale;
				self::assertStringContainsString( 'data-locale="' . $expected . '"', reviewbird_carousel_shortcode( array( 'id' => 'showcase' ) ) );
			}
			unset( $GLOBALS['reviewbird_test_showcase_locale'] );
		}


		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['reviewbird_test_options']    = array(
				'reviewbird_enable_widget' => 'yes',
				'reviewbird_store_status' => array( 'status' => 'healthy', 'has_active_subscription' => true ),
			);
			$GLOBALS['reviewbird_test_is_product'] = false;
			$GLOBALS['reviewbird_test_has_block']  = array();
			$GLOBALS['reviewbird_test_post_meta']   = array();
			$GLOBALS['reviewbird_test_request']     = null;
			$GLOBALS['reviewbird_test_response']    = '{"statistics":{"total_reviews":259,"average_rating":4.8,"rating_distribution":{"5":228,"4":19,"3":6,"2":3,"1":3}}}';
			$GLOBALS['reviewbird_test_transients']  = array();
			$GLOBALS['reviewbird_test_enqueued_scripts'] = array();
			$GLOBALS['reviewbird_test_enqueue_calls'] = array();
			$GLOBALS['reviewbird_test_printed_scripts'] = array();
			$GLOBALS['post']                        = null;
		}

		protected function tearDown(): void {
			$GLOBALS['reviewbird_test_options']    = array();
			$GLOBALS['reviewbird_test_is_product'] = false;
			$GLOBALS['reviewbird_test_has_block']  = array();
			$GLOBALS['reviewbird_test_post_meta']  = array();
			$GLOBALS['post']                       = null;

			parent::tearDown();
		}

		public function test_badge_uses_connected_store_and_enqueues_once_for_multiple_placements(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_id'] = 37;
			self::assertSame( array(), $GLOBALS['reviewbird_test_enqueue_calls'] );
			$expected = '<div data-reviewbird-store-badge data-store-id="37" data-layout="horizontal" data-theme="light" data-alignment="left"></div>';
			self::assertSame( $expected, reviewbird_badge_shortcode( array() ) );
			self::assertSame( $expected, reviewbird_badge_shortcode( array( 'store_id' => '999' ) ) );
			self::assertSame( array( 'reviewbird-badge' ), $GLOBALS['reviewbird_test_enqueue_calls'] );
			self::assertSame(
				array( 'https://api.reviewbird.test/build/review-badge.js', true ),
				$GLOBALS['reviewbird_test_enqueued_scripts']['reviewbird-badge']
			);

			// A removed script can be queued again, but a printed script cannot.
			unset( $GLOBALS['reviewbird_test_enqueued_scripts']['reviewbird-badge'] );
			reviewbird_badge_shortcode( array() );
			self::assertCount( 2, $GLOBALS['reviewbird_test_enqueue_calls'] );
			unset( $GLOBALS['reviewbird_test_enqueued_scripts']['reviewbird-badge'] );
			$GLOBALS['reviewbird_test_printed_scripts']['reviewbird-badge'] = true;
			self::assertSame( $expected, reviewbird_badge_shortcode( array() ) );
			self::assertCount( 2, $GLOBALS['reviewbird_test_enqueue_calls'] );
		}

		public function test_badge_accepts_supported_appearance_attributes(): void {
			foreach ( array( 'left', 'center', 'right' ) as $alignment ) {
				self::assertSame(
					'<div data-reviewbird-store-badge data-store-id="148" data-layout="seal" data-theme="dark" data-alignment="' . $alignment . '"></div>',
					reviewbird_badge_shortcode( array( 'layout' => 'seal', 'theme' => 'dark', 'alignment' => $alignment ) )
				);
			}
		}

		public function test_badge_replaces_invalid_attributes_with_defaults(): void {
			self::assertSame(
				'<div data-reviewbird-store-badge data-store-id="148" data-layout="horizontal" data-theme="light" data-alignment="left"></div>',
				reviewbird_badge_shortcode( array( 'layout' => '"><script>alert(1)</script>', 'theme' => 'unknown', 'alignment' => array( 'center' ), 'onclick' => 'alert(1)' ) )
			);
		}

		public function test_badge_does_not_render_or_load_script_without_a_connected_store(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_id'] = '';
			self::assertSame( '<!-- Reviewbird Badge: Store not connected -->', reviewbird_badge_shortcode( array( 'store_id' => '999' ) ) );
			self::assertSame( array(), $GLOBALS['reviewbird_test_enqueued_scripts'] );
			self::assertSame( array(), $GLOBALS['reviewbird_test_enqueue_calls'] );
		}

		public function test_store_id_falls_back_to_health_status(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_id']     = '';
			$GLOBALS['reviewbird_test_options']['reviewbird_store_status'] = array(
				'store_id' => 37,
			);

			self::assertSame( 37, reviewbird_get_store_id() );
		}

		public function test_store_id_option_wins_over_health_status(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_id']     = 12;
			$GLOBALS['reviewbird_test_options']['reviewbird_store_status'] = array(
				'store_id' => 37,
			);

			self::assertSame( 12, reviewbird_get_store_id() );
		}

		public function test_enqueues_widget_on_product_page_shortcode_pages(): void {
			$post               = new \stdClass();
			$post->post_content = '[vc_row][vc_column][product_page id="3932"][/vc_column][/vc_row]';
			$GLOBALS['post']    = $post;

			self::assertTrue( reviewbird_page_should_enqueue_widget() );
		}

		public function test_enqueues_widget_on_single_product_block_pages(): void {
			$post               = new \stdClass();
			$post->post_content = '<!-- wp:woocommerce/single-product -->';
			$GLOBALS['post']    = $post;
			$GLOBALS['reviewbird_test_has_block']['woocommerce/single-product'] = true;

			self::assertTrue( reviewbird_page_should_enqueue_widget() );
		}

		public function test_does_not_enqueue_widget_on_plain_pages(): void {
			$post               = new \stdClass();
			$post->post_content = '[vc_row][vc_column]Hello[/vc_column][/vc_row]';
			$GLOBALS['post']    = $post;

			self::assertFalse( reviewbird_page_should_enqueue_widget() );
		}

		public function test_sync_skips_api_when_rating_meta_exists(): void {
			$GLOBALS['reviewbird_test_post_meta'][3932]['_reviewbird_reviews_count'] = 259;

			reviewbird_sync_product_rating( 3932 );

			self::assertNull( $GLOBALS['reviewbird_test_request'] );
		}

		public function test_sync_writes_rating_meta_when_missing(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_id'] = 37;

			reviewbird_sync_product_rating( 4100 );

			self::assertSame( 4.8, $GLOBALS['reviewbird_test_post_meta'][4100]['_reviewbird_avg_stars'] );
			self::assertSame( 259, $GLOBALS['reviewbird_test_post_meta'][4100]['_reviewbird_reviews_count'] );
			self::assertSame(
				array( 1 => 3, 2 => 3, 3 => 6, 4 => 19, 5 => 228 ),
				$GLOBALS['reviewbird_test_post_meta'][4100]['_reviewbird_rating_counts']
			);
		}

		public function test_zero_ratings_do_not_fetch_or_restore_old_api_totals(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_id'] = 37;
			$GLOBALS['reviewbird_test_post_meta'][4101] = array(
				'_reviewbird_avg_stars' => '0',
				'_reviewbird_reviews_count' => '0',
				'_reviewbird_rating_counts' => array_fill( 1, 5, 0 ),
			);
			reviewbird_sync_product_rating( 4101 );
			self::assertNull( $GLOBALS['reviewbird_test_request'] );
			self::assertSame( '0', get_post_meta( 4101, '_reviewbird_reviews_count', true ) );
		}
	}
}
