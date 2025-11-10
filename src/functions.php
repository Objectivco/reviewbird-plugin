<?php
/**
 * Core procedural functions for ReviewBird WordPress plugin.
 *
 * @package reviewbird
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =============================================================================
// API FUNCTIONS (replacing Client class)
// =============================================================================

/**
 * Make API requests to ReviewBird backend.
 *
 * @param string $endpoint The API endpoint to call.
 * @param array  $data     Optional. Data to send with the request.
 * @param string $method   Optional. HTTP method. Default 'GET'.
 * @return array|\WP_Error The API response or WP_Error on failure.
 */
function reviewbird_api_request( $endpoint, $data = null, $method = 'GET' ) {
	$api_url = reviewbird_get_api_url();

	$args = array(
		'headers' => array(
			'Content-Type' => 'application/json',
			'User-Agent'   => 'reviewbird WordPress Plugin/' . REVIEWBIRD_VERSION,
		),
		'timeout'   => 30,
		'method'    => strtoupper( $method ),
		'sslverify' => ! reviewbird_should_disable_ssl_verify(),
	);

	if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && $data ) {
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
		$error_message = isset( $decoded['message'] )
			? $decoded['message']
			: __( 'API request failed', 'reviewbird-reviews' );

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

	// Log any errors in successful responses (partial failures)
	if ( isset( $decoded['errors'] ) && ! empty( $decoded['errors'] ) && function_exists( 'wc_get_logger' ) ) {
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
function reviewbird_get_store_id() {
	$store_id = get_option( 'reviewbird_store_id' );
	return $store_id ? absint( $store_id ) : null;
}

/**
 * Log API errors for debugging.
 *
 * @param string $type     Error type.
 * @param string $message  Error message.
 * @param string $endpoint API endpoint.
 * @param int    $code     Optional. HTTP response code.
 * @param array  $context  Optional. Additional context data.
 */
function reviewbird_log_api_error( $type, $message, $endpoint, $code = null, $context = array() ) {
	$log_message = sprintf(
		'%s: %s [Endpoint: %s]',
		$type,
		$message,
		$endpoint
	);

	if ( $code ) {
		$log_message .= sprintf( ' [Code: %d]', $code );
	}

	// Use WooCommerce logger if available
	if ( function_exists( 'wc_get_logger' ) ) {
		$logger = wc_get_logger();
		$logger->error(
			$log_message,
			array_merge( array( 'source' => 'reviewbird' ), $context )
		);
	} else {
		error_log( 'reviewbird: ' . $log_message );
	}
}

// =============================================================================
// HEALTH CHECK FUNCTIONS (replacing HealthChecker class)
// =============================================================================

/**
 * Get store status with smart caching.
 *
 * @param bool $skip_cache Force fresh check.
 * @return array|null Status array or null if unavailable.
 */
function reviewbird_get_store_status( $skip_cache = false ) {
	$cache_key = 'reviewbird_store_status';

	if ( ! $skip_cache ) {
		$cached = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached;
		}
	}

	// Fetch fresh status from API
	$domain   = parse_url( home_url(), PHP_URL_HOST ) ?? '';
	$endpoint = '/api/woocommerce/health?domain=' . urlencode( $domain );

	$response = reviewbird_api_request( $endpoint );

	if ( is_wp_error( $response ) ) {
		return null;
	}

	// Cache status with TTL based on health
	// Match backend TTL: 5 min when healthy/syncing, 30 sec otherwise
	$ttl = in_array( $response['status'] ?? '', array( 'healthy', 'syncing' ) ) ? 300 : 30;
	set_transient( $cache_key, $response, $ttl );

	return $response;
}

/**
 * Clear cached status.
 */
function reviewbird_clear_status_cache() {
	delete_transient( 'reviewbird_store_status' );
}

/**
 * Check if widget can be shown.
 * Widget shows if healthy or syncing AND has subscription.
 *
 * @return bool
 */
function reviewbird_is_store_connected() {
	$status = reviewbird_get_store_status();

	if ( ! $status ) {
		return false;
	}

	// Widget can show if healthy or syncing AND has subscription
	return in_array( $status['status'] ?? '', array( 'healthy', 'syncing' ) )
		&& ( $status['has_active_subscription'] ?? false );
}

function reviewbird_can_show_widget() {
    return reviewbird_is_store_connected() && get_option('reviewbird_enable_widget', false ) === 'yes';
}

/**
 * Render the reviewbird widget for a product.
 *
 * @param int|null $product_id Optional product ID. If not provided, uses global $product.
 * @return string HTML output for the widget, or empty string if conditions not met.
 */
function reviewbird_render_widget( $product_id = null ) {
	// If product_id not provided, get from global
	if ( $product_id === null ) {
		global $product;
		if ( ! $product ) {
			return '';
		}
		$product_obj = $product;
		$product_id = $product->get_id();
	} else {
		// Get product object from ID
		if ( ! function_exists( 'wc_get_product' ) ) {
			return '';
		}
		$product_obj = wc_get_product( $product_id );
		if ( ! $product_obj ) {
			return '';
		}
	}

	$store_id = reviewbird_get_store_id();
	if ( ! $store_id ) {
		// Return HTML comment for debugging when called directly
		if ( $product_id === null ) {
			return '<!-- reviewbird: Widget not displayed. Store ID not configured. Please connect your reviewbird account in WP Admin > reviewbird > Settings -->';
		}
		return '';
	}

	// Allow developers to disable widget for specific products.
	if ( ! apply_filters( 'reviewbird_show_widget_for_product', true, $product_obj ) ) {
		return '';
	}

	$widget_id = 'reviewbird-widget-container-' . $product_id;

	// Allow developers to customize widget attributes.
	$widget_attrs = apply_filters(
		'reviewbird_widget_attributes',
		array(
			'store-id'    => $store_id,
			'product-key' => $product_id,
		),
		$product_obj
	);

	$attrs_html = '';
	foreach ( $widget_attrs as $key => $value ) {
		$attrs_html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
	}

	// Allow developers to customize widget wrapper.
	$widget_html = apply_filters(
		'reviewbird_widget_html',
		sprintf(
			'<div id="%s"%s></div><script>if(typeof reviewbird !== "undefined") reviewbird.init();</script>',
			esc_attr( $widget_id ),
			$attrs_html
		),
		$product_obj,
		$widget_id,
		$widget_attrs
	);

	return $widget_html;
}
