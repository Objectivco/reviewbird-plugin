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

/**
 * Find a product by its current ID or a saved Reviewbird ID.
 *
 * @param int $product_id Current or saved product ID.
 * @return \WC_Product|false
 */
function reviewbird_find_product( int $product_id ) {
	$product = wc_get_product( $product_id );
	if ( $product && 'trash' !== $product->get_status() ) {
		return $product;
	}

	// ponytail: Deleted identities use a meta lookup; add an indexed map if these lookups become frequent.
	$ids = get_posts(
		array(
			'post_type'        => array( 'product', 'product_variation' ),
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'meta_key'         => '_reviewbird_product_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-result fallback for a deleted shared identity.
			'meta_value'       => $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'lang'             => '',
			'suppress_filters' => true,
		)
	);

	return $ids ? wc_get_product( (int) $ids[0] ) : false;
}

/**
 * Get the actual product IDs in one translation set.
 *
 * @param int $product_id Product ID.
 * @return int[] Actual product IDs.
 */
function reviewbird_product_translation_ids( int $product_id ): array {
	$product = reviewbird_find_product( $product_id );
	if ( ! $product ) {
		return array();
	}

	$product_id = $product->get_id();
	$type       = get_post_type( $product_id );

	/**
	 * Filter the actual IDs in a product translation set.
	 *
	 * @param int[] $ids        Product IDs.
	 * @param int   $product_id Actual product ID.
	 */
	$ids = apply_filters( 'reviewbird_product_translation_ids', array( $product_id ), $product_id );
	$ids = array_map( 'intval', (array) $ids );
	$ids = array_filter(
		$ids,
		static function ( $id ) use ( $type ) {
			return $id > 0 && get_post_type( $id ) === $type;
		}
	);
	return array_values( array_unique( $ids ) );
}

/**
 * Get the saved Reviewbird identity for a product and its translations.
 *
 * @param int $product_id Actual or saved product ID.
 * @return int Shared Reviewbird product ID.
 */
function reviewbird_product_id( int $product_id ): int {
	if ( ! has_filter( 'reviewbird_product_translation_ids' ) ) {
		$saved_id = (int) get_post_meta( $product_id, '_reviewbird_product_id', true );
		if ( $saved_id > 0 ) {
			return $saved_id;
		}
		if ( $product_id > 0 && in_array( get_post_type( $product_id ), array( 'product', 'product_variation' ), true ) ) {
			update_post_meta( $product_id, '_reviewbird_product_id', $product_id );
		}
		return $product_id;
	}

	$ids = reviewbird_product_translation_ids( $product_id );
	if ( ! $ids ) {
		return $product_id;
	}

	$saved = array();
	foreach ( $ids as $id ) {
		$saved_id = (int) get_post_meta( $id, '_reviewbird_product_id', true );
		if ( $saved_id > 0 ) {
			$saved[] = $saved_id;
		}
	}
	if ( $saved ) {
		$shared_id = min( $saved );
	} else {
		/**
		 * Filter the initial identity before it is saved for all translations.
		 *
		 * @param int $shared_id  Default identity.
		 * @param int $product_id Actual product ID.
		 */
		$shared_id = (int) apply_filters( 'reviewbird_original_product_id', min( $ids ), $ids[0] );
		$shared_id = in_array( $shared_id, $ids, true ) ? $shared_id : min( $ids );
	}

	foreach ( $ids as $id ) {
		if ( (int) get_post_meta( $id, '_reviewbird_product_id', true ) !== $shared_id ) {
			update_post_meta( $id, '_reviewbird_product_id', $shared_id );
		}
	}

	return $shared_id;
}

/**
 * Get the surviving product that stores shared Reviewbird data.
 *
 * @param int $product_id Actual or saved product ID.
 * @return int Product ID used for cached data.
 */
function reviewbird_product_meta_id( int $product_id ): int {
	$shared_id = reviewbird_product_id( $product_id );
	$product   = reviewbird_find_product( $shared_id );
	return $product ? $product->get_id() : $shared_id;
}

/**
 * Get the Reviewbird environment. REVIEWBIRD_ENVIRONMENT overrides WordPress's type.
 *
 * @return string 'production', 'staging', or a custom REVIEWBIRD_ENVIRONMENT value.
 */
