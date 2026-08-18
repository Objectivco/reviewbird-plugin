<?php
/**
 * Core procedural functions for reviewbird WordPress plugin.
 *
 * @package reviewbird
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// ENVIRONMENT FUNCTIONS
// =============================================================================

/**
 * Determine the current environment.
 *
 * @return string 'production', 'staging', or 'development'
 */
function reviewbird_get_environment(): string {
	if ( defined( 'REVIEWBIRD_ENVIRONMENT' ) ) {
		return REVIEWBIRD_ENVIRONMENT;
	}

	if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
		return 'production';
	}

	$environment_map = array(
		'production' => 'production',
		'staging'    => 'staging',
	);

	return $environment_map[ WP_ENVIRONMENT_TYPE ] ?? 'production';
}

/**
 * Get the appropriate API URL based on environment.
 *
 * @return string The API URL for the current environment.
 */
function reviewbird_get_api_url(): string {
	$api_urls = array(
		'production'  => REVIEWBIRD_API_URL_PRODUCTION,
		'staging'     => REVIEWBIRD_API_URL_STAGING,
		'development' => REVIEWBIRD_API_URL_DEVELOPMENT,
	);

	return $api_urls[ reviewbird_get_environment() ] ?? REVIEWBIRD_API_URL_DEVELOPMENT;
}

/**
 * Determine if SSL verification should be disabled for HTTP requests.
 *
 * @return bool True to disable SSL verification, false to enable it.
 */
function reviewbird_should_disable_ssl_verify(): bool {
	if ( 'development' === reviewbird_get_environment() ) {
		return true;
	}

	if ( defined( 'REVIEWBIRD_DISABLE_SSL_VERIFY' ) ) {
		return (bool) REVIEWBIRD_DISABLE_SSL_VERIFY;
	}

	return false;
}

// =============================================================================
// API FUNCTIONS (replacing Client class)
// =============================================================================

/**
 * Make API requests to reviewbird backend.
 *
 * @param string $endpoint The API endpoint to call.
 * @param array  $data     Optional. Data to send with the request.
 * @param string $method   Optional. HTTP method. Default 'GET'.
 * @return array|\WP_Error The API response or WP_Error on failure.
 */
function reviewbird_api_request( $endpoint, $data = null, $method = 'GET' ) {
	$api_url = reviewbird_get_api_url();

	$args = array(
		'headers'   => array(
			'Content-Type' => 'application/json',
			'Origin'       => home_url(),
			'User-Agent'   => 'reviewbird WordPress Plugin/' . REVIEWBIRD_VERSION,
		),
		'timeout'   => 30,
		'method'    => strtoupper( $method ),
		'sslverify' => ! reviewbird_should_disable_ssl_verify(),
	);

	$methods_with_body = array( 'POST', 'PUT', 'PATCH' );
	if ( $data && in_array( $method, $methods_with_body, true ) ) {
		$args['body'] = wp_json_encode( $data );
	}

	$url      = $api_url . $endpoint;
	$response = wp_remote_request( $url, $args );

	if ( is_wp_error( $response ) ) {
		reviewbird_log_api_error( 'HTTP Error', $response->get_error_message(), $endpoint );
		return $response;
	}

	$response_code = wp_remote_retrieve_response_code( $response );
	$body          = wp_remote_retrieve_body( $response );
	$decoded       = json_decode( $body, true );

	if ( $response_code >= 400 ) {
		$error_message = $decoded['message'] ?? __( 'API request failed', 'reviewbird' );

		reviewbird_log_api_error(
			'API Error',
			$error_message,
			$endpoint,
			$response_code,
			array(
				'response_body'    => $body,
				'decoded_response' => $decoded,
			)
		);

		return new \WP_Error(
			'reviewbird_api_error',
			$error_message,
			array(
				'status'   => $response_code,
				'response' => $decoded,
			)
		);
	}

	// Log any errors in successful responses (partial failures).
	if ( ! empty( $decoded['errors'] ) && function_exists( 'wc_get_logger' ) ) {
		$logger = wc_get_logger();
		$logger->warning(
			sprintf(
				'API request to %s returned %d errors in response',
				$endpoint,
				count( $decoded['errors'] )
			),
			array(
				'source' => 'reviewbird',
				'errors' => $decoded['errors'],
			)
		);
	}

	return $decoded;
}

