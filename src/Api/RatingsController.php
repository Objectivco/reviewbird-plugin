<?php
/**
 * Ratings REST API controller for ReviewApp.
 *
 * @package ReviewApp
 */

namespace ReviewApp\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Ratings controller class.
 */
class RatingsController {

	/**
	 * Update product ratings from ReviewApp.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function update_ratings( WP_REST_Request $request ) {
		$product_external_id = $request->get_param( 'product_external_id' );
		$avg_stars           = $request->get_param( 'avg_stars' );
		$review_count        = $request->get_param( 'review_count' );

		if ( empty( $product_external_id ) ) {
			return new WP_Error(
				'missing_product_id',
				__( 'Product external ID is required', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_numeric( $avg_stars ) || $avg_stars < 0 || $avg_stars > 5 ) {
			return new WP_Error(
				'invalid_rating',
				__( 'Average stars must be a number between 0 and 5', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_numeric( $review_count ) || $review_count < 0 ) {
			return new WP_Error(
				'invalid_count',
				__( 'Review count must be a non-negative number', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		$product_id = absint( $product_external_id );

		if ( ! $product_id ) {
			return new WP_Error(
				'invalid_product_id',
				__( 'Invalid product ID', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found', 'reviewapp-reviews' ),
				array( 'status' => 404 )
			);
		}

		update_post_meta( $product_id, '_reviewapp_avg_stars', floatval( $avg_stars ) );
		update_post_meta( $product_id, '_reviewapp_reviews_count', intval( $review_count ) );

		do_action( 'reviewapp_rating_updated', $product_id, $avg_stars, $review_count );

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->info(
				sprintf(
					'Rating updated for product %d: %.2f stars (%d reviews)',
					$product_id,
					$avg_stars,
					$review_count
				),
				array( 'source' => 'reviewapp' )
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'product_id'   => $product_id,
				'avg_stars'    => floatval( $avg_stars ),
				'review_count' => intval( $review_count ),
			),
			200
		);
	}

	/**
	 * Permission callback for ratings endpoint.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function permission_callback( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) ) {
			return new WP_Error(
				'missing_auth',
				__( 'Authorization header is required', 'reviewapp-reviews' ),
				array( 'status' => 401 )
			);
		}

		$provided_token = str_replace( 'Bearer ', '', $auth_header );
		$stored_token   = get_option( 'reviewapp_store_token' );

		if ( empty( $stored_token ) ) {
			return new WP_Error(
				'no_token_configured',
				__( 'ReviewApp store token not configured', 'reviewapp-reviews' ),
				array( 'status' => 401 )
			);
		}

		if ( $provided_token !== $stored_token ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid authentication token', 'reviewapp-reviews' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Check if a customer is a verified purchaser of a product.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function check_verified_purchase( WP_REST_Request $request ) {
		$product_id     = $request->get_param( 'product_id' );
		$customer_email = $request->get_param( 'customer_email' );

		if ( empty( $product_id ) ) {
			return new WP_Error(
				'missing_product_id',
				__( 'Product ID is required', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $customer_email ) ) {
			return new WP_Error(
				'missing_email',
				__( 'Customer email is required', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_email( $customer_email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Invalid email address', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return new WP_Error(
				'invalid_product_id',
				__( 'Invalid product ID', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found', 'reviewapp-reviews' ),
				array( 'status' => 404 )
			);
		}

		// Check if customer bought product using email (user_id = null).
		$verified_purchase = false;
		if ( function_exists( 'wc_customer_bought_product' ) ) {
			$verified_purchase = wc_customer_bought_product( null, $customer_email, $product_id );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->debug(
				sprintf(
					'Verified purchase check for product %d, email %s: %s',
					$product_id,
					$customer_email,
					$verified_purchase ? 'true' : 'false'
				),
				array( 'source' => 'reviewapp' )
			);
		}

		return new WP_REST_Response(
			array(
				'product_id'        => $product_id,
				'customer_email'    => $customer_email,
				'verified_purchase' => (bool) $verified_purchase,
			),
			200
		);
	}
}