function reviewbird_get_environment(): string {
	if ( defined( 'REVIEWBIRD_ENVIRONMENT' ) ) {
		return REVIEWBIRD_ENVIRONMENT;
	}

	return 'staging' === wp_get_environment_type() ? 'staging' : 'production';
}

/**
 * Get the Reviewbird app URL for the current environment.
 *
 * @return string
 */
function reviewbird_get_api_url(): string {
	$urls = array(
		'production'  => REVIEWBIRD_API_URL_PRODUCTION,
		'staging'     => REVIEWBIRD_API_URL_STAGING,
		'development' => REVIEWBIRD_API_URL_DEVELOPMENT,
	);

	return $urls[ reviewbird_get_environment() ] ?? REVIEWBIRD_API_URL_DEVELOPMENT;
}

/**
 * Whether to skip SSL verification, for local development only.
 *
 * @return bool
 */
function reviewbird_should_disable_ssl_verify(): bool {
	return 'development' === reviewbird_get_environment() || ( defined( 'REVIEWBIRD_DISABLE_SSL_VERIFY' ) && REVIEWBIRD_DISABLE_SSL_VERIFY );
}

/**
 * Call the Reviewbird API and decode its JSON response.
 *
 * @param string     $endpoint Path, including any query string.
 * @param array|null $data     Body for POST, PUT, and PATCH requests.
 * @param string     $method   HTTP method.
 * @param int        $timeout  Request timeout in seconds.
 * @param array      $headers  Extra request headers.
 * @return array|null|\WP_Error Decoded response, or an error for HTTP and API failures.
 */
