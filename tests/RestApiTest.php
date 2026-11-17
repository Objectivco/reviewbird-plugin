<?php
/**
 * Reviewbird REST endpoint tests: ratings and verified purchases.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- PHPUnit tests use the fake WordPress in bootstrap.php.

use PHPUnit\Framework\TestCase;

final class RestApiTest extends TestCase {

	protected function setUp(): void {
		WP_Fake::reset();
		WP_Fake::product( 7 );
		WP_Fake::$post_types[17] = WP_Fake::$post_types[27] = 'product';
		WP_Fake::$meta[7]['_reviewbird_product_id'] = 7;
	}

	/** Rating meta saved for product 7, without its saved identity. */
	private static function rating_meta(): array {
		return array_diff_key( WP_Fake::$meta[7] ?? array(), array( '_reviewbird_product_id' => true ) );
	}

	/** Resolve translations the way the Polylang compatibility filter does. */
	private static function polylang( array $translations ): void {
		add_filter(
			'reviewbird_product_translation_ids',
			static function ( array $ids, int $id ) use ( $translations ) {
				return array_merge( $ids, array_values( 7 === $id ? $translations : array() ) );
			},
			10,
			2
		);
	}

		public function test_verifies_a_purchase_of_a_translated_product(): void {
		self::polylang( array( 'en' => 7, 'fi' => 17, 'de' => 27 ) );
		WP_Fake::$purchases['buyer@example.com'] = array( 17 );
		$result = reviewbird_rest_check_verified_purchase( new WP_REST_Request( array(
			'product_id' => 7, 'customer_email' => 'buyer@example.com',
		) ) );

		self::assertSame( array( 'product_id' => 7, 'customer_email' => 'buyer@example.com', 'verified_purchase' => true ), $result->data );
		self::assertSame( array( array( 'buyer@example.com', 7 ), array( 'buyer@example.com', 17 ) ), WP_Fake::$purchase_checks );
	}

	public function test_unrelated_purchases_and_request_supplied_ids_do_not_verify_a_review(): void {
		self::polylang( array( 'en' => 7, 'fi' => 17, 'invalid' => 98 ) );
		WP_Fake::$post_types[98] = 'page';
		WP_Fake::$purchases['buyer@example.com'] = array( 99 );
		$result = reviewbird_rest_check_verified_purchase( new WP_REST_Request( array(
			'product_id' => 7, 'customer_email' => 'buyer@example.com', 'product_ids' => array( 99 ),
		) ) );

		self::assertFalse( $result->data['verified_purchase'] );
		self::assertSame( array( array( 'buyer@example.com', 7 ), array( 'buyer@example.com', 17 ) ), WP_Fake::$purchase_checks );
		self::assertSame( array(), WP_Fake::$order_queries );
	}

	public function test_keeps_single_product_checks_when_polylang_is_missing_or_has_no_translations(): void {
		foreach ( array( false, true ) as $polylang ) {
			if ( $polylang ) {
				self::polylang( array() );
			}
			foreach ( array( array(), array( 7 ) ) as $purchases ) {
				WP_Fake::$purchase_checks = array();
				WP_Fake::$purchases['buyer@example.com'] = $purchases;
				$result = reviewbird_rest_check_verified_purchase( new WP_REST_Request( array(
					'product_id' => 7, 'customer_email' => 'buyer@example.com',
				) ) );
				self::assertSame( ! empty( $purchases ), $result->data['verified_purchase'] );
				self::assertSame( array( array( 'buyer@example.com', 7 ) ), WP_Fake::$purchase_checks );
			}
		}
	}

	public function test_reads_saved_purchase_details_after_the_first_page_of_orders(): void {
		self::polylang( array( 'en' => 7, 'fi' => 17 ) );
		WP_Fake::$purchases['buyer@example.com'] = array( 17 );
		$size = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_variation' ) )->getMock();
		$size->method( 'get_variation' )->willReturn( true );
		$material = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_variation' ) )->getMock();
		$material->method( 'get_variation' )->willReturn( false );
		$parent = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_attributes' ) )->getMock();
		$parent->method( 'get_attributes' )->with( 'edit' )->willReturn( array( 'size' => $size, 'gr%c3%b6%c3%9fe' => $size, 'material' => $material ) );
		WP_Fake::$products[17] = $parent;
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
		$order->method( 'get_billing_country' )->willReturnCallback( static fn() => $GLOBALS['ratings_test_billing_country'] ?? 'NG' );
		$unrelated = $this->getMockBuilder( \stdClass::class )->addMethods( array( 'get_items' ) )->getMock();
		$unrelated->method( 'get_items' )->willReturn( array( $items[0] ) );
		WP_Fake::$orders = array_merge( array_fill( 0, 100, $unrelated ), array( $order ) );

		$result = reviewbird_rest_check_verified_purchase( new WP_REST_Request( array(
			'product_id' => 7, 'customer_email' => 'buyer@example.com',
		) ) );
		self::assertTrue( $result->data['verified_purchase'] );
		self::assertSame( array( array( 'name' => 'Size', 'value' => 'Large' ), array( 'name' => 'Größe', 'value' => 'Groß' ) ), $result->data['purchased_attributes'] );
		self::assertSame( 'Nigeria', $result->data['location'] );
		self::assertSame( 'fi', WP_Fake::$locale );
		self::assertSame( array( 1, 2 ), array_column( WP_Fake::$order_queries, 'page' ) );
		self::assertSame( 'buyer@example.com', WP_Fake::$order_queries[0]['billing_email'] );
		self::assertSame( array( 'processing', 'completed' ), WP_Fake::$order_queries[0]['status'] );
		self::assertSame( '', WP_Fake::$order_queries[0]['lang'] );

		// Send plain English names, not WooCommerce's dropdown labels.
		foreach ( array(
			'US' => 'United States',
			'GB' => 'United Kingdom',
			'UM' => 'United States Minor Outlying Islands',
			'VI' => 'Virgin Islands (US)',
			'CW' => 'Curaçao',
		) as $code => $name ) {
			$GLOBALS['ratings_test_billing_country'] = $code;
			$result = reviewbird_rest_check_verified_purchase( new WP_REST_Request( array(
				'product_id' => 7, 'customer_email' => 'buyer@example.com',
			) ) );
			self::assertSame( $name, $result->data['location'] );
		}
		unset( $GLOBALS['ratings_test_billing_country'] );

		unset( WP_Fake::$products[17] );
		$result = reviewbird_rest_check_verified_purchase( new WP_REST_Request( array(
			'product_id' => 7, 'customer_email' => 'buyer@example.com',
		) ) );
		self::assertTrue( $result->data['verified_purchase'] );
		self::assertArrayNotHasKey( 'purchased_attributes', $result->data );
	}

	public function test_invalid_product_ids_cannot_update_another_product(): void {
					foreach ( array( array( 7 ), true, -7, 7.5, '7wrong', INF, (string) PHP_INT_MAX . '0' ) as $id ) {
			$request = new WP_REST_Request( array( 'product_id' => $id, 'avg_stars' => 5, 'review_count' => 1 ) );
			$result = reviewbird_rest_update_ratings( $request );
			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 'invalid_product_id', $result->code );
			self::assertFalse( reviewbird_rest_can_update_ratings( $request ) );
			self::assertSame( array(), self::rating_meta() );
		}
	}

	public function test_rating_updates_clear_empty_totals_and_reject_invalid_data_without_writes(): void {
					$update = static function ( $average, $count, array $extra = array() ) {
			return reviewbird_rest_update_ratings( new WP_REST_Request( array_merge( array(
				'product_id' => 7, 'avg_stars' => $average, 'review_count' => $count,
			), $extra ) ) );
		};

		foreach ( array( array(), array( 'rating_counts' => array() ), array( 'rating_counts' => array( 5 => 2 ) ) ) as $empty_distribution ) {
			$positive = $update( '4.5', '2', array( 'rating_counts' => array( 4 => 1, 5 => 1 ) ) );
			self::assertSame( array( 'success' => true, 'product_id' => 7, 'avg_stars' => 4.5, 'review_count' => 2 ), $positive->data );
			$result = $update( '0', '0', $empty_distribution );
			self::assertInstanceOf( WP_REST_Response::class, $result );
			self::assertSame( 200, $result->status );
			self::assertSame( array( 'success' => true, 'product_id' => 7, 'avg_stars' => 0.0, 'review_count' => 0 ), $result->data );
			self::assertSame( array(
				'_reviewbird_avg_stars' => 0.0,
				'_reviewbird_reviews_count' => 0,
				'_reviewbird_rating_counts' => array_fill( 1, 5, 0 ),
			), self::rating_meta() );
			self::assertSame( array( 'reviewbird_rating_updated', 7, 0.0, 0 ), end( WP_Fake::$actions ) );
			self::assertSame( 7, end( WP_Fake::$deleted_wc_transients ) );
		}

		foreach ( array( 1, 5 ) as $average ) {
			self::assertInstanceOf( WP_REST_Response::class, $update( $average, 1, array( 'rating_counts' => array( $average => '1' ) ) ) );
		}
		// The service owns the average, so it is stored exactly as sent.
		foreach ( array( array( 0, 1, 0, 0.0 ), array( 4.7, 6, 1, 4.7 ) ) as [ $average, $count, $five_star_count, $stored_average ] ) {
			$distribution = array_fill( 1, 5, 0 );
			$distribution[5] = $five_star_count;
			$result = $update( $average, $count, array( 'rating_counts' => $distribution ) );
			self::assertInstanceOf( WP_REST_Response::class, $result );
			self::assertSame( $count, self::rating_meta()['_reviewbird_reviews_count'] );
			self::assertSame( $stored_average, self::rating_meta()['_reviewbird_avg_stars'] );
			self::assertSame( $stored_average, $result->data['avg_stars'] );
			self::assertSame( $distribution, self::rating_meta()['_reviewbird_rating_counts'] );
		}
		// Rating counts are optional with a positive total; saved counts are kept.
		foreach ( array( null, array() ) as $counts ) {
			$result = $update( 4.5, 3, array( 'rating_counts' => $counts ) );
			self::assertSame( 4.5, $result->data['avg_stars'] );
			self::assertSame( 4.5, self::rating_meta()['_reviewbird_avg_stars'] );
			self::assertSame( $distribution, self::rating_meta()['_reviewbird_rating_counts'] );
		}
		$before = array( self::rating_meta(), WP_Fake::$actions, WP_Fake::$deleted_wc_transients );
		foreach ( array( array( 5 => -1 ), array( 5 => 1.5 ), array( 5 => true ), array( 5 => array( 1 ) ), array( 0 => 1 ), array( 6 => 1 ), array( 5 => 2 ) ) as $counts ) {
			$result = $update( 5, 1, array( 'rating_counts' => $counts ) );
			self::assertSame( 'invalid_rating_counts', $result->code );
			self::assertSame( $before, array( self::rating_meta(), WP_Fake::$actions, WP_Fake::$deleted_wc_transients ) );
		}
		foreach ( array(
			array( -0.1, 1 ), array( 5.1, 1 ), array( -1, 0 ), array( 1, 0 ),
			array( NAN, 2 ), array( INF, 2 ), array( '1e999', 2 ), array( 'invalid', 2 ),
			array( 4.5, -1 ), array( 4.5, 1.5 ), array( 4.5, NAN ), array( 4.5, INF ),
			array( 4.5, '1e999' ), array( 4.5, (string) PHP_INT_MAX . '0' ), array( 4.5, true ), array( 4.5, null ),
		) as [ $average, $count ] ) {
			$result = $update( $average, $count );
			self::assertInstanceOf( WP_Error::class, $result );
			self::assertSame( 400, $result->data['status'] );
			self::assertSame( 2 === $count || 1 === $count || 0 === $count ? 'invalid_rating' : 'invalid_count', $result->code );
			self::assertSame( $before, array( self::rating_meta(), WP_Fake::$actions, WP_Fake::$deleted_wc_transients ) );
		}
	}
}
