<?php
/**
 * Ratings REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Ratings controller class.
 */
class RatingsController {

	/**
	 * Validate and sanitize a product ID.
	 *
	 * @param mixed $product_id       The product ID to validate.
	 * @param bool  $allow_variations Whether variation IDs are valid.
	 * @return int|WP_Error The sanitized product ID or an error.
	 */
	private static function validate_product_id( $product_id, bool $allow_variations = false ) {
		if ( empty( $product_id ) ) {
			return new WP_Error( 'missing_product_id', __( 'Product ID is required', 'reviewbird' ), array( 'status' => 400 ) );
		}

		if ( ! is_numeric( $product_id ) || false === filter_var( $product_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
			return new WP_Error( 'invalid_product_id', __( 'Invalid product ID', 'reviewbird' ), array( 'status' => 400 ) );
		}
		$product_id = (int) $product_id;

		$product_types = $allow_variations ? array( 'product', 'product_variation' ) : array( 'product' );
		if ( ! in_array( get_post_type( $product_id ), $product_types, true ) ) {
			$product = reviewbird_find_product( $product_id );
			if ( $product && in_array( get_post_type( $product->get_id() ), $product_types, true ) ) {
				return $product_id;
			}
			return new WP_Error( 'product_not_found', __( 'Product not found', 'reviewbird' ), array( 'status' => 404 ) );
		}

		return $product_id;
	}

	/**
	 * Update product ratings from reviewbird.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function update_ratings( WP_REST_Request $request ) {
		$product_id    = $request->get_param( 'product_id' );
		$avg_stars     = $request->get_param( 'avg_stars' );
		$review_count  = $request->get_param( 'review_count' );
		$rating_counts = $request->get_param( 'rating_counts' );

		$product_id = self::validate_product_id( $product_id );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( ! is_numeric( $review_count ) || ! is_finite( (float) $review_count )
			|| false === filter_var( $review_count, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) ) {
			return new WP_Error( 'invalid_count', __( 'Review count must be a whole number of 0 or more', 'reviewbird' ), array( 'status' => 400 ) );
		}
		$review_count = (int) $review_count;

		if ( ! is_numeric( $avg_stars ) || ! is_finite( (float) $avg_stars )
			|| ( 0 === $review_count ? 0.0 !== (float) $avg_stars : ( $avg_stars < 0 || $avg_stars > 5 ) ) ) {
			return new WP_Error( 'invalid_rating', __( 'Average stars must be between 0 and 5, and 0 when there are no reviews', 'reviewbird' ), array( 'status' => 400 ) );
		}

		$rating_counts = reviewbird_normalize_rating_counts( $rating_counts, $review_count );
		if ( null === $rating_counts ) {
			return new WP_Error( 'invalid_rating_counts', __( 'Rating counts must contain whole numbers of 0 or more for stars 1 to 5 and must not exceed the review count.', 'reviewbird' ), array( 'status' => 400 ) );
		}

		$shared_id  = reviewbird_product_id( $product_id );
		$product_id = reviewbird_product_meta_id( $product_id );
		update_post_meta( $product_id, '_reviewbird_avg_stars', floatval( $avg_stars ) );
		update_post_meta( $product_id, '_reviewbird_reviews_count', intval( $review_count ) );

		update_post_meta( $product_id, '_reviewbird_rating_counts', $rating_counts );

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

		delete_transient( 'reviewbird_ssr_' . $product_id );
		do_action( 'reviewbird_rating_updated', $product_id, $avg_stars, $review_count );

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf( 'Rating updated for product %d: %.2f stars (%d reviews)', $product_id, $avg_stars, $review_count ),
				array( 'source' => 'reviewbird' )
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'product_id'   => $shared_id,
				'avg_stars'    => floatval( $avg_stars ),
				'review_count' => intval( $review_count ),
			),
			200
		);
	}

	/**
	 * Permission callback for ratings endpoint.
	 * Uses WooCommerce authentication (consumer key/secret) via wc_rest_check_post_permissions.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function permission_callback( WP_REST_Request $request ) {
		$product_id = self::validate_product_id( $request->get_param( 'product_id' ) );

		if ( is_wp_error( $product_id ) ) {
			return false;
		}

		if ( 'product' !== get_post_type( $product_id ) ) {
			$product    = reviewbird_find_product( $product_id );
			$product_id = $product->get_id();
		}
		return wc_rest_check_post_permissions( 'product', 'edit', $product_id );
	}

	/**
	 * Permission callback for verified purchase endpoint.
	 * Checks order read permissions since this endpoint accesses customer purchase data.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function verified_purchase_permission_callback( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Keep the REST permission callback signature.
		return wc_rest_check_post_permissions( 'shop_order', 'read', 0 );
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

		$product_id = self::validate_product_id( $product_id, true );
		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( empty( $customer_email ) ) {
			return new WP_Error( 'missing_email', __( 'Customer email is required', 'reviewbird' ), array( 'status' => 400 ) );
		}

		if ( ! is_string( $customer_email ) || ! is_email( $customer_email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address', 'reviewbird' ), array( 'status' => 400 ) );
		}

		$product_ids = reviewbird_product_translation_ids( $product_id );
		$shared_id   = reviewbird_product_id( $product_id );
		// Native orders can still reference the deleted product that owns this saved identity.
		if ( $product_ids && ! get_post_type( $shared_id ) && ! in_array( $shared_id, $product_ids, true ) ) {
			$product_ids[] = $shared_id;
		}

		$verified_purchase = false;
		foreach ( $product_ids as $purchase_product_id ) {
			if ( wc_customer_bought_product( $customer_email, null, $purchase_product_id ) ) {
				$verified_purchase = true;
				break;
			}
		}

		$purchase             = $verified_purchase ? $this->get_purchase_details( $customer_email, $product_ids ) : array();
		$purchased_attributes = $purchase['purchased_attributes'] ?? array();
		$location             = $purchase['location'] ?? null;

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				sprintf(
					'Verified purchase check for product %d, email %s: %s, location: %s, attributes: %s',
					$product_id,
					$customer_email,
					$verified_purchase ? 'true' : 'false',
					$location ? $location : 'none',
					! empty( $purchased_attributes ) ? wp_json_encode( $purchased_attributes ) : 'none'
				),
				array( 'source' => 'reviewbird' )
			);
		}

		$response = array(
			'product_id'        => $product_id,
			'customer_email'    => $customer_email,
			'verified_purchase' => (bool) $verified_purchase,
		);

		if ( ! empty( $location ) ) {
			$response['location'] = $location;
		}

		if ( ! empty( $purchased_attributes ) ) {
			$response['purchased_attributes'] = $purchased_attributes;
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get the saved details from the most recent matching paid order.
	 *
	 * @param string $customer_email Customer billing email.
	 * @param int[]  $product_ids    Product IDs, including translations.
	 * @return array Purchase details.
	 */
	private function get_purchase_details( string $customer_email, array $product_ids ): array {
		// ponytail: Read 100 orders per query; use a product lookup if long order histories become slow.
		$page = 1;
		do {
			$orders      = wc_get_orders(
				array(
					'billing_email' => $customer_email,
					'status'        => wc_get_is_paid_statuses(),
					'limit'         => 100,
					'page'          => $page++,
					'orderby'       => 'date',
					'order'         => 'DESC',
					'lang'          => '',
				)
			);
			$order_count = count( $orders );

			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					$variation_id = (int) $item->get_variation_id( 'edit' );
					if ( ! in_array( (int) $item->get_product_id( 'edit' ), $product_ids, true ) && ! in_array( $variation_id, $product_ids, true ) ) {
						continue;
					}

					$attributes = array();
					if ( $variation_id ) {
						$parent               = wc_get_product( $item->get_product_id( 'edit' ) );
						$variation_attributes = $parent ? array_filter( $parent->get_attributes( 'edit' ), 'wc_attributes_array_filter_variation' ) : array();
						$variation_keys       = array_map( 'rawurldecode', array_keys( $variation_attributes ) );
						foreach ( $item->get_formatted_meta_data( '_', true ) as $meta ) {
							if ( ! in_array( $meta->key, $variation_keys, true ) ) {
								continue;
							}
							$attributes[] = array(
								'name'  => wp_strip_all_tags( $meta->display_key ),
								'value' => wp_strip_all_tags( $meta->display_value ),
							);
						}
					}

					$switched = switch_to_locale( 'en_US' );
					try {
						$countries = ( new \WC_Countries() )->get_countries();
					} finally {
						if ( $switched ) {
							restore_previous_locale();
						}
					}
					return array(
						'location'             => $countries[ $order->get_billing_country() ] ?? null,
						'purchased_attributes' => $attributes,
					);
				}
			}
		} while ( 100 === $order_count );

		return array();
	}
}
