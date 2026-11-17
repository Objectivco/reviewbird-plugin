<?php
/**
 * Schema.org markup integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema markup class for SEO.
 *
 * Reads review data from product meta that is populated by the
 * SchemaScheduler running via Action Scheduler in the background.
 * No API calls are made during page loads.
 */
class SchemaMarkup {

	/**
	 * Filter WooCommerce's structured data to inject reviewbird review data.
	 *
	 * This hooks into WooCommerce's schema output to add aggregateRating and
	 * individual reviews from reviewbird, replacing WooCommerce's native review data.
	 *
	 * @param array       $markup  The structured data markup array.
	 * @param \WC_Product $product The WooCommerce product object.
	 * @return array Modified markup with reviewbird review data.
	 */
	public function filter_woocommerce_structured_data( $markup, $product ) {
		if ( ! reviewbird_is_schema_enabled() || ! reviewbird_is_store_connected() ) {
			return $markup;
		}

		$product_id = reviewbird_product_meta_id( $product->get_id() );
		if ( '0' === (string) get_post_meta( $product_id, '_reviewbird_reviews_count', true ) ) {
			unset( $markup['aggregateRating'], $markup['review'] );
			return $markup;
		}

		$aggregate_rating = $this->build_aggregate_rating( $product_id );

		if ( $aggregate_rating ) {
			$markup['aggregateRating'] = $aggregate_rating;
		}

		$reviews = get_post_meta( $product_id, SchemaScheduler::META_KEY, true );

		if ( is_array( $reviews ) && ! empty( $reviews ) ) {
			$markup['review'] = $reviews;
		}
		if ( $aggregate_rating ) {
			( new SchemaScheduler() )->schedule_schema_refresh( $product_id );
		}

		return $markup;
	}

	/**
	 * Build aggregate rating schema from product meta.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null Aggregate rating schema or null if no data.
	 */
	private function build_aggregate_rating( $product_id ) {
		// Match the visible stars: average rated reviews only.
		$counts       = get_post_meta( $product_id, '_reviewbird_rating_counts', true );
		$rating_value = reviewbird_rated_average( $counts, (float) get_post_meta( $product_id, '_reviewbird_avg_stars', true ) );
		$review_count = (int) get_post_meta( $product_id, '_reviewbird_reviews_count', true );
		// Meta saved before rating counts were stored rated every review.
		$rating_count = is_array( $counts ) ? array_sum( array_map( fn( $stars ) => (int) ( $counts[ $stars ] ?? 0 ), range( 1, 5 ) ) ) : $review_count;

		if ( $review_count <= 0 || $rating_count <= 0 || $rating_value < 1 ) {
			return null;
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $rating_value,
			'ratingCount' => $rating_count,
			'reviewCount' => $review_count,
			'bestRating'  => '5',
			'worstRating' => '1',
		);
	}
}