function reviewbird_api_request( $endpoint, $data = null, $method = 'GET', int $timeout = 30, array $headers = array() ) {
	$method = strtoupper( $method );
	$args   = array(
		'headers'   => $headers + array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'Origin'       => home_url(),
			'User-Agent'   => 'reviewbird WordPress Plugin/' . REVIEWBIRD_VERSION,
		),
		'timeout'   => $timeout,
		'method'    => $method,
		'sslverify' => ! reviewbird_should_disable_ssl_verify(),
	);
	if ( $data && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
		$args['body'] = wp_json_encode( $data );
	}

	$response = wp_remote_request( reviewbird_get_api_url() . $endpoint, $args );
	if ( is_wp_error( $response ) ) {
		reviewbird_log_api_error( 'HTTP Error', $response->get_error_message(), $endpoint );
		return $response;
	}

	$code    = wp_remote_retrieve_response_code( $response );
	$body    = wp_remote_retrieve_body( $response );
	$decoded = json_decode( $body, true );
	if ( $code < 200 || $code >= 300 ) {
		$message = $decoded['message'] ?? __( 'API request failed', 'reviewbird' );
		reviewbird_log_api_error(
			'API Error',
			$message,
			$endpoint,
			$code,
			array(
				'response_body'    => $body,
				'decoded_response' => $decoded,
			)
		);
		return new \WP_Error(
			'reviewbird_api_error',
			$message,
			array(
				'status'   => $code,
				'response' => $decoded,
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
	$store_id = absint( get_option( 'reviewbird_store_id' ) );
	if ( ! $store_id ) {
		$store_id = absint( reviewbird_get_store_status()['store_id'] ?? 0 );
	}

	return $store_id ? $store_id : null;
}

/**
 * Log an API error to the WooCommerce log.
 *
 * @param string   $type     Error type.
 * @param string   $message  Error message.
 * @param string   $endpoint API endpoint.
 * @param int|null $code     Optional. HTTP response code.
 * @param array    $context  Optional. Additional context data.
 * @return void
 */
function reviewbird_log_api_error( string $type, string $message, string $endpoint, ?int $code = null, array $context = array() ): void {
	wc_get_logger()->error(
		sprintf( '%s: %s [Endpoint: %s]', $type, $message, $endpoint ) . ( $code ? sprintf( ' [Code: %d]', $code ) : '' ),
		array( 'source' => 'reviewbird' ) + $context
	);
}

/**
 * Get the store status saved by the background health check.
 *
 * @return array|null Status array or null if not yet populated.
 */
function reviewbird_get_store_status(): ?array {
	$status = get_option( 'reviewbird_store_status' );

	return is_array( $status ) ? $status : null;
}

/**
 * Check if the store is connected and can show the widget.
 *
 * A store is considered connected when it is healthy or syncing AND has an active subscription.
 *
 * @return bool True if store is connected.
 */
function reviewbird_is_store_connected(): bool {
	if ( reviewbird_is_onboarding_preview() ) {
		return true;
	}

	$status = reviewbird_get_store_status();

	return in_array( $status['status'] ?? '', array( 'healthy', 'syncing' ), true ) && ! empty( $status['has_active_subscription'] );
}

/**
 * Allow a short-lived setup preview without changing public subscription access.
 *
 * @return bool Whether Reviewbird authorized this request's preview token.
 */
function reviewbird_is_onboarding_preview(): bool {
	// This token is verified by the API, not a WordPress form nonce.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Validate the exact token with the strict allowlist below; the API verifies its authority.
	$token = $_GET['reviewbird_setup'] ?? '';
	if ( ! is_string( $token ) || ! preg_match( '/\A[a-zA-Z0-9]{64}\z/', $token ) ) {
		return false;
	}

	static $results = array();
	if ( isset( $results[ $token ] ) ) {
		return $results[ $token ];
	}

	$failure_key = 'reviewbird_preview_failed_' . hash( 'sha256', $token );
	if ( get_transient( $failure_key ) ) {
		return false;
	}

	$allowed_key = 'reviewbird_preview_allowed_' . hash( 'sha256', $token );
	$allowed     = (bool) get_transient( $allowed_key );
	if ( ! $allowed ) {
		// ponytail: one new token check per site every 10 seconds; use signed local tokens if setup traffic grows.
		if ( ! reviewbird_acquire_lock( 'reviewbird_preview_check_lock', time() + 10 ) ) {
			// Remember the miss so later calls in this request skip the lock query.
			$results[ $token ] = false;
			return false;
		}

		$domain   = wp_parse_url( home_url(), PHP_URL_HOST ) ?? '';
		$response = reviewbird_api_request( '/api/woocommerce/health?domain=' . rawurlencode( $domain ) . '&onboarding_preview=' . rawurlencode( $token ), null, 'GET', 3 );
		$allowed  = ! is_wp_error( $response ) && true === ( $response['onboarding_preview_allowed'] ?? false );
		if ( ! $allowed ) {
			set_transient( $failure_key, 1, 5 * MINUTE_IN_SECONDS );
			return false;
		}
		set_transient( $allowed_key, 1, 10 );
	}
	$results[ $token ] = true;

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'Referrer-Policy: no-referrer' );
	}

	return $allowed;
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
 * Acquire an expiring option lock atomically.
 *
 * @param string $lock_key Option name.
 * @param int    $expires Expiration timestamp.
 * @return bool Whether this request acquired the lock.
 */
function reviewbird_acquire_lock( string $lock_key, int $expires ): bool {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- add_option() can overwrite a concurrently inserted option.
	$acquired = $wpdb->query(
		$wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $lock_key, (string) $expires )
	);
	if ( 0 === $acquired ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The conditional update acquires an expired lock atomically.
		$acquired = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(option_value AS UNSIGNED) <= %d",
				(string) $expires,
				$lock_key,
				time()
			)
		);
	}
	if ( $acquired ) {
		wp_cache_delete( $lock_key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
	return (bool) $acquired;
}

/**
 * Validate rating buckets and fill omitted zero buckets.
 *
 * @param mixed $counts Rating counts indexed by stars.
 * @param int   $review_count Total review count.
 * @return array|null Five valid buckets, or null for invalid input.
 */
function reviewbird_normalize_rating_counts( $counts, int $review_count ): ?array {
	$normalized = array_fill( 1, 5, 0 );
	if ( 0 === $review_count ) {
		return $normalized;
	}
	if ( ! is_array( $counts ) || empty( $counts ) || $review_count < 0 ) {
		return null;
	}
	$remaining = $review_count;
	foreach ( $counts as $stars => $count ) {
		if ( ! is_int( $stars ) || $stars < 1 || $stars > 5 || ( ! is_int( $count ) && ! is_string( $count ) ) ) {
			return null;
		}
		$count = filter_var( $count, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		if ( false === $count || $count > $remaining ) {
			return null;
		}
		$remaining           -= $count;
		$normalized[ $stars ] = $count;
	}
	return $normalized;
}

/**
 * Fill `_reviewbird_*` rating meta from the public API when it is missing.
 *
 * @param int $product_id WooCommerce product ID.
 * @return void
 */
function reviewbird_sync_product_rating( int $product_id ): void {
	if ( ! reviewbird_can_show_widget() ) {
		return;
	}
	$shared_id = reviewbird_product_id( $product_id );
	$product   = reviewbird_find_product( $shared_id );
	if ( ! $product ) {
		return;
	}
	$product_id    = $product->get_id();
	static $synced = array();

	if ( $product_id < 1 || isset( $synced[ $product_id ] ) ) {
		return;
	}

	$synced[ $product_id ] = true;

	if ( '' !== get_post_meta( $product_id, '_reviewbird_reviews_count', true ) ) {
		return;
	}

	$lock_key = 'reviewbird_rating_sync_' . $product_id;
	if ( get_transient( $lock_key ) ) {
		return;
	}

	$store_id = reviewbird_get_store_id();
	if ( ! $store_id ) {
		return;
	}

	set_transient( $lock_key, 1, HOUR_IN_SECONDS );

	$data  = reviewbird_api_request( '/api/public/' . $store_id . '/' . $shared_id . '?context=widget&page=1', null, 'GET', 5 );
	$stats = is_array( $data ) ? ( $data['statistics'] ?? array() ) : array();
	if ( ! is_array( $stats ) || ! isset( $stats['total_reviews'], $stats['average_rating'] )
		|| ! is_numeric( $stats['total_reviews'] ) || ! is_numeric( $stats['average_rating'] ) ) {
		return;
	}
	$count   = ( is_int( $stats['total_reviews'] ) || is_string( $stats['total_reviews'] ) )
		? filter_var( $stats['total_reviews'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) : false;
	$average = (float) $stats['average_rating'];
	if ( false === $count || ! is_finite( $average ) || $average < 0 || $average > 5 || ( 0 === $count && 0.0 !== $average ) ) {
		return;
	}
	$counts = reviewbird_normalize_rating_counts( $stats['rating_distribution'] ?? null, $count );
	if ( null === $counts ) {
		return;
	}
	$product = reviewbird_find_product( $shared_id );
	if ( ! $product ) {
		return;
	}
	$product_id = $product->get_id();

	update_post_meta( $product_id, '_reviewbird_avg_stars', $average );
	update_post_meta( $product_id, '_reviewbird_reviews_count', $count );
	update_post_meta( $product_id, '_reviewbird_rating_counts', $counts );
}

/**
 * Whether the current request should enqueue the review widget script early.
 *
 * Covers native product pages plus WordPress pages that embed a product via
 * the Reviewbird shortcode, WooCommerce [product_page] (WPBakery Product Page),
 * or the WooCommerce Single Product block.
 *
 * @return bool True if the widget script should be enqueued.
 */
function reviewbird_page_should_enqueue_widget(): bool {
	if ( is_product() ) {
		return true;
	}

	global $post;

	if ( empty( $post ) ) {
		return false;
	}

	return has_shortcode( $post->post_content, 'reviewbird_widget' ) || has_shortcode( $post->post_content, 'product_page' )
		|| has_block( 'woocommerce/single-product', $post );
}

/**
 * Enqueue the review widget script and configuration.
 *
 * Safe to call while rendering content because the script prints in the footer.
 *
 * @return void
 */
function reviewbird_enqueue_widget_script(): void {
	if ( ! reviewbird_can_show_widget() || ! reviewbird_enqueue_remote_script( 'reviewbird-widget', 'review-widget-v2.js' ) ) {
		return;
	}

	$config = array(
		'apiUrl'       => reviewbird_get_api_url(),
		'storeId'      => reviewbird_get_store_id(),
		'widgetPrefix' => 'reviewbird-widget-container-',
	);

	$customer = is_user_logged_in() ? WC()->customer : null;
	$email    = $customer ? $customer->get_billing_email() : '';

	if ( $email ) {
		$config['prefill'] = array(
			'firstName' => $customer->get_billing_first_name(),
			'lastName'  => $customer->get_billing_last_name(),
			'email'     => $email,
		);
	}

	wp_localize_script( 'reviewbird-widget', 'reviewbirdConfig', $config );
}

/**
 * Enqueue a Reviewbird-hosted footer script once.
 *
 * @param string $handle Script handle.
 * @param string $file   File in the app's build directory.
 * @return bool Whether this call enqueued it.
 */
function reviewbird_enqueue_remote_script( string $handle, string $file ): bool {
	if ( wp_script_is( $handle ) || wp_script_is( $handle, 'done' ) ) {
		return false;
	}
	// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- The service updates its scripts independently of plugin releases.
	wp_enqueue_script( $handle, reviewbird_get_api_url() . '/build/' . $file, array(), null, true );
	return true;
}

/**
 * Get cached product reviews, using a transient to avoid repeated API calls.
 *
 * @param int $product_id WooCommerce product ID.
 * @return array API response array (with 'reviews' key) or empty array on failure.
 */
function reviewbird_get_cached_product_reviews( int $product_id ): array {
	// Setup reads reviews in the browser with its temporary token.
	if ( reviewbird_is_onboarding_preview() ) {
		return array();
	}
	$product_id = reviewbird_product_meta_id( $product_id );
	if ( '0' === (string) get_post_meta( $product_id, '_reviewbird_reviews_count', true ) ) {
		return array();
	}

	$transient_key = 'reviewbird_ssr_' . $product_id;
	$cached        = get_transient( $transient_key );

	if ( false !== $cached ) {
		return $cached;
	}

	if ( get_transient( 'reviewbird_ssr_api_failed' ) ) {
		return array();
	}

	set_transient( $transient_key, array(), 5 * MINUTE_IN_SECONDS );
	$store_id = reviewbird_get_store_id();
	$response = $store_id ? reviewbird_api_request(
		'/api/v1/stores/' . $store_id . '/products/reviews?' . http_build_query(
			array(
				'per_page'    => 10,
				'product_ids' => reviewbird_product_id( $product_id ),
			)
		),
		null,
		'GET',
		3,
		array( 'X-Store-Hash' => md5( (string) $store_id ) )
	) : null;

	if ( is_wp_error( $response ) || ! is_array( $response['reviews'] ?? null ) ) {
		set_transient( 'reviewbird_ssr_api_failed', 1, 5 * MINUTE_IN_SECONDS );
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

		$author_name = $review['author']['name'] ?? __( 'Anonymous', 'reviewbird' );
		$title       = wp_strip_all_tags( $review['title'] ?? '' );
		$body        = wp_strip_all_tags( $review['body'] ?? '' );

		// Format date.
		$datetime_attr = '';
		$date_display  = '';
		$created_at    = $review['created_at'] ?? '';
		if ( ! empty( $created_at ) ) {
			$date = date_create( $created_at );
			if ( $date ) {
				$datetime_attr = $date->format( 'Y-m-d' );
				$date_display  = wp_date( get_option( 'date_format' ), $date->getTimestamp(), new \DateTimeZone( 'UTC' ) );
			}
		}

		$html .= '<article class="reviewbird-ssr-review">';
		$html .= '<header>';
		$html .= sprintf(
			'<span class="reviewbird-ssr-rating" aria-label="%s">%d/5</span>',
			/* translators: %d: review rating, from 1 to 5. */
			esc_attr( sprintf( __( 'Rated %d out of 5', 'reviewbird' ), $rating ) ),
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

	if ( ! $product instanceof \WC_Product || ! reviewbird_is_store_connected() ) {
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
			'product-key' => reviewbird_product_id( $actual_product_id ),
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

	// Enqueue here so pages that embed a product (WPBakery, shortcode, block)
	// still get the footer script even when is_product() is false.
	reviewbird_enqueue_widget_script();

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

	if ( ! $product instanceof \WC_Product || ! wc_review_ratings_enabled() ) {
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
