<?php
/**
 * Ratings update contract tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This isolated test defines small WordPress function stubs.

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	class WP_REST_Request {
		private $params;
		public function __construct( array $params ) { $this->params = $params; }
		public function get_param( $key ) { return $this->params[ $key ] ?? null; }
	}

	class WP_REST_Response {
		public $data;
		public $status;
		public function __construct( $data, $status ) { $this->data = $data; $this->status = $status; }
	}

	class WP_Error {
		public $code;
		public $data;
		public function __construct( $code, $message, $data ) { $this->code = $code; $this->data = $data; }
	}
	class WC_Countries {
		public function get_countries() { return array( 'NG' => 'Nigeria' ); }
	}

	function wc_delete_product_transients( $product_id ) { $GLOBALS['ratings_test_transients'][] = $product_id; }
	function wc_attributes_array_filter_variation( $attribute ) { return $attribute && $attribute->get_variation(); }
}

namespace reviewbird\Api {

	function reviewbird_find_product( $id ) { return wc_get_product( $id ); }
	function reviewbird_product_id( $id ) { return $id; }
	function reviewbird_product_meta_id( $id ) { return $id; }
	function delete_transient( $key ) {}
	function reviewbird_product_translation_ids( $id ) {
		$ids = array( $id );
		if ( function_exists( 'pll_get_post_translations' ) ) {
			foreach ( pll_get_post_translations( $id ) as $translation_id ) {
				if ( 'product' === get_post_type( $translation_id ) ) { $ids[] = (int) $translation_id; }
			}
		}
		return array_unique( $ids );
	}

	function __( $text ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function get_post_type( $id ) { return $GLOBALS['ratings_test_post_types'][ $id ] ?? 'product'; }
	function is_wp_error( $value ) { return $value instanceof \WP_Error; }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['ratings_test_meta'][ $key ] = $value; }
	function do_action( ...$args ) { $GLOBALS['ratings_test_actions'][] = $args; }
	function function_exists( $name ) {
		return 'pll_get_post_translations' === $name ? ( $GLOBALS['ratings_test_polylang'] ?? false ) : \function_exists( $name );
	}
	function pll_get_post_translations( $id ) { return $GLOBALS['ratings_test_translations'][ $id ] ?? array(); }
	function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ); }
	function wc_customer_bought_product( $email, $user_id, $product_id ) {
		$GLOBALS['ratings_test_purchase_checks'][] = array( $email, $product_id );
		return in_array( $product_id, $GLOBALS['ratings_test_purchases'][ $email ] ?? array(), true );
	}
	function wc_get_product( $id ) { return $GLOBALS['ratings_test_products'][ $id ] ?? false; }
	function wc_get_is_paid_statuses() { return array( 'processing', 'completed' ); }
	function wc_get_orders( $args ) {
		$GLOBALS['ratings_test_order_queries'][] = $args;
		return array_slice( $GLOBALS['ratings_test_orders'], ( $args['page'] - 1 ) * $args['limit'], $args['limit'] );
	}
	function switch_to_locale( $locale ) { $GLOBALS['ratings_test_locale'] = $locale; return true; }
	function restore_previous_locale() { $GLOBALS['ratings_test_locale'] = 'fi'; }
	function wp_strip_all_tags( $text ) { return strip_tags( $text ); }

	require_once dirname( __DIR__ ) . '/src/functions.php';
	require_once dirname( __DIR__ ) . '/src/Api/RatingsController.php';
}

namespace reviewbird\Tests {

	use PHPUnit\Framework\TestCase;
	use reviewbird\Api\RatingsController;

	final class RatingsControllerTest extends TestCase {

		protected function setUp(): void {
			$GLOBALS['ratings_test_polylang'] = false;
			foreach ( array( 'post_types', 'translations', 'purchases', 'purchase_checks', 'products', 'orders', 'order_queries' ) as $key ) {
				$GLOBALS[ 'ratings_test_' . $key ] = array();
			}
		}

		public function test_verifies_a_purchase_of_a_translated_product(): void {
			$GLOBALS['ratings_test_polylang'] = true;
			$GLOBALS['ratings_test_translations'][7] = array( 'en' => 7, 'fi' => 17, 'de' => 27 );
			$GLOBALS['ratings_test_purchases']['buyer@example.com'] = array( 17 );
			$result = ( new RatingsController() )->check_verified_purchase( new \WP_REST_Request( array(
				'product_id' => 7, 'customer_email' => 'buyer@example.com',
			) ) );

			self::assertSame( array( 'product_id' => 7, 'customer_email' => 'buyer@example.com', 'verified_purchase' => true ), $result->data );
			self::assertSame( array( array( 'buyer@example.com', 7 ), array( 'buyer@example.com', 17 ) ), $GLOBALS['ratings_test_purchase_checks'] );
		}

		public function test_unrelated_purchases_and_request_supplied_ids_do_not_verify_a_review(): void {
			$GLOBALS['ratings_test_polylang'] = true;
			$GLOBALS['ratings_test_translations'][7] = array( 'en' => 7, 'fi' => 17, 'invalid' => 98 );
			$GLOBALS['ratings_test_post_types'][98] = 'page';
			$GLOBALS['ratings_test_purchases']['buyer@example.com'] = array( 99 );
			$result = ( new RatingsController() )->check_verified_purchase( new \WP_REST_Request( array(
				'product_id' => 7, 'customer_email' => 'buyer@example.com', 'product_ids' => array( 99 ),
			) ) );

			self::assertFalse( $result->data['verified_purchase'] );
			self::assertSame( array( array( 'buyer@example.com', 7 ), array( 'buyer@example.com', 17 ) ), $GLOBALS['ratings_test_purchase_checks'] );
			self::assertSame( array(), $GLOBALS['ratings_test_order_queries'] );
		}

		public function test_keeps_single_product_checks_when_polylang_is_missing_or_has_no_translations(): void {
			foreach ( array( false, true ) as $polylang ) {
				$GLOBALS['ratings_test_polylang'] = $polylang;
				foreach ( array( array(), array( 7 ) ) as $purchases ) {
					$GLOBALS['ratings_test_purchase_checks'] = array();
					$GLOBALS['ratings_test_purchases']['buyer@example.com'] = $purchases;
					$result = ( new RatingsController() )->check_verified_purchase( new \WP_REST_Request( array(
						'product_id' => 7, 'customer_email' => 'buyer@example.com',
					) ) );
					self::assertSame( ! empty( $purchases ), $result->data['verified_purchase'] );
					self::assertSame( array( array( 'buyer@example.com', 7 ) ), $GLOBALS['ratings_test_purchase_checks'] );
				}
			}
		}

		public function test_reads_saved_purchase_details_after_the_first_page_of_orders(): void {
			$GLOBALS['ratings_test_polylang'] = true;
			$GLOBALS['ratings_test_translations'][7] = array( 'en' => 7, 'fi' => 17 );
			$GLOBALS['ratings_test_purchases']['buyer@example.com'] = array( 17 );
			$size = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_variation' ) )->getMock();
			$size->method( 'get_variation' )->willReturn( true );
			$material = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_variation' ) )->getMock();
			$material->method( 'get_variation' )->willReturn( false );
			$parent = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_attributes' ) )->getMock();
			$parent->method( 'get_attributes' )->with( 'edit' )->willReturn( array( 'size' => $size, 'gr%c3%b6%c3%9fe' => $size, 'material' => $material ) );
			$GLOBALS['ratings_test_products'][17] = $parent;
			$items = array();
			foreach ( array( 99, 17 ) as $parent_id ) {
				$item = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_product_id', 'get_variation_id', 'get_formatted_meta_data' ) )->getMock();
				$item->method( 'get_product_id' )->willReturn( $parent_id );
				$item->method( 'get_variation_id' )->willReturn( 99 === $parent_id ? 98 : 18 );
				$item->method( 'get_formatted_meta_data' )->with( '_', true )->willReturn( array(
					(object) array( 'key' => 'size', 'display_key' => 'Size', 'display_value' => '<p>Large</p>' ),
					(object) array( 'key' => 'größe', 'display_key' => 'Größe', 'display_value' => '<p>Groß</p>' ),
					(object) array( 'key' => 'material', 'display_key' => 'Material', 'display_value' => 'Cotton' ),
					(object) array( 'key' => 'gift_message', 'display_key' => 'Gift message', 'display_value' => 'Private gift note' ),
					(object) array( 'key' => 'engraving', 'display_key' => 'Engraving', 'display_value' => 'Private inscription' ),
					(object) array( 'key' => 'custom_field', 'display_key' => 'Custom field', 'display_value' => 'Private data' ),
					(object) array( 'key' => 'size!', 'display_key' => 'Size note', 'display_value' => 'Private size note' ),
				) );
				$items[] = $item;
			}
			$order = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_items', 'get_billing_country' ) )->getMock();
			$order->method( 'get_items' )->willReturn( $items );
			$order->method( 'get_billing_country' )->willReturn( 'NG' );
			$unrelated = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_items' ) )->getMock();
			$unrelated->method( 'get_items' )->willReturn( array( $items[0] ) );
			$GLOBALS['ratings_test_orders'] = array_merge( array_fill( 0, 100, $unrelated ), array( $order ) );

			$result = ( new RatingsController() )->check_verified_purchase( new \WP_REST_Request( array(
				'product_id' => 7, 'customer_email' => 'buyer@example.com',
			) ) );
			self::assertTrue( $result->data['verified_purchase'] );
			self::assertSame( array( array( 'name' => 'Size', 'value' => 'Large' ), array( 'name' => 'Größe', 'value' => 'Groß' ) ), $result->data['purchased_attributes'] );
			self::assertSame( 'Nigeria', $result->data['location'] );
			self::assertSame( 'fi', $GLOBALS['ratings_test_locale'] );
			self::assertSame( array( 1, 2 ), array_column( $GLOBALS['ratings_test_order_queries'], 'page' ) );
			self::assertSame( 'buyer@example.com', $GLOBALS['ratings_test_order_queries'][0]['billing_email'] );
			self::assertSame( array( 'processing', 'completed' ), $GLOBALS['ratings_test_order_queries'][0]['status'] );
			self::assertSame( '', $GLOBALS['ratings_test_order_queries'][0]['lang'] );

			unset( $GLOBALS['ratings_test_products'][17] );
			$result = ( new RatingsController() )->check_verified_purchase( new \WP_REST_Request( array(
				'product_id' => 7, 'customer_email' => 'buyer@example.com',
			) ) );
			self::assertTrue( $result->data['verified_purchase'] );
			self::assertArrayNotHasKey( 'purchased_attributes', $result->data );
		}

		public function test_invalid_product_ids_cannot_update_another_product(): void {
			$controller = new RatingsController();
			$GLOBALS['ratings_test_meta'] = array();
			foreach ( array( array( 7 ), true, -7, 7.5, '7wrong', INF, (string) PHP_INT_MAX . '0' ) as $id ) {
				$request = new \WP_REST_Request( array( 'product_id' => $id, 'avg_stars' => 5, 'review_count' => 1 ) );
				$result = $controller->update_ratings( $request );
				self::assertInstanceOf( \WP_Error::class, $result );
				self::assertSame( 'invalid_product_id', $result->code );
				self::assertFalse( RatingsController::permission_callback( $request ) );
				self::assertSame( array(), $GLOBALS['ratings_test_meta'] );
			}
		}

		public function test_rating_updates_clear_empty_totals_and_reject_invalid_data_without_writes(): void {
			$controller = new RatingsController();
			$update = static function ( $average, $count, array $extra = array() ) use ( $controller ) {
				return $controller->update_ratings( new \WP_REST_Request( array_merge( array(
					'product_id' => 7, 'avg_stars' => $average, 'review_count' => $count,
				), $extra ) ) );
			};
			$GLOBALS['ratings_test_meta'] = array();
			$GLOBALS['ratings_test_actions'] = array();
			$GLOBALS['ratings_test_transients'] = array();

			foreach ( array( array(), array( 'rating_counts' => array() ), array( 'rating_counts' => array( 5 => 2 ) ) ) as $empty_distribution ) {
				$positive = $update( '4.5', '2', array( 'rating_counts' => array( 4 => 1, 5 => 1 ) ) );
				self::assertSame( array( 'success' => true, 'product_id' => 7, 'avg_stars' => 4.5, 'review_count' => 2 ), $positive->data );
				$result = $update( '0', '0', $empty_distribution );
				self::assertInstanceOf( \WP_REST_Response::class, $result );
				self::assertSame( 200, $result->status );
				self::assertSame( array( 'success' => true, 'product_id' => 7, 'avg_stars' => 0.0, 'review_count' => 0 ), $result->data );
				self::assertSame( array(
					'_reviewbird_avg_stars' => 0.0,
					'_reviewbird_reviews_count' => 0,
					'_reviewbird_rating_counts' => array_fill( 1, 5, 0 ),
				), $GLOBALS['ratings_test_meta'] );
				self::assertSame( array( 'reviewbird_rating_updated', 7, 0.0, 0 ), end( $GLOBALS['ratings_test_actions'] ) );
				self::assertSame( 7, end( $GLOBALS['ratings_test_transients'] ) );
			}

			foreach ( array( 1, 5 ) as $average ) {
				self::assertInstanceOf( \WP_REST_Response::class, $update( $average, 1, array( 'rating_counts' => array( $average => '1' ) ) ) );
			}
			// The stored average covers rated reviews only, whatever average the service sends.
			foreach ( array( array( 0, 1, 0, 0.0 ), array( 0.83, 6, 1, 5.0 ) ) as [ $average, $count, $five_star_count, $stored_average ] ) {
				$distribution = array_fill( 1, 5, 0 );
				$distribution[5] = $five_star_count;
				$result = $update( $average, $count, array( 'rating_counts' => $distribution ) );
				self::assertInstanceOf( \WP_REST_Response::class, $result );
				self::assertSame( $count, $GLOBALS['ratings_test_meta']['_reviewbird_reviews_count'] );
				self::assertSame( $stored_average, $GLOBALS['ratings_test_meta']['_reviewbird_avg_stars'] );
				self::assertSame( $stored_average, $result->data['avg_stars'] );
				self::assertSame( $distribution, $GLOBALS['ratings_test_meta']['_reviewbird_rating_counts'] );
			}
			$before = array( $GLOBALS['ratings_test_meta'], $GLOBALS['ratings_test_actions'], $GLOBALS['ratings_test_transients'] );
			foreach ( array( null, array(), array( 5 => -1 ), array( 5 => 1.5 ), array( 5 => true ), array( 5 => array( 1 ) ), array( 0 => 1 ), array( 6 => 1 ), array( 5 => 2 ) ) as $counts ) {
				$result = $update( 5, 1, array( 'rating_counts' => $counts ) );
				self::assertSame( 'invalid_rating_counts', $result->code );
				self::assertSame( $before, array( $GLOBALS['ratings_test_meta'], $GLOBALS['ratings_test_actions'], $GLOBALS['ratings_test_transients'] ) );
			}
			foreach ( array(
				array( -0.1, 1 ), array( 5.1, 1 ), array( -1, 0 ), array( 1, 0 ),
				array( NAN, 2 ), array( INF, 2 ), array( '1e999', 2 ), array( 'invalid', 2 ),
				array( 4.5, -1 ), array( 4.5, 1.5 ), array( 4.5, NAN ), array( 4.5, INF ),
				array( 4.5, '1e999' ), array( 4.5, (string) PHP_INT_MAX . '0' ), array( 4.5, true ), array( 4.5, null ),
			) as [ $average, $count ] ) {
				$result = $update( $average, $count );
				self::assertInstanceOf( \WP_Error::class, $result );
				self::assertSame( 400, $result->data['status'] );
				self::assertSame( 2 === $count || 1 === $count || 0 === $count ? 'invalid_rating' : 'invalid_count', $result->code );
				self::assertSame( $before, array( $GLOBALS['ratings_test_meta'], $GLOBALS['ratings_test_actions'], $GLOBALS['ratings_test_transients'] ) );
			}
		}
	}
}
