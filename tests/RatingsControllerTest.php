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

	function wc_delete_product_transients( $product_id ) { $GLOBALS['ratings_test_transients'][] = $product_id; }
}

namespace reviewbird\Api {
	function __( $text ) { return $text; }
	function absint( $value ) { return abs( (int) $value ); }
	function get_post_type( $id ) { return 'product'; }
	function is_wp_error( $value ) { return $value instanceof \WP_Error; }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['ratings_test_meta'][ $key ] = $value; }
	function do_action( ...$args ) { $GLOBALS['ratings_test_actions'][] = $args; }

	require_once dirname( __DIR__ ) . '/src/Api/RatingsController.php';
}

namespace reviewbird\Tests {

	use PHPUnit\Framework\TestCase;
	use reviewbird\Api\RatingsController;

	final class RatingsControllerTest extends TestCase {

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
				self::assertSame( array( 'reviewbird_rating_updated', 7, '0', 0 ), end( $GLOBALS['ratings_test_actions'] ) );
				self::assertSame( 7, end( $GLOBALS['ratings_test_transients'] ) );
			}

			foreach ( array( 1, 5 ) as $average ) {
				self::assertInstanceOf( \WP_REST_Response::class, $update( $average, 1 ) );
			}
			$before = array( $GLOBALS['ratings_test_meta'], $GLOBALS['ratings_test_actions'], $GLOBALS['ratings_test_transients'] );
			foreach ( array(
				array( 0, 1 ), array( 0.5, 1 ), array( 5.1, 1 ), array( -1, 0 ), array( 1, 0 ),
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
