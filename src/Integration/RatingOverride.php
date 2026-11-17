<?php
/**
 * Rating override integration for reviewbird.
 *
 * Overrides WooCommerce product ratings with reviewbird cached values.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Product;

/**
 * Rating override class.
 */
class RatingOverride {

	/**
	 * Initialize rating overrides.
	 */
	public function __construct() {
		add_action( 'reviewbird_sync_product_rating', 'reviewbird_sync_product_rating', 10, 1 );
		add_action(
			'update_option_reviewbird_enable_widget',
			static function ( $old_value, $value ) {
				if ( 'yes' !== $value && class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
					as_unschedule_all_actions( 'reviewbird_sync_product_rating' );
				}
			},
			10,
			2
		);
		if ( ! reviewbird_can_show_widget() ) {
			return;
		}

		$filters = array(
			'average_rating' => 'override_average_rating',
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
	 * @param float      $native_rating Default average rating.
	 * @param WC_Product $product Product object.
	 * @return float Average rating.
	 */
	public function override_average_rating( $native_rating, $product ): float {
		$cached_value = $this->get_product_meta( $product, '_reviewbird_avg_stars' );

		if ( '' === $cached_value ) {
			return (float) $native_rating;
		}

		return (float) $cached_value;
	}

	/**
	 * Override product rating/review count.
	 *
	 * @param int        $native_count Default count.
	 * @param WC_Product $product Product object.
	 * @return int Rating or review count.
	 */
	public function override_rating_count( $native_count, $product ): int {
		$cached_value = $this->get_product_meta( $product, '_reviewbird_reviews_count' );

		if ( '' === $cached_value ) {
			return (int) $native_count;
		}

		return (int) $cached_value;
	}

	/**
	 * Override product rating counts distribution.
	 *
	 * WooCommerce expects an array with keys 1-5 and count values.
	 *
	 * @param array      $native_counts Default rating counts.
	 * @param WC_Product $product Product object.
	 * @return array Rating counts distribution.
	 */
	public function override_rating_counts( $native_counts, $product ): array {
		$cached_counts = $this->get_product_meta( $product, '_reviewbird_rating_counts' );

		if ( ! is_array( $cached_counts ) ) {
			return $native_counts;
		}

		return array_combine(
			range( 1, 5 ),
			array_map( fn( $key ) => (int) ( $cached_counts[ $key ] ?? 0 ), range( 1, 5 ) )
		);
	}

	/**
	 * Get reviewbird meta value for a product.
	 *
	 * Reviewbird groups variation reviews under the parent product, so a
	 * variation reads its parent's rating.
	 *
	 * @param WC_Product $product  Product object.
	 * @param string     $meta_key Meta key to retrieve.
	 * @return mixed Meta value or empty string if not set.
	 */
	private function get_product_meta( $product, string $meta_key ) {
		if ( ! reviewbird_can_show_widget() ) {
			return '';
		}
		$product_id = reviewbird_product_meta_id( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
		$value      = get_post_meta( $product_id, $meta_key, true );

		if ( '' !== $value ) {
			return $value;
		}

		// Each rating getter lands here, so check the queue once per product per request.
		static $checked = array();
		if ( isset( $checked[ $product_id ] ) ) {
			return $value;
		}
		$checked[ $product_id ] = true;

		if ( ! get_transient( 'reviewbird_rating_sync_' . $product_id )
			&& class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', $product_id );
		}

		return $value;
	}
}
