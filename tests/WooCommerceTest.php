<?php
/**
 * WooCommerce integration tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- PHPUnit tests use the fake WordPress in bootstrap.php.

use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'WooCommerce' ) ) {
	class WooCommerce {}
}

final class WooCommerceTest extends TestCase {

	protected function setUp(): void {
		WP_Fake::reset();
		WP_Fake::connect();
		WP_Fake::$context['is_product'] = true;
		WP_Fake::$context['queried_id'] = 21;
		// Register the plugin's hooks the way it does on plugins_loaded.
		reviewbird_run();
	}

		public function test_exposes_cusrev_title_photos_and_videos(): void {
		WP_Fake::$comment_meta[7] = array(
			'cr_rev_title'         => 'Love it',
			'ivole_review_image2' => array( 11, 99 ),
			'ivole_review_video2' => array( 12 ),
		);
		WP_Fake::$attachments = array(
			11 => 'https://store.test/photo.jpg',
			12 => 'https://store.test/video.mp4',
		);
					$review = (object) array( 'comment_ID' => 7 );
		$response = new WP_REST_Response( array( 'id' => 7, 'review' => 'Great product!' ), 200 );

		$result = reviewbird_add_review_data_to_rest_response( $response, $review );

		self::assertSame( $response, $result );
		self::assertSame( 'Love it', $result->data['title'] );
		self::assertSame( 'Great product!', $result->data['review'] );
		self::assertSame( array(
			array( 'type' => 'image', 'url' => 'https://store.test/photo.jpg' ),
			array( 'type' => 'video', 'url' => 'https://store.test/video.mp4' ),
		), $result->data['media'] );
	}

	public function test_keeps_an_existing_review_title(): void {
		WP_Fake::$comment_meta[7]['cr_rev_title'] = 'CusRev title';
		$response = new WP_REST_Response( array( 'title' => 'Existing title' ), 200 );
		$result = reviewbird_add_review_data_to_rest_response( $response, (object) array( 'comment_ID' => 7 ) );

		self::assertSame( 'Existing title', $result->data['title'] );
	}

	public function test_plain_reviews_do_not_get_a_cusrev_title(): void {
		$response = new WP_REST_Response( array( 'id' => 7 ), 200 );
		$result = reviewbird_add_review_data_to_rest_response( $response, (object) array( 'comment_ID' => 7 ) );

		self::assertArrayNotHasKey( 'title', $result->data );
		self::assertSame( array(), $result->data['media'] );
	}

	public function test_captures_the_locale_only_when_an_order_is_created(): void {
		self::assertSame( 10, has_action( 'woocommerce_new_order', 'reviewbird_save_order_locale' ) );
		self::assertFalse( has_action( 'woocommerce_before_order_object_save' ) );
		self::assertFalse( has_action( 'woocommerce_checkout_order_created' ) );
	}

	public function test_order_and_review_normalization_runs_after_wcml_translates_responses(): void {
		self::assertSame( 100, has_filter( 'woocommerce_rest_prepare_shop_order_object', 'reviewbird_add_locale_to_order_response' ) );
		self::assertSame( 100, has_filter( 'woocommerce_rest_prepare_product_review', 'reviewbird_add_review_data_to_rest_response' ) );
	}

	public function test_exposes_widget_setting_in_woocommerce_system_status(): void {
					reviewbird_register_rest_routes();
		$field = WP_Fake::$rest_fields['system_status']['reviewbird_widget_enabled'];

		self::assertSame( 'reviewbird_is_widget_enabled', $field['get_callback'] );
		self::assertSame( 'boolean', $field['schema']['type'] );
	}

	public function test_removes_embedded_reviews_without_parent_context(): void {
					$reviews = array( 'blockName' => 'woocommerce/product-reviews', 'innerBlocks' => array( array( 'blockName' => 'woocommerce/product-review-template' ) ) );
		$group = array( 'blockName' => 'core/group', 'innerBlocks' => array( $reviews ), 'innerContent' => array( '<div>', null, '</div>' ) );
		$block = array( 'blockName' => 'woocommerce/single-product', 'innerBlocks' => array(
			array( 'blockName' => 'core/group', 'innerBlocks' => array( $group ) ),
			array( 'blockName' => 'core/query', 'innerBlocks' => array( $reviews ) ),
			array( 'blockName' => 'woocommerce/product-collection', 'innerBlocks' => array( $reviews ) ),
		) );
		$result = reviewbird_remove_embedded_product_reviews( $block );
		$nested_group = $result['innerBlocks'][0]['innerBlocks'][0];
		self::assertSame( 10, has_filter( 'render_block_data', 'reviewbird_remove_embedded_product_reviews' ) );
		self::assertNull( $nested_group['innerBlocks'][0]['blockName'] );
		self::assertSame( array(), $nested_group['innerBlocks'][0]['innerBlocks'] );
		self::assertSame( array(), $nested_group['innerBlocks'][0]['innerContent'] );
		self::assertSame( $group['innerContent'], $nested_group['innerContent'] );
		self::assertSame( $block['innerBlocks'][1], $result['innerBlocks'][1] );
		self::assertSame( $block['innerBlocks'][2], $result['innerBlocks'][2] );
		self::assertSame( $reviews, reviewbird_remove_embedded_product_reviews( $reviews ) );
		self::assertSame( $group, reviewbird_remove_embedded_product_reviews( $group ) );
		WP_Fake::$options['reviewbird_enable_widget'] = 'no';
		self::assertSame( $block, reviewbird_remove_embedded_product_reviews( $block ) );
	}

	public function test_suppresses_default_woocommerce_review_output_and_query(): void {
					// WP_Comment_Query defaults the type vars to ''.
		$query = (object) array( 'query_vars' => array( 'post_id' => 21, 'type' => '', 'type__in' => '', 'type__not_in' => '', 'count' => false ), 'found_comments' => 8, 'max_num_pages' => 2 );
		$reviews_block = array( 'blockName' => 'woocommerce/product-reviews' );
		$other_block   = array( 'blockName' => 'core/paragraph' );
		self::assertSame( array( 'reviewbird_suppress_product_reviews_block', 3 ), WP_Fake::$hooks['pre_render_block'][10][0] );
		self::assertSame( array( 'reviewbird_suppress_product_review_queries', 2 ), WP_Fake::$hooks['comments_pre_query'][10][0] );
		self::assertSame( '', reviewbird_suppress_product_reviews_block( null, $reviews_block ) );
		WP_Fake::$context['is_product'] = false;
		self::assertSame( '', reviewbird_suppress_product_reviews_block( null, $reviews_block, (object) array( 'context' => array( 'singleProduct' => true ) ) ) );
		self::assertNull( reviewbird_suppress_product_reviews_block( null, $reviews_block ) );
		WP_Fake::$context['is_product'] = true;
		self::assertNull( reviewbird_suppress_product_reviews_block( null, $other_block ) );
		self::assertSame( array(), reviewbird_suppress_product_review_queries( null, $query ) );
		self::assertSame( 0, $query->found_comments );
		$query->query_vars['count'] = true;
		self::assertSame( 0, reviewbird_suppress_product_review_queries( null, $query ) );
		self::assertSame( 9, reviewbird_suppress_product_review_queries( 9, $query ) );
		$query->query_vars['type'] = 'note';
		self::assertNull( reviewbird_suppress_product_review_queries( null, $query ) );
		$query->query_vars['type'] = 'review';
		$query->query_vars['post_id'] = 99;
		self::assertNull( reviewbird_suppress_product_review_queries( null, $query ) );
		$query->query_vars['post_id'] = 21;
		$query->query_vars['type__in'] = array( 'review' );
		self::assertSame( 0, reviewbird_suppress_product_review_queries( null, $query ) );
		$query->query_vars['type__in'] = 'review,note';
		self::assertNull( reviewbird_suppress_product_review_queries( null, $query ) );
		$query->query_vars['type__in'] = '';
		$query->query_vars['type__not_in'] = 'pings';
		self::assertNull( reviewbird_suppress_product_review_queries( null, $query ) );
		$query->query_vars['type__not_in'] = '';

		WP_Fake::$options['reviewbird_enable_widget'] = 'no';

		self::assertNull( reviewbird_suppress_product_reviews_block( null, $reviews_block ) );
		self::assertNull( reviewbird_suppress_product_review_queries( null, $query ) );

		WP_Fake::$options['reviewbird_enable_widget'] = 'yes';
		WP_Fake::$context['is_product']      = false;

		self::assertNull( reviewbird_suppress_product_review_queries( null, $query ) );
	}

	public function test_adds_account_order_review_link(): void {
		WP_Fake::$context['view_order']     = false;
		WP_Fake::$context['order_received'] = true;
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
				return $GLOBALS['order_status'] === $status;
			}
		);
		$GLOBALS['order_status'] = 'completed';

		$item = $this->getMockBuilder( \stdClass::class )
			->addMethods( array( 'get_id', 'get_order', 'get_product', 'get_product_id' ) )
			->getMock();
		$item->method( 'get_id' )->willReturn( 7 );
		$item->method( 'get_order' )->willReturn( $order );
		$item->method( 'get_product' )->willReturn( $product );
		$item->method( 'get_product_id' )->willReturn( 21 );

		WP_Fake::$products[21] = $review_product;
		$expected                                   = '<p><a class="reviewbird-leave-review woocommerce-button button" href="https://store.test/product/mug/?attribute_color=blue#write_review">Leave a review</a></p>';

		self::assertSame( array( 'reviewbird_add_account_review_link', 4 ), WP_Fake::$hooks['woocommerce_order_item_meta_end'][10][0] );
		self::assertSame( array( 'reviewbird_add_checkoutwc_account_review_link', 1 ), WP_Fake::$hooks['cfw_order_item_after_data'][10][0] );

		ob_start();
		reviewbird_add_checkoutwc_account_review_link( $item );
		$output = ob_get_clean();

		self::assertSame( $expected, $output );
		self::assertSame( 21, WP_Fake::$context['comments_checked'] );

		WP_Fake::$context['view_order']     = true;
		WP_Fake::$context['order_received'] = false;
		unset( $_GET['view'] );
		ob_start();
		reviewbird_add_account_review_link( 7, $item, $order );
		self::assertSame( $expected, ob_get_clean() );

		WP_Fake::$context['view_order']     = false;
		WP_Fake::$context['order_received'] = true;
		ob_start();
		reviewbird_add_account_review_link( 7, $item, $order );
		self::assertSame( '', ob_get_clean() );

		$_GET['view'] = 'true';
		$GLOBALS['order_status'] = 'processing';
		ob_start();
		reviewbird_add_account_review_link( 7, $item, $order );
		self::assertSame( '', ob_get_clean() );

		$GLOBALS['order_status'] = 'completed';
		ob_start();
		reviewbird_add_account_review_link( 7, $item, $order, true );
		self::assertSame( '', ob_get_clean() );
		unset( $_GET['view'] );
	}
}
