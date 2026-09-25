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
	 * @param mixed $product_id The product ID to validate.
	 * @return int|WP_Error The sanitized product ID or an error.
	 */
	private static function validate_product_id( $product_id ) {
		if ( empty( $product_id ) ) {
			return new WP_Error( 'missing_product_id', __( 'Product ID is required', 'reviewbird' ), array( 'status' => 400 ) );
		}

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return new WP_Error( 'invalid_product_id', __( 'Invalid product ID', 'reviewbird' ), array( 'status' => 400 ) );
		}

		if ( 'product' !== get_post_type( $product_id ) ) {
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
			|| ( 0 === $review_count ? 0.0 !== (float) $avg_stars : ( $avg_stars < 1 || $avg_stars > 5 ) ) ) {
			return new WP_Error( 'invalid_rating', __( 'Average stars must be 0 when there are no reviews, or a number between 1 and 5', 'reviewbird' ), array( 'status' => 400 ) );
		}

		if ( ! empty( $rating_counts ) && ! is_array( $rating_counts ) ) {
			return new WP_Error( 'invalid_rating_counts', __( 'Rating counts must be an array', 'reviewbird' ), array( 'status' => 400 ) );
		}

		update_post_meta( $product_id, '_reviewbird_avg_stars', floatval( $avg_stars ) );
		update_post_meta( $product_id, '_reviewbird_reviews_count', intval( $review_count ) );

		if ( 0 === $review_count ) {
			$rating_counts = array_fill( 1, 5, 0 );
		}
		if ( ! empty( $rating_counts ) ) {
			update_post_meta( $product_id, '_reviewbird_rating_counts', $rating_counts );
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

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
				'product_id'   => $product_id,
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

		return wc_rest_check_post_permissions( 'product', 'edit', $product_id );
	}

	/**
	 * Permission callback for verified purchase endpoint.
	 * Checks order read permissions since this endpoint accesses customer purchase data.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function verified_purchase_permission_callback( WP_REST_Request $request ) {
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

		$product_id = self::validate_product_id( $product_id );
		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		if ( empty( $customer_email ) ) {
			return new WP_Error( 'missing_email', __( 'Customer email is required', 'reviewbird' ), array( 'status' => 400 ) );
		}

		if ( ! is_email( $customer_email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address', 'reviewbird' ), array( 'status' => 400 ) );
		}

		$verified_purchase    = wc_customer_bought_product( $customer_email, null, $product_id );
		$purchased_attributes = $verified_purchase ? $this->get_purchased_attributes( $customer_email, $product_id ) : array();
		$location             = $this->get_customer_location( $customer_email );

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
	 * Get customer billing location by email.
	 *
	 * @param string $customer_email Customer email address.
	 * @return string|null English country name or null if not found.
	 */
	private function get_customer_location( string $customer_email ): ?string {
		if ( ! class_exists( 'WC_Customer' ) ) {
			return null;
		}

		$user     = get_user_by( 'email', $customer_email );
		$customer = $user ? new \WC_Customer( $user->ID ) : new \WC_Customer( 0 );

		if ( ! $user ) {
			$customer->set_email( $customer_email );
		}

		$country_code = $customer->get_billing_country();

		return ! empty( $country_code ) ? self::get_english_country_name( $country_code ) : null;
	}

	/**
	 * Get variation attributes for a customer's purchase of a product.
	 *
	 * @param string $customer_email Customer email address.
	 * @param int    $product_id     Product ID (parent or variation).
	 * @return array Purchased variation attributes.
	 */
	private function get_purchased_attributes( string $customer_email, int $product_id ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}

		$parent_id = $product->get_parent_id() ? $product->get_parent_id() : $product_id;

		$orders = wc_get_orders(
			array(
				'billing_email' => $customer_email,
				'status'        => wc_get_is_paid_statuses(),
				'limit'         => 10,
				'orderby'       => 'date',
				'order'         => 'DESC',
			)
		);

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$item_product_id = $item->get_product_id();
				$variation_id    = $item->get_variation_id();

				// Skip if not matching parent or no variation.
				if ( $item_product_id !== $parent_id || ! $variation_id ) {
					continue;
				}

				$variation = wc_get_product( $variation_id );
				if ( ! $variation ) {
					continue;
				}

				$attributes = array();
				foreach ( $variation->get_variation_attributes() as $attr_key => $attr_value ) {
					$attr_name    = wc_attribute_label( str_replace( 'attribute_', '', $attr_key ) );
					$attributes[] = array(
						'name'  => ucwords( $attr_name ),
						'value' => ucwords( $attr_value ),
					);
				}

				// Return first match (most recent order).
				if ( ! empty( $attributes ) ) {
					return $attributes;
				}
			}
		}

		return array();
	}

	/**
	 * Get English country name from ISO country code.
	 *
	 * Uses a static list to avoid WooCommerce localization returning
	 * country names in the store's language instead of English.
	 *
	 * @param string $country_code Two-letter ISO country code.
	 * @return string|null English country name or null if not found.
	 */
	private static function get_english_country_name( string $country_code ): ?string {
		$countries = array(
			'US' => 'United States',
			'CA' => 'Canada',
			'GB' => 'United Kingdom',
			'AU' => 'Australia',
			'NZ' => 'New Zealand',
			'DE' => 'Germany',
			'FR' => 'France',
			'ES' => 'Spain',
			'IT' => 'Italy',
			'NL' => 'Netherlands',
			'BE' => 'Belgium',
			'SE' => 'Sweden',
			'NO' => 'Norway',
			'DK' => 'Denmark',
			'FI' => 'Finland',
			'IE' => 'Ireland',
			'CH' => 'Switzerland',
			'AT' => 'Austria',
			'PL' => 'Poland',
			'CZ' => 'Czech Republic',
			'PT' => 'Portugal',
			'GR' => 'Greece',
			'HU' => 'Hungary',
			'RO' => 'Romania',
			'BG' => 'Bulgaria',
			'HR' => 'Croatia',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'LT' => 'Lithuania',
			'LV' => 'Latvia',
			'EE' => 'Estonia',
			'LU' => 'Luxembourg',
			'MT' => 'Malta',
			'CY' => 'Cyprus',
			'IS' => 'Iceland',
			'JP' => 'Japan',
			'CN' => 'China',
			'KR' => 'South Korea',
			'TW' => 'Taiwan',
			'HK' => 'Hong Kong',
			'SG' => 'Singapore',
			'MY' => 'Malaysia',
			'TH' => 'Thailand',
			'ID' => 'Indonesia',
			'PH' => 'Philippines',
			'VN' => 'Vietnam',
			'IN' => 'India',
			'MX' => 'Mexico',
			'BR' => 'Brazil',
			'AR' => 'Argentina',
			'CL' => 'Chile',
			'CO' => 'Colombia',
			'PE' => 'Peru',
			'ZA' => 'South Africa',
			'AE' => 'United Arab Emirates',
			'SA' => 'Saudi Arabia',
			'IL' => 'Israel',
			'TR' => 'Turkey',
			'RU' => 'Russia',
			'UA' => 'Ukraine',
			'RS' => 'Serbia',
		);

		return isset( $countries[ $country_code ] ) ? $countries[ $country_code ] : null;
	}
}