/**
 * Get store ID safe for frontend use.
 *
 * @return int|null Store ID or null if not available.
 */
function reviewbird_get_store_id(): ?int {
	$store_id = get_option( 'reviewbird_store_id' );

	return $store_id ? absint( $store_id ) : null;
}

/**
 * Get MD5 hash of store ID for API authentication.
 *
 * @return string|null Hash or null if store ID not available.
 */
function reviewbird_get_store_hash(): ?string {
	$store_id = reviewbird_get_store_id();
	return $store_id ? md5( (string) $store_id ) : null;
}

/**
 * Fetch product reviews from the reviewbird API.
 *
 * @param array $product_ids Array of product IDs to fetch reviews for (max 50). Default empty array returns all store reviews.
 * @param int   $per_page    Number of reviews per page (1-20). Default 5.
 * @param array $args        Optional arguments:
 *                           - 'sort'   string Sort order: newest, oldest, rating_high, rating_low, most_helpful.
 *                           - 'rating' int    Filter by star rating (1-5).
 *                           - 'page'   int    Page number for pagination (1+).
 * @return array|\WP_Error The API response array on success, or WP_Error on failure.
 */
function reviewbird_get_product_reviews( array $product_ids = array(), int $per_page = 5, array $args = array() ) {
	$endpoint = '/api/v1/stores/{store_id}/products/reviews';

	// Validate store connection.
	$store_id = reviewbird_get_store_id();
	if ( ! $store_id ) {
		reviewbird_log_api_error( 'Validation Error', 'Store not connected', $endpoint );
		return new \WP_Error(
			'reviewbird_not_connected',
			__( 'Store is not connected to Reviewbird.', 'reviewbird' )
		);
	}

	// Get API key.
	$store_hash = reviewbird_get_store_hash();
	if ( ! $store_hash ) {
		reviewbird_log_api_error( 'Validation Error', 'Missing Store Hash', $endpoint );
		return new \WP_Error(
			'reviewbird_missing_api_key',
			__( 'Unable to generate API key for authentication.', 'reviewbird' )
		);
	}

	// Validate and sanitize product IDs.
	$product_ids = array_map( 'absint', $product_ids );
	$product_ids = array_filter( $product_ids );
	$product_ids = array_unique( $product_ids );
	$product_ids = array_values( $product_ids );

	if ( ! empty( $product_ids ) && count( $product_ids ) > 50 ) {
		reviewbird_log_api_error( 'Validation Error', 'Too many product IDs (max 50)', $endpoint );
		return new \WP_Error(
			'reviewbird_too_many_products',
			__( 'Maximum of 50 product IDs allowed per request.', 'reviewbird' )
		);
	}

	// Validate per_page (clamp to 1-20).
	$per_page = max( 1, min( 20, $per_page ) );

	// Build query parameters.
	$query_params = array(
		'per_page' => $per_page,
	);

	if ( ! empty( $product_ids ) ) {
		$query_params['product_ids'] = implode( ',', $product_ids );
	}

	// Validate and add optional sort parameter.
	if ( ! empty( $args['sort'] ) ) {
		$valid_sorts = array( 'newest', 'oldest', 'rating_high', 'rating_low', 'most_helpful' );
		if ( in_array( $args['sort'], $valid_sorts, true ) ) {
			$query_params['sort'] = $args['sort'];
		}
	}

	// Validate and add optional rating parameter.
	if ( isset( $args['rating'] ) ) {
		$rating = absint( $args['rating'] );
		$rating = max( 1, min( 5, $rating ) );
		$query_params['min_rating'] = $rating;
	}

	// Validate and add optional page parameter.
	if ( isset( $args['page'] ) ) {
		$page = absint( $args['page'] );
		if ( $page >= 1 ) {
			$query_params['page'] = $page;
		}
	}

	// Build the full URL.
	$api_url  = reviewbird_get_api_url();
	$endpoint = '/api/v1/stores/' . $store_id . '/products/reviews';
	$url      = $api_url . $endpoint . '?' . http_build_query( $query_params );

	// Make the request.
	$response = wp_remote_get(
		$url,
		array(
			'headers'   => array(
				'X-Store-Hash'  => $store_hash,
				'User-Agent' => 'reviewbird WordPress Plugin/' . REVIEWBIRD_VERSION,
			),
			'timeout'   => 30,
			'sslverify' => ! reviewbird_should_disable_ssl_verify(),
		)
	);

	// Handle connection errors.
	if ( is_wp_error( $response ) ) {
		reviewbird_log_api_error( 'HTTP Error', $response->get_error_message(), $endpoint );
		return $response;
	}

	// Handle HTTP error responses.
	$response_code = wp_remote_retrieve_response_code( $response );
	$body          = wp_remote_retrieve_body( $response );

	if ( $response_code >= 400 ) {
		$decoded       = json_decode( $body, true );
		$error_message = $decoded['message'] ?? __( 'API request failed', 'reviewbird' );

		reviewbird_log_api_error(
			'API Error',
			$error_message,
			$endpoint,
			$response_code,
			array(
				'response_body'    => $body,
				'decoded_response' => $decoded,
			)
		);

		return new \WP_Error(
			'reviewbird_api_error',
			$error_message,
			array(
				'status'   => $response_code,
				'response' => $decoded,
			)
		);
	}

	// Decode JSON response.
	$decoded = json_decode( $body, true );
	if ( null === $decoded && json_last_error() !== JSON_ERROR_NONE ) {
		reviewbird_log_api_error(
			'JSON Error',
			json_last_error_msg(),
			$endpoint,
			$response_code,
			array( 'response_body' => $body )
		);

		return new \WP_Error(
			'reviewbird_json_error',
			__( 'Failed to decode API response.', 'reviewbird' )
		);
	}

	return $decoded;
}

