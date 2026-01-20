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
		if ( ! reviewbird_get_store_id() ) {
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Register WooCommerce rating override filters.
	 */
	private function register_hooks(): void {
		$filters = array(
			'average_rating' => 'override_average_rating',
			'rating_count'   => 'override_rating_count',
			'review_count'   => 'override_rating_count',
			'rating_counts'  => 'override_rating_counts',
		);

		foreach ( $filters as $property => $callback ) {
			add_filter( "woocommerce_product_get_{$property}", array( $this, $callback ), 10, 2 );
			add_filter( "woocommerce_product_variation_get_{$property}", array( $this, $callback ), 10, 2 );
		}
	}

	/**
	 * Override product average rating.
	 *
	 * @param float       $default Default average rating.
	 * @param \WC_Product $product Product object.
	 * @return float Average rating.
	 */
	public function override_average_rating( $default, $product ): float {
		$cached_value = $this->get_product_meta( $product, '_reviewbird_avg_stars' );

		if ( '' === $cached_value ) {
			return (float) $default;
		}

		return (float) $cached_value;
	}

	/**
	 * Override product rating/review count.
	 *
	 * @param int         $default Default count.
	 * @param \WC_Product $product Product object.
	 * @return int Rating or review count.
	 */
	public function override_rating_count( $default, $product ): int {
		$cached_value = $this->get_product_meta( $product, '_reviewbird_reviews_count' );

		if ( '' === $cached_value ) {
			return (int) $default;
		}

		return (int) $cached_value;
	}

	/**
	 * Override product rating counts distribution.
	 *
	 * WooCommerce expects an array with keys 1-5 and count values.
	 *
	 * @param array       $default Default rating counts.
	 * @param \WC_Product $product Product object.
	 * @return array Rating counts distribution.
	 */
	public function override_rating_counts( $default, $product ): array {
		$cached_counts = $this->get_product_meta( $product, '_reviewbird_rating_counts' );

		if ( ! is_array( $cached_counts ) ) {
			return $default;
		}

		return array_combine(
			range( 1, 5 ),
			array_map( fn( $key ) => (int) ( $cached_counts[ $key ] ?? 0 ), range( 1, 5 ) )
		);
	}

	/**
	 * Get reviewbird meta value for a product.
	 *
	 * @param \WC_Product $product  Product object.
	 * @param string      $meta_key Meta key to retrieve.
	 * @return mixed Meta value or empty string if not set.
	 */
	private function get_product_meta( $product, string $meta_key ) {
		return get_post_meta( $product->get_id(), $meta_key, true );
	}
}
