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
	 * Maximum number of reviews to include in schema (Google recommendation).
	 */
	private const MAX_SCHEMA_REVIEWS = 10;

	/**
	 * Cache duration for schema reviews in seconds (4 hours).
	 */
	private const CACHE_DURATION = 4 * HOUR_IN_SECONDS;

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

		$cache_key = 'reviewbird_schema_reviews_' . $product_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$reviews = $this->fetch_reviews_from_api( $store_id, $product_id );

		if ( empty( $reviews ) ) {
			return array();
		}

		$review_schemas = $this->build_review_schemas( $reviews );

		set_transient( $cache_key, $review_schemas, self::CACHE_DURATION );

		return $review_schemas;
	}

	/**
	 * Fetch reviews from the reviewbird API.
	 *
	 * @param int $store_id   reviewbird store ID.
	 * @param int $product_id WooCommerce product ID.
	 * @return array Raw reviews array or empty array on failure.
	 */
	private function fetch_reviews_from_api( $store_id, $product_id ) {
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

		return $data['reviews'];
	}

	/**
	 * Build schema objects from raw review data.
	 *
	 * @param array $reviews Raw reviews from API.
	 * @return array Array of Review schema objects.
	 */
	private function build_review_schemas( array $reviews ) {
		$schemas         = array();
		$reviews_limited = array_slice( $reviews, 0, self::MAX_SCHEMA_REVIEWS );

		foreach ( $reviews_limited as $review ) {
			$schema = $this->build_single_review_schema( $review );

			if ( $schema ) {
				$schemas[] = $schema;
			}
		}

		return $schemas;
	}

	/**
	 * Build a single review schema object.
	 *
	 * @param array $review Raw review data.
	 * @return array|null Review schema object or null if invalid.
	 */
	private function build_single_review_schema( array $review ) {
		$rating = intval( $review['rating'] ?? 0 );

		if ( 0 === $rating ) {
			return null;
		}

		$schema = array(
			'@type'        => 'Review',
			'reviewRating' => array(
				'@type'       => 'Rating',
				'ratingValue' => $rating,
				'bestRating'  => '5',
				'worstRating' => '1',
			),
			'author'       => array(
				'@type' => 'Person',
				'name'  => $review['author']['name'] ?? 'Anonymous',
			),
		);

		if ( ! empty( $review['body'] ) ) {
			$schema['reviewBody'] = wp_strip_all_tags( $review['body'] );
		}

		if ( ! empty( $review['title'] ) ) {
			$schema['headline'] = wp_strip_all_tags( $review['title'] );
		}

		$date_published = $this->format_date( $review['created_at'] ?? '' );
		if ( $date_published ) {
			$schema['datePublished'] = $date_published;
		}

		return $schema;
	}

	/**
	 * Format a date string to Y-m-d format.
	 *
	 * @param string $date_string Date string to format.
	 * @return string|null Formatted date or null if invalid.
	 */
	private function format_date( $date_string ) {
		if ( empty( $date_string ) ) {
			return null;
		}

		$date = date_create( $date_string );

		return $date ? $date->format( 'Y-m-d' ) : null;
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
		if ( ! $this->is_schema_enabled() ) {
			return $markup;
		}

		$product_id = $product->get_id();

		$aggregate_rating = $this->build_aggregate_rating( $product_id );
		if ( $aggregate_rating ) {
			$markup['aggregateRating'] = $aggregate_rating;
		}

		$reviews = $this->fetch_reviews_for_schema( $product_id );
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
	private function is_schema_enabled() {
		$schema_setting_enabled = get_option( 'reviewbird_enable_schema', 'yes' ) === 'yes';

		return $schema_setting_enabled && reviewbird_is_store_connected();
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