/**
 * Log API errors for debugging.
 *
 * @param string   $type     Error type.
 * @param string   $message  Error message.
 * @param string   $endpoint API endpoint.
 * @param int|null $code     Optional. HTTP response code.
 * @param array    $context  Optional. Additional context data.
 * @return void
 */
function reviewbird_log_api_error( string $type, string $message, string $endpoint, ?int $code = null, array $context = array() ): void {
	$log_message = sprintf(
		'%s: %s [Endpoint: %s]',
		$type,
		$message,
		$endpoint
	);

	if ( $code ) {
		$log_message .= sprintf( ' [Code: %d]', $code );
	}

	// Use WooCommerce logger if available.
	if ( function_exists( 'wc_get_logger' ) ) {
		$logger = wc_get_logger();
		$logger->error(
			$log_message,
			array_merge( array( 'source' => 'reviewbird' ), $context )
		);
	}
}

// =============================================================================
// HEALTH CHECK FUNCTIONS (replacing HealthChecker class)
// =============================================================================

/**
 * Get store status from cached option.
 *
 * This reads from an option that is populated by the HealthScheduler
 * running via Action Scheduler in the background. No API calls are
 * made during page loads.
 *
 * @param bool $skip_cache Deprecated. Kept for backward compatibility.
 * @return array|null Status array or null if not yet populated.
 */
function reviewbird_get_store_status( bool $skip_cache = false ): ?array {
	$status = get_option( 'reviewbird_store_status' );

	return is_array( $status ) ? $status : null;
}

/**
 * Clear cached store status.
 *
 * @return void
 */
function reviewbird_clear_status_cache(): void {
	delete_option( 'reviewbird_store_status' );
}

/**
 * Check if the store is connected and can show the widget.
 *
 * A store is considered connected when it is healthy or syncing AND has an active subscription.
 *
 * @return bool True if store is connected.
 */
