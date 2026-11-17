<?php
/**
 * Schema reviews scheduler for reviewbird.
 *
 * Uses Action Scheduler to refresh schema reviews in the background
 * when ratings are updated, preventing blocking API calls on page loads.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schema scheduler class.
 */
class SchemaScheduler {

	/**
	 * Action hook name for schema reviews refresh.
	 */
	private const ACTION_HOOK = 'reviewbird_refresh_schema_reviews';

	/**
	 * Product meta key for storing schema reviews.
	 */
	public const META_KEY = '_reviewbird_schema_reviews';

	/**
	 * Maximum number of reviews to include in schema (Google recommendation).
	 */
	private const MAX_SCHEMA_REVIEWS = 10;

	/**
	 * Initialize the scheduler.
	 */
	public function init(): void {
		add_action( self::ACTION_HOOK, array( $this, 'refresh_schema_reviews' ), 10, 1 );
		add_action( 'reviewbird_rating_updated', array( $this, 'invalidate_schema_reviews' ), 10, 1 );
		add_action( 'update_option_reviewbird_enable_schema', array( $this, 'cancel_disabled_schema_actions' ), 10, 2 );
		add_action( 'before_delete_post', array( $this, 'cancel_deleted_product_actions' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'cancel_deleted_product_actions' ), 10, 1 );
	}

	/**
	 * Invalidate the refresh deadline, including when schema output is disabled.
	 *
	 * @param int $product_id Product ID.
	 */
	public function invalidate_schema_reviews( int $product_id ): void {
		delete_transient( 'reviewbird_schema_refresh_' . reviewbird_product_meta_id( $product_id ) );
		$this->schedule_schema_refresh( $product_id );
	}

	/**
	 * Cancel pending schema actions when schema output is disabled.
	 *
	 * @param string $old_value Previous setting.
	 * @param string $value Current setting.
	 */
	public function cancel_disabled_schema_actions( $old_value, $value ): void {
		if ( 'yes' !== $value && class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
			as_unschedule_all_actions( self::ACTION_HOOK );
		}
	}

	/**
	 * Remove product jobs only when no translation can use their results.
	 *
	 * @param int           $post_id Deleted or trashed post ID.
	 * @param \WP_Post|null $post Post, when supplied by the deletion hook.
	 */
	public function cancel_deleted_product_actions( int $post_id, $post = null ): void {
		$post = $post ?? get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			return;
		}
		$shared_id = reviewbird_product_id( $post_id );
		$survivor  = false;
		foreach ( array_diff( reviewbird_product_translation_ids( $post_id ), array( $post_id ) ) as $id ) {
			$product = wc_get_product( $id );
			if ( $product && 'trash' !== $product->get_status() ) {
				$survivor = true;
				break;
			}
		}
		foreach ( array_unique( array( $post_id, $shared_id ) ) as $id ) {
			if ( $survivor && $id === $shared_id ) {
				continue;
			}
			delete_transient( 'reviewbird_schema_refresh_' . $id );
			delete_transient( 'reviewbird_rating_sync_' . $id );
			if ( class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized() ) {
				foreach ( array( self::ACTION_HOOK, 'reviewbird_sync_product_rating' ) as $hook ) {
					$pending = as_next_scheduled_action( $hook, array( $id ) );
					as_unschedule_all_actions( $hook, array( $id ) );
					if ( $survivor && $pending ) {
						reviewbird_enqueue_product_action( $hook, $shared_id );
					}
				}
			}
		}
	}

	/**
	 * Schedule a schema reviews refresh for a product.
	 *
	 * Hooked into 'reviewbird_rating_updated' action from RatingsController.
	 *
	 * @param int $product_id   WooCommerce product ID.
	 */
	public function schedule_schema_refresh( $product_id ): void {
		if ( ! is_numeric( $product_id ) || ! class_exists( '\ActionScheduler' ) || ! \ActionScheduler::is_initialized() ) {
			return;
		}
		$product_id = reviewbird_product_meta_id( absint( $product_id ) );
		if ( ! $product_id || ! reviewbird_is_schema_enabled() || ! reviewbird_is_store_connected() || ! reviewbird_find_product( $product_id )
			|| get_transient( 'reviewbird_schema_refresh_' . $product_id ) ) {
			return;
		}

		reviewbird_enqueue_product_action( self::ACTION_HOOK, $product_id );
	}

	/**
	 * Refresh schema reviews from the API and store in product meta.
	 *
	 * This runs in the background via Action Scheduler.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	public function refresh_schema_reviews( int $product_id ): void {
		if ( ! reviewbird_is_schema_enabled() || ! reviewbird_is_store_connected() ) {
			return;
		}
		$store_id = reviewbird_get_store_id();

		if ( ! $store_id ) {
			return;
		}

		$product_id = reviewbird_product_meta_id( $product_id );
		if ( ! reviewbird_find_product( $product_id ) ) {
			return;
		}
		// Keep the last good data and limit retries during an API outage.
		set_transient( 'reviewbird_schema_refresh_' . $product_id, true, 5 * MINUTE_IN_SECONDS );
		$reviews = $this->fetch_reviews_from_api( $store_id, reviewbird_product_id( $product_id ) );
		if ( is_wp_error( $reviews ) ) {
			return;
		}
		$product_id = reviewbird_product_meta_id( $product_id );
		if ( ! reviewbird_find_product( $product_id ) ) {
			return;
		}
		$review_schemas = $this->build_review_schemas( $reviews );
		set_transient( 'reviewbird_schema_refresh_' . $product_id, true, DAY_IN_SECONDS );

		if ( empty( $reviews ) ) {
			delete_post_meta( $product_id, self::META_KEY );
			return;
		}

		update_post_meta( $product_id, self::META_KEY, $review_schemas );

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->debug(
				sprintf( 'Schema reviews refreshed for product %d: %d reviews', $product_id, count( $review_schemas ) ),
				array( 'source' => 'reviewbird' )
			);
		}
	}

	/**
	 * Fetch reviews from the reviewbird API.
	 *
	 * @param int $store_id   reviewbird store ID.
	 * @param int $product_id WooCommerce product ID.
	 * @return array|\WP_Error Raw reviews, or an error that preserves the last good cache.
	 */
	private function fetch_reviews_from_api( int $store_id, int $product_id ) {
		$response = wp_remote_get(
			reviewbird_get_api_url() . "/api/public/{$store_id}/{$product_id}?context=schema&page=1",
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
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning(
					sprintf( 'Schema reviews fetch failed for product %d: %s', $product_id, $response->get_error_message() ),
					array( 'source' => 'reviewbird' )
				);
			}
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 || ! is_array( $data['reviews'] ?? null ) ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning(
					sprintf( 'Schema reviews fetch failed for product %d: Invalid schema reviews response.', $product_id ),
					array( 'source' => 'reviewbird' )
				);
			}
			return new \WP_Error( 'reviewbird_invalid_schema_response', 'Invalid schema reviews response.' );
		}

		return $data['reviews'];
	}

	/**
	 * Build schema objects from raw review data.
	 *
	 * @param array $reviews Raw reviews from API.
	 * @return array Array of Review schema objects.
	 */
	private function build_review_schemas( array $reviews ): array {
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
	private function build_single_review_schema( array $review ): ?array {
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
				'name'  => wp_strip_all_tags( $review['author']['name'] ?? 'Anonymous' ),
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
	private function format_date( string $date_string ): ?string {
		if ( empty( $date_string ) ) {
			return null;
		}

		$date = date_create( $date_string );

		return $date ? $date->format( 'Y-m-d' ) : null;
	}
}
