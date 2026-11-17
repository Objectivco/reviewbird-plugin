<?php
/**
 * Background jobs: store health, review schema, and deactivation cleanup.
 *
 * Action Scheduler runs the API calls so page loads never wait on Reviewbird.
 *
 * @package reviewbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Action Scheduler can accept jobs yet.
 *
 * @return bool
 */
function reviewbird_scheduler_ready(): bool {
	return class_exists( '\ActionScheduler' ) && \ActionScheduler::is_initialized();
}

/**
 * Clear plugin jobs and caches. Network deactivation clears every site.
 *
 * @param bool $network_wide Whether this is a network deactivation.
 */
function reviewbird_deactivate( $network_wide = false ): void {
	if ( $network_wide && is_multisite() ) {
		foreach ( get_sites(
			array(
				'fields'     => 'ids',
				'number'     => 0,
				'network_id' => get_current_network_id(),
			)
		) as $site_id ) {
			switch_to_blog( $site_id );
			try {
				reviewbird_deactivate();
			} finally {
				restore_current_blog();
			}
		}
		return;
	}
	wp_clear_scheduled_hook( 'reviewbird_cleanup_health_actions' );
	if ( reviewbird_scheduler_ready() ) {
		as_unschedule_all_actions( 'reviewbird_refresh_health_status' );
		as_unschedule_all_actions( 'reviewbird_refresh_schema_reviews' );
		as_unschedule_all_actions( 'reviewbird_sync_product_rating' );
		as_unschedule_all_actions( 'reviewbird_cleanup_health_actions' );
	}
	delete_transient( 'reviewbird_star_color' );
	delete_option( 'reviewbird_preview_check_lock' );
}

/**
 * Schedule the five-minute health check once jobs from before 1.2 are gone.
 */
function reviewbird_schedule_health_check(): void {
	if ( ! reviewbird_scheduler_ready() ) {
		return;
	}
	if ( ! get_option( 'reviewbird_health_cleanup_status' ) ) {
		wp_clear_scheduled_hook( 'reviewbird_cleanup_health_actions' );
		if ( as_get_scheduled_actions(
			array(
				'hook'     => 'reviewbird_refresh_health_status',
				'per_page' => 1,
			),
			'ids'
		) ) {
			// Large queues must be canceled in the background, not on a page request.
			if ( ! as_next_scheduled_action( 'reviewbird_cleanup_health_actions' ) ) {
				as_enqueue_async_action( 'reviewbird_cleanup_health_actions', array(), 'reviewbird', true, 0 );
			}
			return;
		}
		// A new store has no health actions to remove.
		update_option( 'reviewbird_health_cleanup_status', 'complete', false );
	}
	if ( ! as_next_scheduled_action( 'reviewbird_refresh_health_status', array( 'periodic' ), 'reviewbird-health' ) ) {
		as_schedule_recurring_action( time(), 5 * MINUTE_IN_SECONDS, 'reviewbird_refresh_health_status', array( 'periodic' ), 'reviewbird-health', true, 5 );
	}
	if ( 'complete' !== get_option( 'reviewbird_health_cleanup_status' ) && ! as_next_scheduled_action( 'reviewbird_cleanup_health_actions' ) ) {
		as_schedule_single_action( time() + 60, 'reviewbird_cleanup_health_actions', array(), 'reviewbird', true, 0 );
	}
}

/** Schedule one immediate check after a connection notification. */
function reviewbird_schedule_immediate_health_check(): void {
	if ( reviewbird_scheduler_ready() && ! as_next_scheduled_action( 'reviewbird_refresh_health_status', array( 'immediate' ), 'reviewbird-health-immediate' ) ) {
		as_enqueue_async_action( 'reviewbird_refresh_health_status', array( 'immediate' ), 'reviewbird-health-immediate', true, 5 );
	}
}

/**
 * Run a health check job. Jobs from before 1.2 have no type and do no API work.
 *
 * @param string $type Check type.
 */
function reviewbird_run_health_check( $type = '' ): void {
	if ( in_array( $type, array( 'periodic', 'immediate' ), true ) ) {
		reviewbird_refresh_health_status();
	}
}

