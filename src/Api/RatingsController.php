<?php
/**
 * Ratings REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Ratings controller class.
 */
class RatingsController {

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

		if ( empty( $product_id ) ) {
			return new WP_Error(
				'missing_product_id',
				__( 'Product external ID is required', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_numeric( $avg_stars ) || $avg_stars < 0 || $avg_stars > 5 ) {
			return new WP_Error(
				'invalid_rating',
				__( 'Average stars must be a number between 0 and 5', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_numeric( $review_count ) || $review_count < 0 ) {
			return new WP_Error(
				'invalid_count',
				__( 'Review count must be a non-negative number', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $rating_counts ) && ! is_array( $rating_counts ) ) {
			return new WP_Error(
				'invalid_rating_counts',
				__( 'Rating counts must be an array', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return new WP_Error(
				'invalid_product_id',
				__( 'Invalid product ID', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found', 'reviewbird-reviews' ),
				array( 'status' => 404 )
			);
		}

		update_post_meta( $product_id, '_reviewbird_avg_stars', floatval( $avg_stars ) );
		update_post_meta( $product_id, '_reviewbird_reviews_count', intval( $review_count ) );

		// Store rating distribution if provided
		if ( ! empty( $rating_counts ) && is_array( $rating_counts ) ) {
			update_post_meta( $product_id, '_reviewbird_rating_counts', $rating_counts );
		}

		// Clear WooCommerce product cache to force re-fetch of ratings
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

		do_action( 'reviewbird_rating_updated', $product_id, $avg_stars, $review_count );

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->info(
				sprintf(
					'Rating updated for product %d: %.2f stars (%d reviews)',
					$product_id,
					$avg_stars,
					$review_count
				),
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
		// Extract and validate product ID from request
		$product_id = $request->get_param( 'product_id' );
		$product_id = absint( $product_id );

		// If no valid product ID provided, deny access
		if ( ! $product_id ) {
			return false;
		}

		// Verify the post is actually a product
		if ( 'product' !== get_post_type( $product_id ) ) {
			return false;
		}

		// Check WooCommerce OAuth authenticated user has permission to edit this specific product
		return wc_rest_check_post_permissions( 'product', 'edit', $product_id );
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
				__( 'Product ID is required', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $customer_email ) ) {
			return new WP_Error(
				'missing_email',
				__( 'Customer email is required', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( ! is_email( $customer_email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'Invalid email address', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return new WP_Error(
				'invalid_product_id',
				__( 'Invalid product ID', 'reviewbird-reviews' ),
				array( 'status' => 400 )
			);
		}

		if ( 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found', 'reviewbird-reviews' ),
				array( 'status' => 404 )
			);
		}

		// Check if customer bought product using email (user_id = null).
		$verified_purchase      = wc_customer_bought_product( $customer_email, null, $product_id );
		$location               = null;
		$purchased_attributes   = [];

		// Only look up variation attributes if verified (optimization).
		if ( $verified_purchase ) {
			$purchased_attributes = $this->get_purchased_attributes( $customer_email, $product_id );
		}

		// Get customer's location using WC_Customer.
		if ( class_exists( 'WC_Customer' ) ) {
			// Try to get user ID by email.
			$user = get_user_by( 'email', $customer_email );

			if ( $user ) {
				// Load customer from user ID.
				$customer = new \WC_Customer( $user->ID );
			} else {
				// Create guest customer object.
				$customer = new \WC_Customer( 0 );
				$customer->set_email( $customer_email );
			}

			// Get billing country code.
			$country_code = $customer->get_billing_country();

			// Convert to English country name (avoid WC localization issues).
			if ( ! empty( $country_code ) ) {
				$location = self::get_english_country_name( $country_code );
			}
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->debug(
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

		// Only include location if we found one.
		if ( ! empty( $location ) ) {
			$response['location'] = $location;
		}

		// Only include purchased attributes if we found any.
		if ( ! empty( $purchased_attributes ) ) {
			$response['purchased_attributes'] = $purchased_attributes;
		}

		return new WP_REST_Response( $response, 200 );
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
			return [];
		}

		$parent_id = $product->get_parent_id() ?: $product_id;

		$orders = wc_get_orders( [
			'billing_email' => $customer_email,
			'status'        => wc_get_is_paid_statuses(),
			'limit'         => 10,
			'orderby'       => 'date',
			'order'         => 'DESC',
		] );

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

				$attributes = [];
				foreach ( $variation->get_variation_attributes() as $attr_key => $attr_value ) {
					$attr_name    = wc_attribute_label( str_replace( 'attribute_', '', $attr_key ) );
					$attributes[] = [
						'name'  => ucwords( $attr_name ),
						'value' => ucwords( $attr_value ),
					];
				}

				// Return first match (most recent order).
				if ( ! empty( $attributes ) ) {
					return $attributes;
				}
			}
		}

		return [];
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
