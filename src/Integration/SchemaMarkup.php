<?php
/**
 * Schema.org markup integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

/**
 * Schema markup class for SEO.
 *
 * Reads review data from product meta that is populated by the
 * SchemaScheduler running via Action Scheduler in the background.
 * No API calls are made during page loads.
 */
class SchemaMarkup {

	/**
	 * Get schema reviews from product meta.
	 *
	 * Reviews are stored in product meta by SchemaScheduler and
	 * refreshed when ratings are updated.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array Array of Review schema objects.
	 */
	private function get_reviews_for_schema( int $product_id ): array {
		$reviews = get_post_meta( $product_id, SchemaScheduler::META_KEY, true );

		return is_array( $reviews ) ? $reviews : array();
	}

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
		if ( ! $this->is_schema_enabled() ) {
			return $markup;
		}

		$product_id = $product->get_id();

		$aggregate_rating = $this->build_aggregate_rating( $product_id );

		if ( $aggregate_rating ) {
			$markup['aggregateRating'] = $aggregate_rating;
		}

		$reviews = $this->get_reviews_for_schema( $product_id );

		if ( ! empty( $reviews ) ) {
			$markup['review'] = $reviews;
		}

		return $markup;
	}

	/**
	 * Check if schema markup is enabled and store is connected.
	 *
	 * @return bool True if schema should be generated.
	 */
	private function is_schema_enabled(): bool {
		return reviewbird_is_schema_enabled() && reviewbird_is_store_connected();
	}

	/**
	 * Build aggregate rating schema from product meta.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null Aggregate rating schema or null if no data.
	 */
	private function build_aggregate_rating( $product_id ) {
		$avg_stars    = get_post_meta( $product_id, '_reviewbird_avg_stars', true );
		$review_count = get_post_meta( $product_id, '_reviewbird_reviews_count', true );

		if ( empty( $avg_stars ) || empty( $review_count ) || intval( $review_count ) <= 0 ) {
			return null;
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => floatval( $avg_stars ),
			'reviewCount' => intval( $review_count ),
			'bestRating'  => '5',
			'worstRating' => '1',
		);
	}
}