function reviewbird_is_store_connected(): bool {
	$status = reviewbird_get_store_status();

	if ( ! $status ) {
		return false;
	}

	$valid_statuses          = array( 'healthy', 'syncing' );
	$current_status          = $status['status'] ?? '';
	$has_active_subscription = $status['has_active_subscription'] ?? false;

	return in_array( $current_status, $valid_statuses, true ) && $has_active_subscription;
}

/**
 * Check if the reviewbird widget setting is enabled.
 *
 * @return bool True if widget is enabled.
 */
function reviewbird_is_widget_enabled(): bool {
	return get_option( 'reviewbird_enable_widget', 'no' ) === 'yes';
}

/**
 * Check if the reviewbird schema setting is enabled.
 *
 * @return bool True if schema is enabled.
 */
function reviewbird_is_schema_enabled(): bool {
	return get_option( 'reviewbird_enable_schema', 'no' ) === 'yes';
}

/**
 * Check if force reviews open setting is enabled.
 *
 * @return bool True if force reviews open is enabled.
 */
function reviewbird_is_force_reviews_open(): bool {
	return get_option( 'reviewbird_force_reviews_open', 'no' ) === 'yes';
}

/**
 * Check if the reviewbird widget can be shown.
 *
 * Widget displays when the store is connected and widget setting is enabled.
 *
 * @return bool True if widget can be shown.
 */
function reviewbird_can_show_widget(): bool {
	return reviewbird_is_store_connected() && reviewbird_is_widget_enabled();
}

/**
 * Get cached product reviews, using a transient to avoid repeated API calls.
 *
 * @param int $product_id WooCommerce product ID.
 * @return array API response array (with 'reviews' key) or empty array on failure.
 */
function reviewbird_get_cached_product_reviews( int $product_id ): array {
	$transient_key = 'reviewbird_ssr_' . $product_id;
	$cached        = get_transient( $transient_key );

	if ( false !== $cached ) {
		return $cached;
	}

	$response = reviewbird_get_product_reviews( array( $product_id ), 10 );

	if ( is_wp_error( $response ) ) {
		return array();
	}

	set_transient( $transient_key, $response, 4 * HOUR_IN_SECONDS );

	return $response;
}

/**
 * Render server-side review HTML for SEO.
 *
 * @param array $response API response array containing 'reviews' key.
 * @return string Semantic HTML string of reviews.
 */
function reviewbird_render_ssr_reviews( array $response ): string {
	if ( empty( $response['reviews'] ) || ! is_array( $response['reviews'] ) ) {
		return '';
	}

	$html  = '';
	$count = 0;

	foreach ( $response['reviews'] as $review ) {
		$rating = intval( $review['rating'] ?? 0 );

		if ( 0 === $rating ) {
			continue;
		}

		$author_name = $review['author']['name'] ?? 'Anonymous';
		$title       = wp_strip_all_tags( $review['title'] ?? '' );
		$body        = wp_strip_all_tags( $review['body'] ?? '' );

		// Format date.
		$datetime_attr  = '';
		$date_display   = '';
		$created_at     = $review['created_at'] ?? '';
		if ( ! empty( $created_at ) ) {
			$date = date_create( $created_at );
			if ( $date ) {
				$datetime_attr = $date->format( 'Y-m-d' );
				$date_display  = wp_date( get_option( 'date_format' ), $date->getTimestamp() );
			}
		}

		$html .= '<article class="reviewbird-ssr-review">';
		$html .= '<header>';
		$html .= sprintf(
			'<span class="reviewbird-ssr-rating" aria-label="%s">%d/5</span>',
			esc_attr( sprintf( 'Rated %d out of 5', $rating ) ),
			$rating
		);
		$html .= sprintf( '<strong class="reviewbird-ssr-author">%s</strong>', esc_html( $author_name ) );

		if ( $datetime_attr ) {
			$html .= sprintf(
				'<time datetime="%s">%s</time>',
				esc_attr( $datetime_attr ),
				esc_html( $date_display )
			);
		}

		$html .= '</header>';

		if ( ! empty( $title ) ) {
			$html .= sprintf( '<h4 class="reviewbird-ssr-title">%s</h4>', esc_html( $title ) );
		}

		if ( ! empty( $body ) ) {
			$html .= sprintf( '<p class="reviewbird-ssr-body">%s</p>', esc_html( $body ) );
		}

		$html .= '</article>';
		++$count;
	}

	if ( 0 === $count ) {
		return '';
	}

	return '<div class="reviewbird-ssr-reviews">' . $html . '</div>';
}

