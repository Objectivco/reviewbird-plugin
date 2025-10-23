<?php
/**
 * Rating override integration for ReviewBop.
 *
 * Overrides WooCommerce product ratings with ReviewBop cached values.
 *
 * @package ReviewBop
 */

namespace ReviewBop\Integration;

/**
 * Rating override class.
 */
class RatingOverride {

	/**
	 * Initialize rating overrides.
	 */
	public function __construct() {
		// Only hook if store is connected
		if ( ! $this->is_store_connected() ) {
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
	 * Check if store is connected to ReviewBop.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	private function is_store_connected(): bool {
		$token = get_option( 'reviewbop_store_token' );
		return ! empty( $token );
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
		$reviewbop_stars = get_post_meta( $product_id, '_reviewbop_avg_stars', true );

		if ( ! empty( $reviewbop_stars ) ) {
			return floatval( $reviewbop_stars );
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
		$reviewbop_count   = get_post_meta( $product_id, '_reviewbop_reviews_count', true );

		if ( ! empty( $reviewbop_count ) || $reviewbop_count === '0' || $reviewbop_count === 0 ) {
			return intval( $reviewbop_count );
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
	 * Since ReviewBop doesn't send distribution data yet, we return
	 * an empty array to avoid showing incorrect distributions.
	 *
	 * @param array      $rating_counts Default rating counts.
	 * @param WC_Product $product       Product object.
	 * @return array Rating counts.
	 */
	public function override_rating_counts( $rating_counts, $product ) {
		$product_id        = $product->get_id();
		$reviewbop_count   = get_post_meta( $product_id, '_reviewbop_reviews_count', true );

		// If ReviewBop has data for this product, return empty distribution
		// to avoid showing WooCommerce's native distribution
		if ( ! empty( $reviewbop_count ) || $reviewbop_count === '0' || $reviewbop_count === 0 ) {
			return array(
				1 => 0,
				2 => 0,
				3 => 0,
				4 => 0,
				5 => 0,
			);
		}

		return $rating_counts;
	}
}
