<?php
/**
 * WooCommerce integration tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This isolated test defines small WordPress function stubs.

namespace reviewbird\Integration {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	function add_filter() {}

	function add_action( $hook, $callback, $priority, $accepted_args ) {
		$GLOBALS['reviewbird_test_actions'][ $hook ] = array( $callback, $priority, $accepted_args );
	}

	function is_wc_endpoint_url( $endpoint ) {
		return 'view-order' === $endpoint && $GLOBALS['reviewbird_test_is_view_order'];
	}

	function is_order_received_page() {
		return $GLOBALS['reviewbird_test_is_order_received'];
	}

	function reviewbird_can_show_widget() {
		return true;
	}

	function wc_get_product() {
		return $GLOBALS['reviewbird_test_review_product'];
	}

	function apply_filters( $hook, $value ) {
		return $value;
	}

	function comments_open( $product_id ) {
		$GLOBALS['reviewbird_test_comments_product_id'] = $product_id;
		return true;
	}

	function esc_url( $url ) {
		return $url;
	}

	function esc_html__( $text ) {
		return $text;
	}

	require_once dirname( __DIR__ ) . '/src/Integration/WooCommerce.php';
}

namespace reviewbird\Tests {

	use PHPUnit\Framework\TestCase;
	use reviewbird\Integration\WooCommerce;

	final class WooCommerceTest extends TestCase {

		public function test_adds_account_order_review_link(): void {
			$GLOBALS['reviewbird_test_is_view_order']     = false;
			$GLOBALS['reviewbird_test_is_order_received'] = true;
			$_GET['view']                                 = 'true';

			$product = $this->getMockBuilder( \stdClass::class )
				->addMethods( array( 'get_permalink', 'is_visible' ) )
				->getMock();
			$product->method( 'get_permalink' )->willReturn( 'https://store.test/product/mug/?attribute_color=blue' );
			$product->method( 'is_visible' )->willReturn( true );

			$review_product = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_id' ) )->getMock();
			$review_product->method( 'get_id' )->willReturn( 21 );
			$order = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'has_status' ) )->getMock();
			$order->method( 'has_status' )->willReturnCallback(
				function ( $status ) {
					return $GLOBALS['reviewbird_test_order_status'] === $status;
				}
			);
			$GLOBALS['reviewbird_test_order_status'] = 'completed';

			$item = $this->getMockBuilder( \stdClass::class )
				->addMethods( array( 'get_id', 'get_order', 'get_product', 'get_product_id' ) )
				->getMock();
			$item->method( 'get_id' )->willReturn( 7 );
			$item->method( 'get_order' )->willReturn( $order );
			$item->method( 'get_product' )->willReturn( $product );
			$item->method( 'get_product_id' )->willReturn( 21 );

			$GLOBALS['reviewbird_test_review_product'] = $review_product;
			$integration                                = new WooCommerce();
			$expected                                   = '<p><a class="reviewbird-leave-review woocommerce-button button" href="https://store.test/product/mug/?attribute_color=blue#write_review">Leave a review</a></p>';

			self::assertSame( 4, $GLOBALS['reviewbird_test_actions']['woocommerce_order_item_meta_end'][2] );
			self::assertSame( array( $integration, 'add_checkoutwc_account_review_link' ), $GLOBALS['reviewbird_test_actions']['cfw_order_item_after_data'][0] );
			self::assertSame( 1, $GLOBALS['reviewbird_test_actions']['cfw_order_item_after_data'][2] );

			ob_start();
			$integration->add_checkoutwc_account_review_link( $item );
			$output = ob_get_clean();

			self::assertSame( $expected, $output );
			self::assertSame( 21, $GLOBALS['reviewbird_test_comments_product_id'] );

			$GLOBALS['reviewbird_test_is_view_order']     = true;
			$GLOBALS['reviewbird_test_is_order_received'] = false;
			unset( $_GET['view'] );
			ob_start();
			$integration->add_account_review_link( 7, $item, $order );
			self::assertSame( $expected, ob_get_clean() );

			$GLOBALS['reviewbird_test_is_view_order']     = false;
			$GLOBALS['reviewbird_test_is_order_received'] = true;
			ob_start();
			$integration->add_account_review_link( 7, $item, $order );
			self::assertSame( '', ob_get_clean() );

			$_GET['view'] = 'true';
			$GLOBALS['reviewbird_test_order_status'] = 'processing';
			ob_start();
			$integration->add_account_review_link( 7, $item, $order );
			self::assertSame( '', ob_get_clean() );

			$GLOBALS['reviewbird_test_order_status'] = 'completed';
			ob_start();
			$integration->add_account_review_link( 7, $item, $order, true );
			self::assertSame( '', ob_get_clean() );
			unset( $_GET['view'] );
		}
	}
}
