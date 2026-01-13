<?php
/**
 * Schema.org markup integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

/**
 * Schema markup class for SEO.
 */
class SchemaMarkup {

	/**
	 * Fetch approved reviews from reviewbird API for schema markup.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array Array of Review schema objects.
	 */
	private function fetch_reviews_for_schema( $product_id ) {
		$store_id = reviewbird_get_store_id();

		if ( ! $store_id ) {
			return array();
		}

		// Try to get reviews from cache first.
		$cache_key = 'reviewbird_schema_reviews_' . $product_id;
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Fetch reviews from API.
		$response = wp_remote_get(
			reviewbird_get_api_url() . "/api/public/{$store_id}/{$product_id}",
			array(
				'timeout'   => 10,
				'sslverify' => ! reviewbird_should_disable_ssl_verify(),
				'headers'   => array(
					'Accept' => 'application/json',
					'Origin' => home_url(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data['reviews'] ) || ! is_array( $data['reviews'] ) ) {
			return array();
		}

		$review_schemas = array();

		// Limit to first 10 reviews for schema (Google recommendation).
		$reviews_to_include = array_slice( $data['reviews'], 0, 10 );

		foreach ( $reviews_to_include as $review ) {
			// Skip reviews with 0 rating.
			if ( empty( $review['rating'] ) || intval( $review['rating'] ) === 0 ) {
				continue;
			}

			$review_schema = array(
				'@type'        => 'Review',
				'reviewRating' => array(
					'@type'       => 'Rating',
					'ratingValue' => intval( $review['rating'] ),
					'bestRating'  => '5',
					'worstRating' => '1',
				),
				'author'       => array(
					'@type' => 'Person',
					'name'  => $review['author']['name'] ?? 'Anonymous',
				),
			);

			// Add review body if available.
			if ( ! empty( $review['body'] ) ) {
				$review_schema['reviewBody'] = wp_strip_all_tags( $review['body'] );
			}

			// Add review title/headline if available.
			if ( ! empty( $review['title'] ) ) {
				$review_schema['headline'] = wp_strip_all_tags( $review['title'] );
			}

			// Add date published.
			if ( ! empty( $review['created_at'] ) ) {
				$date = date_create( $review['created_at'] );
				if ( $date ) {
					$review_schema['datePublished'] = $date->format( 'Y-m-d' );
				}
			}

			$review_schemas[] = $review_schema;
		}

		// Cache for 4 hours.
		set_transient( $cache_key, $review_schemas, 4 * HOUR_IN_SECONDS );

		return $review_schemas;
	}

	/**
	 * Clear schema cache for a product.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	public function clear_schema_cache( $product_id ) {
		delete_transient( 'reviewbird_schema_reviews_' . $product_id );
	}

	/**
	 * Filter WooCommerce's structured data to inject ReviewBird review data.
	 *
	 * This hooks into WooCommerce's schema output to add aggregateRating and
	 * individual reviews from ReviewBird, replacing WooCommerce's native review data.
	 *
	 * @param array       $markup  The structured data markup array.
	 * @param \WC_Product $product The WooCommerce product object.
	 * @return array Modified markup with ReviewBird review data.
	 */
	public function filter_woocommerce_structured_data( $markup, $product ) {
		// Check if schema is enabled.
		if ( 'yes' !== get_option( 'reviewbird_enable_schema', 'yes' ) ) {
			return $markup;
		}

		// Check if store is connected.
		if ( ! reviewbird_is_store_connected() ) {
			return $markup;
		}

		$product_id = $product->get_id();

		// Get rating data from post meta (synced via webhook from ReviewBird).
		$avg_stars    = get_post_meta( $product_id, '_reviewbird_avg_stars', true );
		$review_count = get_post_meta( $product_id, '_reviewbird_reviews_count', true );

		// Add aggregateRating if we have ReviewBird data.
		if ( ! empty( $avg_stars ) && ! empty( $review_count ) && $review_count > 0 ) {
			$markup['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => floatval( $avg_stars ),
				'reviewCount' => intval( $review_count ),
				'bestRating'  => '5',
				'worstRating' => '1',
			);
		}

		// Add individual reviews from ReviewBird API.
		$reviews = $this->fetch_reviews_for_schema( $product_id );
		if ( ! empty( $reviews ) ) {
			$markup['review'] = $reviews;
		}

		return $markup;
	}
}
