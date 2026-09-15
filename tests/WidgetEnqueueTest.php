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

	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES );
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

	if ( ! function_exists( 'wp_remote_get' ) ) {
		function wp_remote_get( $url, $args = array() ) {
			$GLOBALS['reviewbird_test_remote_get'] = array( $url, $args );

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '{"statistics":{"total_reviews":259,"average_rating":4.8,"rating_distribution":{"5":228,"4":19,"3":6,"2":3,"1":3}}}',
			);
		}
	}

	require_once dirname( __DIR__ ) . '/src/functions.php';

	final class WidgetEnqueueTest extends TestCase {

		public function test_showcase_locale_keeps_regional_language_variants(): void {
			require_once dirname( __DIR__ ) . '/src/Core/Plugin.php';
			$plugin = ( new \ReflectionClass( \reviewbird\Core\Plugin::class ) )->newInstanceWithoutConstructor();
			foreach ( array( 'pt_BR' => 'pt-BR', 'pt_PT' => 'pt-PT', 'zh_TW' => 'zh-TW', 'de_DE_formal' => 'de-DE', 'pt_PT_ao90' => 'pt-PT', 'ja' => 'ja' ) as $locale => $expected ) {
				$GLOBALS['reviewbird_test_showcase_locale'] = $locale;
				self::assertStringContainsString( 'data-locale="' . $expected . '"', $plugin->carousel_shortcode( array( 'id' => 'showcase' ) ) );
			}
			unset( $GLOBALS['reviewbird_test_showcase_locale'] );
		}


		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['reviewbird_test_options']    = array();
			$GLOBALS['reviewbird_test_is_product'] = false;
			$GLOBALS['reviewbird_test_has_block']  = array();
			$GLOBALS['reviewbird_test_post_meta']   = array();
			$GLOBALS['reviewbird_test_remote_get']  = null;
			$GLOBALS['reviewbird_test_transients']  = array();
			$GLOBALS['reviewbird_test_enqueued_scripts'] = array();
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

			self::assertNull( $GLOBALS['reviewbird_test_remote_get'] );
		}

		public function test_sync_writes_rating_meta_when_missing(): void {
			$GLOBALS['reviewbird_test_options']['reviewbird_store_id'] = 37;

			reviewbird_sync_product_rating( 4100 );

			self::assertSame( 4.8, $GLOBALS['reviewbird_test_post_meta'][4100]['_reviewbird_avg_stars'] );
			self::assertSame( 259, $GLOBALS['reviewbird_test_post_meta'][4100]['_reviewbird_reviews_count'] );
			self::assertSame(
				array( 5 => 228, 4 => 19, 3 => 6, 2 => 3, 1 => 3 ),
				$GLOBALS['reviewbird_test_post_meta'][4100]['_reviewbird_rating_counts']
			);
		}
	}
}

namespace reviewbird\Core {
	function get_locale() {
		return $GLOBALS['reviewbird_test_showcase_locale'] ?? 'en_US';
	}
}
