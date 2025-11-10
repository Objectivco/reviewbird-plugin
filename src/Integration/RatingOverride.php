<?php
/**
 * Rating override integration for reviewbird.
 *
 * Overrides WooCommerce product ratings with reviewbird cached values.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

/**
 * Rating override class.
 */
class RatingOverride {

	/**
	 * Initialize rating overrides.
	 */
	public function __construct() {
		// Only hook if store is connected
		if ( ! reviewbird_get_store_id() ) {
			return;
		}

		// Override product rating getters
		add_filter( 'woocommerce_product_get_average_rating', array( $this, 'override_average_rating' ), 10, 2 );
		add_filter( 'woocommerce_product_get_rating_count', array( $this, 'override_rating_count' ), 10, 2 );
		add_filter( 'woocommerce_product_get_review_count', array( $this, 'override_review_count' ), 10, 2 );
		add_filter( 'woocommerce_product_get_rating_counts', array( $this, 'override_rating_counts' ), 10, 2 );

		// Override for product variations
		add_filter( 'woocommerce_product_variation_get_average_rating', array( $this, 'override_average_rating' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_rating_count', array( $this, 'override_rating_count' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_review_count', array( $this, 'override_review_count' ), 10, 2 );
		add_filter( 'woocommerce_product_variation_get_rating_counts', array( $this, 'override_rating_counts' ), 10, 2 );
	}

	/**
	 * Override product average rating.
	 *
	 * @param float      $average_rating Default average rating.
	 * @param WC_Product $product        Product object.
	 * @return float Average rating.
	 */
	public function override_average_rating( $average_rating, $product ) {
		$product_id      = $product->get_id();
		$reviewbird_stars = get_post_meta( $product_id, '_reviewbird_avg_stars', true );

		if ( ! empty( $reviewbird_stars ) ) {
			return floatval( $reviewbird_stars );
		}

		return $average_rating;
	}

	/**
	 * Override product rating count (total number of reviews).
	 *
	 * @param int        $rating_count Default rating count.
	 * @param WC_Product $product      Product object.
	 * @return int Rating count.
	 */
	public function override_rating_count( $rating_count, $product ) {
		$product_id        = $product->get_id();
		$reviewbird_count   = get_post_meta( $product_id, '_reviewbird_reviews_count', true );

		if ( ! empty( $reviewbird_count ) || $reviewbird_count === '0' || $reviewbird_count === 0 ) {
			return intval( $reviewbird_count );
		}

		return $rating_count;
	}

	/**
	 * Override product review count.
	 *
	 * @param int        $review_count Default review count.
	 * @param WC_Product $product      Product object.
	 * @return int Review count.
	 */
	public function override_review_count( $review_count, $product ) {
		// Use the same logic as rating_count
		return $this->override_rating_count( $review_count, $product );
	}

	/**
	 * Override product rating counts distribution.
	 *
	 * WooCommerce expects an array with keys 1-5 and count values.
	 * reviewbird sends the actual rating distribution from approved reviews.
	 *
	 * @param array      $rating_counts Default rating counts.
	 * @param WC_Product $product       Product object.
	 * @return array Rating counts.
	 */
	public function override_rating_counts( $rating_counts, $product ) {
		$product_id             = $product->get_id();
		$reviewbird_rating_counts = get_post_meta( $product_id, '_reviewbird_rating_counts', true );

		// If reviewbird has rating distribution data, use it
		if ( ! empty( $reviewbird_rating_counts ) && is_array( $reviewbird_rating_counts ) ) {
			// Ensure all keys 1-5 exist
			return array(
				1 => intval( $reviewbird_rating_counts[1] ?? 0 ),
				2 => intval( $reviewbird_rating_counts[2] ?? 0 ),
				3 => intval( $reviewbird_rating_counts[3] ?? 0 ),
				4 => intval( $reviewbird_rating_counts[4] ?? 0 ),
				5 => intval( $reviewbird_rating_counts[5] ?? 0 ),
			);
		}

		return $rating_counts;
	}
}