/** Remove old health actions and their logs in small batches after an upgrade. */
function reviewbird_cleanup_legacy_health_actions(): void {
	if ( 'complete' === get_option( 'reviewbird_health_cleanup_status' ) ) {
		return;
	}
	try {
		if ( ! get_option( 'reviewbird_health_cleanup_status' ) ) {
			as_unschedule_all_actions( 'reviewbird_refresh_health_status' );
			as_unschedule_all_actions( 'reviewbird_cleanup_health_actions' );
			update_option( 'reviewbird_health_cleanup_status', 'pending', false );
			reviewbird_schedule_health_check();
		}
		$ids       = as_get_scheduled_actions(
			array(
				'hook'     => 'reviewbird_refresh_health_status',
				'per_page' => 1000,
				'orderby'  => 'date',
				'order'    => 'ASC',
			),
			'ids'
		);
		$store     = \ActionScheduler::store();
		$deadline  = microtime( true ) + 10;
		$remaining = count( $ids ) === 1000;
		foreach ( $ids as $id ) {
			$status = $store->get_status( $id );
			if ( 'in-progress' === $status ) {
				$remaining = true;
			} elseif ( 'pending' !== $status ) {
				// AS also deletes the logs. Keep the new pending health checks.
				$store->delete_action( $id );
			}
			if ( microtime( true ) >= $deadline ) {
				$remaining = true;
				break;
			}
		}
		if ( ! $remaining ) {
			update_option( 'reviewbird_health_cleanup_status', 'complete', false );
			return;
		}
	} catch ( \Exception $error ) {
		wc_get_logger()->warning( 'Health status refresh failed: ' . $error->getMessage(), array( 'source' => 'reviewbird' ) );
	}
	as_schedule_single_action( time() + 60, 'reviewbird_cleanup_health_actions', array(), 'reviewbird', false, 0 );
}

/**
 * Refresh health status from the API and store it.
 *
 * Used by Action Scheduler and the admin Refresh button.
 *
 * @return array|\WP_Error Fresh status, or an error with the last good cache preserved.
 */
function reviewbird_refresh_health_status() {
	$domain   = wp_parse_url( home_url(), PHP_URL_HOST ) ?? '';
	$response = reviewbird_api_request( '/api/woocommerce/health?domain=' . rawurlencode( $domain ) );

	if ( is_wp_error( $response ) ) {
		$error_data = $response->get_error_data();
		if ( 404 !== ( $error_data['status'] ?? null ) || 'not_connected' !== ( $error_data['response']['status'] ?? null ) ) {
			wc_get_logger()->warning( 'Health status refresh failed: ' . $response->get_error_message(), array( 'source' => 'reviewbird' ) );
			return $response;
		}
		$response = $error_data['response'];
	}

	if ( ! is_array( $response ) || ! is_string( $response['status'] ?? null ) || '' === $response['status'] ) {
		return new \WP_Error( 'reviewbird_invalid_health_status', __( 'The connection response is invalid. Please try again.', 'reviewbird' ) );
	}

	if ( ! update_option( 'reviewbird_store_status', $response, false ) && get_option( 'reviewbird_store_status' ) !== $response ) {
		return new \WP_Error( 'reviewbird_health_cache_failed', __( 'The connection status could not be saved. Please try again.', 'reviewbird' ) );
	}
	set_transient( 'reviewbird_admin_health_status', $response, 5 * MINUTE_IN_SECONDS );
	if ( ! reviewbird_is_store_connected() && reviewbird_scheduler_ready() ) {
		as_unschedule_all_actions( 'reviewbird_refresh_schema_reviews' );
		as_unschedule_all_actions( 'reviewbird_sync_product_rating' );
	}

	$store_id = absint( $response['store_id'] ?? 0 );
	if ( $store_id && ! get_option( 'reviewbird_store_id' ) ) {
		update_option( 'reviewbird_store_id', $store_id );
	}
	if ( 'not_connected' === $response['status'] ) {
		delete_option( 'reviewbird_star_color' );
		delete_transient( 'reviewbird_star_color' );
	} elseif ( reviewbird_get_store_id() && false === get_transient( 'reviewbird_star_color' ) ) {
		reviewbird_refresh_star_color();
	}

	wc_get_logger()->debug( 'Health status refreshed: ' . $response['status'], array( 'source' => 'reviewbird' ) );
	return $response;
}