/**
 * Render the reviewbird widget for a product.
 *
 * @param int|null $product_id Optional product ID. If not provided, uses global $product.
 * @return string HTML output for the widget, or empty string if conditions not met.
 */
function reviewbird_render_widget( $product_id = null ): string {
	$product = $product_id ? wc_get_product( $product_id ) : $GLOBALS['product'] ?? null;

	if ( ! $product || ! reviewbird_is_store_connected() ) {
		return '';
	}

	$store_id = reviewbird_get_store_id();

	if ( ! $store_id ) {
		return '<!-- Reviewbird: Widget not displayed. Store ID not configured. Please connect your Reviewbird account in WP Admin > Reviewbird > Settings -->';
	}

	if ( ! apply_filters( 'reviewbird_show_widget_for_product', true, $product ) ) {
		return '';
	}

	// Don't show widget if reviews are disabled for this product.
	if ( ! comments_open( $product->get_id() ) ) {
		return '';
	}

	$actual_product_id = $product->get_id();
	$widget_id         = 'reviewbird-widget-container-' . $actual_product_id;

	$widget_attrs = apply_filters(
		'reviewbird_widget_attributes',
		array(
			'store-id'    => $store_id,
			'product-key' => $actual_product_id,
			'locale'      => get_locale(),
		),
		$product
	);

	$attrs_html = '';
	foreach ( $widget_attrs as $key => $value ) {
		$attrs_html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
	}

	// Build SSR review HTML for SEO.
	$cached_reviews = reviewbird_get_cached_product_reviews( $actual_product_id );
	$ssr_html       = reviewbird_render_ssr_reviews( $cached_reviews );

	// Add the init call via wp_add_inline_script (once, even if multiple widgets render).
	static $inline_script_added = false;
	if ( ! $inline_script_added ) {
		wp_add_inline_script( 'reviewbird-widget', 'if(typeof reviewbird !== "undefined") reviewbird.init();' );
		$inline_script_added = true;
	}

	return apply_filters(
		'reviewbird_widget_html',
		sprintf(
			'<div id="%s"%s>%s</div>',
			esc_attr( $widget_id ),
			$attrs_html,
			$ssr_html
		),
		$product,
		$widget_id,
		$widget_attrs
	);
}

/**
 * Output the reviewbird star rating in the single product summary.
 *
 * Hooked onto `woocommerce_single_product_summary` at priority 6 for classic
 * themes (see StarRatingDisplay::replace_single_product_rating()). This is a
 * plain named function — not a closure or object method — so site owners can
 * relocate or remove it with an ordinary remove_action()/add_action() call.
 *
 * Because the hook is registered on `init`, those calls must run later; nest
 * them in a `wp` hook for correct timing:
 *
 *     add_action( 'wp', function () {
 *         // Move the stars lower in the summary (e.g. after the short description).
 *         remove_action( 'woocommerce_single_product_summary', 'reviewbird_display_single_rating', 6 );
 *         add_action( 'woocommerce_single_product_summary', 'reviewbird_display_single_rating', 25 );
 *     } );
 *
 * @return void
 */
function reviewbird_display_single_rating(): void {
	global $product;

	if ( ! $product || ! wc_review_ratings_enabled() ) {
		return;
	}

	// Don't show rating if reviews are disabled for this product.
	if ( ! comments_open( $product->get_id() ) ) {
		return;
	}

	$rating_count = $product->get_rating_count();
	if ( $rating_count < 1 ) {
		return;
	}

	echo '<div class="woocommerce-product-rating">';
	echo wp_kses(
		wc_get_rating_html( $product->get_average_rating(), $rating_count ),
		\reviewbird\Integration\StarRatingDisplay::allowed_rating_tags()
	);
	echo '</div>';
}
