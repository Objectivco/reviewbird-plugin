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

	require_once dirname( __DIR__ ) . '/src/functions.php';

	final class WidgetEnqueueTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();

			$GLOBALS['reviewbird_test_options']     = array();
			$GLOBALS['reviewbird_test_is_product']  = false;
			$GLOBALS['reviewbird_test_has_block']   = array();
			$GLOBALS['post']                        = null;
		}

		protected function tearDown(): void {
			$GLOBALS['reviewbird_test_options']    = array();
			$GLOBALS['reviewbird_test_is_product'] = false;
			$GLOBALS['reviewbird_test_has_block']  = array();
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
			$post                = new \stdClass();
			$post->post_content  = '[vc_row][vc_column][product_page id="3932"][/vc_column][/vc_row]';
			$GLOBALS['post']     = $post;

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
	}
}