/**
 * Save the widget star color. The transient only spaces out requests.
 */
function reviewbird_refresh_star_color(): void {
	$store_id = reviewbird_get_store_id();
	if ( ! $store_id ) {
		return;
	}

	$response = reviewbird_api_request( '/api/widget/' . $store_id . '/config' );
	$color    = is_wp_error( $response ) ? null : ( $response['widget_settings']['star_color'] ?? null );
	$color    = is_string( $color ) ? sanitize_hex_color( $color ) : null;

	if ( $color ) {
		update_option( 'reviewbird_star_color', $color, false );
	}
	// Keep the last valid color and wait before another request.
	set_transient( 'reviewbird_star_color', 1, $color ? DAY_IN_SECONDS : HOUR_IN_SECONDS );
}

/**
 * Cancel a job type when its setting is turned off.
 *
 * @param mixed  $old_value Previous setting.
 * @param mixed  $value     Current setting.
 * @param string $option    Option name.
 */
function reviewbird_cancel_disabled_jobs( $old_value, $value, $option ): void {
	$hooks = array(
		'reviewbird_enable_schema' => 'reviewbird_refresh_schema_reviews',
		'reviewbird_enable_widget' => 'reviewbird_sync_product_rating',
	);
	if ( 'yes' !== $value && isset( $hooks[ $option ] ) && reviewbird_scheduler_ready() ) {
		as_unschedule_all_actions( $hooks[ $option ] );
	}
}

/**
 * Enqueue one product job unless the same product is already queued.
 *
 * @param string $hook Action hook.
 * @param int    $product_id Product ID.
 */
function reviewbird_enqueue_product_action( string $hook, int $product_id ): void {
	$product_id = reviewbird_product_id( $product_id );
	if ( ! as_next_scheduled_action( $hook, array( $product_id ) ) ) {
		// Check arguments here: older AS versions apply $unique to the whole hook and group.
		// ponytail: Concurrent requests can add a duplicate refresh; use native uniqueness when old AS support ends.
		as_enqueue_async_action( $hook, array( $product_id ), 'reviewbird' );
	}
}

/**
 * Refresh schema after a rating update, including when schema output is disabled.
 *
 * @param int $product_id Product ID.
 */
function reviewbird_invalidate_schema_reviews( int $product_id ): void {
	delete_transient( 'reviewbird_schema_refresh_' . reviewbird_product_meta_id( $product_id ) );
	reviewbird_schedule_schema_refresh( $product_id );
}

/**
 * Remove product jobs only when no translation can use their results.
 *
 * @param int           $post_id Deleted or trashed post ID.
 * @param \WP_Post|null $post Post, when supplied by the deletion hook.
 */
function reviewbird_cancel_deleted_product_jobs( int $post_id, $post = null ): void {
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
		if ( ! reviewbird_scheduler_ready() ) {
			continue;
		}
		foreach ( array( 'reviewbird_refresh_schema_reviews', 'reviewbird_sync_product_rating' ) as $hook ) {
			$pending = as_next_scheduled_action( $hook, array( $id ) );
			as_unschedule_all_actions( $hook, array( $id ) );
			if ( $survivor && $pending ) {
				reviewbird_enqueue_product_action( $hook, $shared_id );
			}
		}
	}
}

/**
 * Queue a schema refresh for a product whose cached schema has expired.
 *
 * @param int $product_id WooCommerce product ID.
 */
function reviewbird_schedule_schema_refresh( $product_id ): void {
	if ( ! is_numeric( $product_id ) || ! reviewbird_scheduler_ready() ) {
		return;
	}
	$product_id = reviewbird_product_meta_id( absint( $product_id ) );
	if ( ! $product_id || ! reviewbird_is_schema_enabled() || ! reviewbird_is_store_connected() || ! reviewbird_find_product( $product_id )
		|| get_transient( 'reviewbird_schema_refresh_' . $product_id ) ) {
		return;
	}

	reviewbird_enqueue_product_action( 'reviewbird_refresh_schema_reviews', $product_id );
}

