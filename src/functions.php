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
		$error_message = $decoded['message'] ?? __( 'API request failed', 'reviewbird-reviews' );

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
function reviewbird_get_store_status( bool $skip_cache = false ): ?array {
	$cache_key = 'reviewbird_store_status';

	if ( ! $skip_cache ) {
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$domain   = parse_url( home_url(), PHP_URL_HOST ) ?? '';
	$endpoint = '/api/woocommerce/health?domain=' . urlencode( $domain );
	$response = reviewbird_api_request( $endpoint );

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$healthy_statuses = array( 'healthy', 'syncing' );
	$is_healthy       = in_array( $response['status'] ?? '', $healthy_statuses, true );
	$ttl              = $is_healthy ? 300 : 30;

	set_transient( $cache_key, $response, $ttl );

	return $response;
}

/**
 * Clear cached store status.
 *
 * @return void
 */
function reviewbird_clear_status_cache(): void {
	delete_transient( 'reviewbird_store_status' );
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
 * Check if the reviewbird widget can be shown.
 *
 * Widget displays when the store is connected and widget setting is enabled.
 *
 * @return bool True if widget can be shown.
 */
function reviewbird_can_show_widget(): bool {
	$is_widget_enabled = get_option( 'reviewbird_enable_widget', 'yes' ) === 'yes';

	return reviewbird_is_store_connected() && $is_widget_enabled;
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
		return '<!-- reviewbird: Widget not displayed. Store ID not configured. Please connect your reviewbird account in WP Admin > reviewbird > Settings -->';
	}

	if ( ! apply_filters( 'reviewbird_show_widget_for_product', true, $product ) ) {
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

	return apply_filters(
		'reviewbird_widget_html',
		sprintf(
			'<div id="%s"%s></div><script>if(typeof reviewbird !== "undefined") reviewbird.init();</script>',
			esc_attr( $widget_id ),
			$attrs_html
		),
		$product,
		$widget_id,
		$widget_attrs
	);
}
