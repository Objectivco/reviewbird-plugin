<?php
/**
 * Schema.org markup integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

use reviewbird\Api\Client;

/**
 * Schema markup class for SEO.
 */
class SchemaMarkup {

	/**
	 * API client instance.
	 *
	 * @var Client
	 */
	private $api_client;

	/**
	 * Initialize schema markup integration.
	 */
	public function __construct() {
		$this->api_client = new Client();
	}

	/**
	 * Generate Product schema with reviews for a WooCommerce product.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array|null Schema data array or null if product not found.
	 */
	public function generate_product_schema( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return null;
		}

		// Build base product schema.
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Product',
			'name'        => $product->get_name(),
			'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'url'         => get_permalink( $product_id ),
		);

		// Add product image.
		$image_id = $product->get_image_id();
		if ( $image_id ) {
			$image_url = wp_get_attachment_url( $image_id );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// Add SKU if available.
		$sku = $product->get_sku();
		if ( $sku ) {
			$schema['sku'] = $sku;
		}

		// Add brand if available (from product vendor/brand taxonomy or attribute).
		$brand = $this->get_product_brand( $product );
		if ( $brand ) {
			$schema['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}

		// Add offer information.
		$schema['offers'] = array(
			'@type'         => 'Offer',
			'url'           => get_permalink( $product_id ),
			'priceCurrency' => get_woocommerce_currency(),
			'price'         => $product->get_price(),
			'availability'  => $this->get_product_availability( $product ),
		);

		// Add valid-through date for sale prices.
		if ( $product->is_on_sale() && $product->get_date_on_sale_to() ) {
			$schema['offers']['priceValidUntil'] = $product->get_date_on_sale_to()->format( 'Y-m-d' );
		}

		// Get rating data from post meta (updated via webhook).
		$avg_stars = get_post_meta( $product_id, '_reviewbird_avg_stars', true );
		$review_count = get_post_meta( $product_id, '_reviewbird_reviews_count', true );

		// Add aggregate rating if available.
		if ( ! empty( $avg_stars ) && ! empty( $review_count ) && $review_count > 0 ) {
			$schema['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => floatval( $avg_stars ),
				'reviewCount' => intval( $review_count ),
				'bestRating'  => '5',
				'worstRating' => '1',
			);
		}

		// Fetch individual reviews from reviewbird API.
		$reviews = $this->fetch_reviews_for_schema( $product_id );
		if ( ! empty( $reviews ) ) {
			$schema['review'] = $reviews;
		}

		return $schema;
	}

	/**
	 * Fetch approved reviews from reviewbird API for schema markup.
	 *
	 * @param int $product_id WooCommerce product ID.
	 * @return array Array of Review schema objects.
	 */
	private function fetch_reviews_for_schema( $product_id ) {
		$store_id = $this->api_client->get_store_id_for_frontend();

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
			reviewbird_get_api_url() . "/api/stores/{$store_id}/products/{$product_id}/reviews",
			array(
				'timeout'   => 10,
				'sslverify' => ! reviewbird_should_disable_ssl_verify(),
				'headers'   => array(
					'Accept' => 'application/json',
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
	 * Get product brand/vendor name.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Brand name or null.
	 */
	private function get_product_brand( $product ) {
		// Try to get brand from common brand taxonomies.
		$brand_taxonomies = array( 'product_brand', 'pwb-brand', 'yith_product_brand' );

		foreach ( $brand_taxonomies as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				$terms = get_the_terms( $product->get_id(), $taxonomy );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					return $terms[0]->name;
				}
			}
		}

		// Try to get from product attribute.
		$brand_attr = $product->get_attribute( 'brand' );
		if ( $brand_attr ) {
			return $brand_attr;
		}

		// Try to get vendor meta (some themes use this).
		$vendor = get_post_meta( $product->get_id(), '_vendor', true );
		if ( $vendor ) {
			return $vendor;
		}

		return null;
	}

	/**
	 * Get schema.org availability status for product.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string Schema.org availability URL.
	 */
	private function get_product_availability( $product ) {
		if ( ! $product->is_in_stock() ) {
			return 'https://schema.org/OutOfStock';
		}

		// Check if product has limited stock.
		if ( $product->managing_stock() && $product->get_stock_quantity() <= get_option( 'woocommerce_notify_low_stock_amount' ) ) {
			return 'https://schema.org/LimitedAvailability';
		}

		// Check backorder status.
		if ( $product->is_on_backorder() ) {
			return 'https://schema.org/PreOrder';
		}

		return 'https://schema.org/InStock';
	}

	/**
	 * Output schema markup as JSON-LD script tag.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	public function output_product_schema( $product_id ) {
		// Check if schema is enabled.
		$enabled = get_option( 'reviewbird_enable_schema', 'yes' );
		if ( 'yes' !== $enabled ) {
			return;
		}

		$schema = $this->generate_product_schema( $product_id );

		if ( empty( $schema ) ) {
			return;
		}

		// Allow developers to modify schema before output.
		$schema = apply_filters( 'reviewbird_product_schema', $schema, $product_id );

		// Output JSON-LD script tag.
		echo '<script type="application/ld+json">';
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		echo '</script>' . "\n";
	}

	/**
	 * Clear schema cache for a product.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	public function clear_schema_cache( $product_id ) {
		delete_transient( 'reviewbird_schema_reviews_' . $product_id );
	}
}