/**
 * Fetch up to ten reviews for schema and store them in product meta.
 *
 * @param int $product_id WooCommerce product ID.
 */
function reviewbird_refresh_schema_reviews( int $product_id ): void {
	$store_id = reviewbird_get_store_id();
	if ( ! reviewbird_is_schema_enabled() || ! reviewbird_is_store_connected() || ! $store_id ) {
		return;
	}

	$shared_id = reviewbird_product_id( $product_id );
	$product   = reviewbird_find_product( $shared_id );
	if ( ! $product ) {
		return;
	}
	// Keep the last good data and limit retries during an API outage.
	set_transient( 'reviewbird_schema_refresh_' . $product->get_id(), true, 5 * MINUTE_IN_SECONDS );
	$response = reviewbird_api_request( "/api/public/{$store_id}/{$shared_id}?context=schema&page=1", null, 'GET', 10 );
	if ( is_wp_error( $response ) || ! is_array( $response['reviews'] ?? null ) ) {
		return;
	}
	$schemas = array_values( array_filter( array_map( 'reviewbird_review_schema', array_slice( $response['reviews'], 0, 10 ) ) ) );

	// Re-resolve the stable identity: the product can be deleted during the request.
	$product = reviewbird_find_product( $shared_id );
	if ( ! $product ) {
		return;
	}
	$product_id = $product->get_id();
	set_transient( 'reviewbird_schema_refresh_' . $product_id, true, DAY_IN_SECONDS );

	if ( ! $response['reviews'] ) {
		delete_post_meta( $product_id, '_reviewbird_schema_reviews' );
		return;
	}
	update_post_meta( $product_id, '_reviewbird_schema_reviews', $schemas );
}

/**
 * Build one schema.org Review, or null for an unrated review.
 *
 * @param array $review Raw review data.
 * @return array|null
 */
function reviewbird_review_schema( array $review ): ?array {
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
	$date = empty( $review['created_at'] ) ? false : date_create( $review['created_at'] );
	if ( $date ) {
		$schema['datePublished'] = $date->format( 'Y-m-d' );
	}

	return $schema;
}

/**
 * Replace WooCommerce's review data in product structured data.
 *
 * Reads meta that the background jobs fill. No API calls run here.
 *
 * @param array       $markup  Structured data.
 * @param \WC_Product $product Product.
 * @return array
 */
function reviewbird_filter_structured_data( $markup, $product ) {
	if ( ! reviewbird_is_schema_enabled() || ! reviewbird_is_store_connected() ) {
		return $markup;
	}

	$product_id   = reviewbird_product_meta_id( $product->get_id() );
	$review_count = get_post_meta( $product_id, '_reviewbird_reviews_count', true );
	if ( '0' === (string) $review_count ) {
		unset( $markup['aggregateRating'], $markup['review'] );
		return $markup;
	}

	// Use the service's average, which the visible stars also show.
	$counts       = get_post_meta( $product_id, '_reviewbird_rating_counts', true );
	$rating_value = (float) get_post_meta( $product_id, '_reviewbird_avg_stars', true );
	$review_count = (int) $review_count;
	// Meta saved before rating counts were stored rated every review.
	$rating_count = is_array( $counts ) ? array_sum( array_map( fn( $stars ) => (int) ( $counts[ $stars ] ?? 0 ), range( 1, 5 ) ) ) : $review_count;

	$reviews = get_post_meta( $product_id, '_reviewbird_schema_reviews', true );
	if ( is_array( $reviews ) && $reviews ) {
		$markup['review'] = $reviews;
	}
	if ( $review_count > 0 && $rating_count > 0 && $rating_value >= 1 ) {
		$markup['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $rating_value,
			'ratingCount' => $rating_count,
			'reviewCount' => $review_count,
			'bestRating'  => '5',
			'worstRating' => '1',
		);
		reviewbird_schedule_schema_refresh( $product_id );
	}

	return $markup;
}
