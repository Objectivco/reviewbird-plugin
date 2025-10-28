<?php
/**
 * API client for reviewbird communication.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

/**
 * API client class for handling reviewbird communication.
 */
class Client {

	/**
	 * The API base URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * Initialize the API client.
	 */
	public function __construct() {
		$this->api_url = reviewbird_get_api_url();
	}

	/**
	 * Make authenticated API requests using WordPress HTTP API.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @param array  $data     Optional. Data to send with the request.
	 * @param string $method   Optional. HTTP method. Default 'GET'.
	 * @return array|\WP_Error The API response or WP_Error on failure.
	 */
	public function request( $endpoint, $data = null, $method = 'GET' ) {
		$store_token = get_option( 'reviewbird_store_token' );

		if ( ! $store_token ) {
			return new \WP_Error(
				'no_token',
				__( 'reviewbird store token not configured', 'reviewbird-reviews' )
			);
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $store_token,
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'reviewbird WordPress Plugin/' . REVIEWBIRD_VERSION,
			),
			'timeout' => 30,
			'method'  => strtoupper( $method ),
            'sslverify' => ! reviewbird_should_disable_ssl_verify()
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && $data ) {
			$args['body'] = wp_json_encode( $data );
		}

		$url      = $this->api_url . $endpoint;
		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'HTTP Error', $response->get_error_message(), $endpoint );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response, array( 'sslverify' => ! reviewbird_should_disable_ssl_verify() ) );
		$body          = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $body, true );

		if ( $response_code >= 400 ) {
			$error_message = isset( $decoded['message'] )
				? $decoded['message']
				: __( 'API request failed', 'reviewbird-reviews' );

			$this->log_error( 
				'API Error', 
				$error_message, 
				$endpoint, 
				$response_code,
				array(
					'response_body' => $body,
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
	 * Validate the store token.
	 *
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate_token() {
		$response = $this->request( '/api/stores/status' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['store_id'] );
	}

	/**
	 * Get store information.
	 *
	 * @return array|\WP_Error Store information or WP_Error on failure.
	 */
	public function get_store_info() {
		return $this->request( '/api/stores/status' );
	}

	/**
	 * Extract store ID from store token.
	 *
	 * @param string $token Optional. Store token. Uses saved token if not provided.
	 * @return int|null Store ID or null if not found.
	 */
	public function get_store_id_from_token( $token = null ) {
		if ( ! $token ) {
			$token = get_option( 'reviewbird_store_token' );
		}

		if ( ! $token ) {
			return null;
		}

		// Extract store ID from token format: ra_st_{store_id}_{token}.
		if ( preg_match( '/ra_st_(\d+)_/', $token, $matches ) ) {
			return absint( $matches[1] );
		}

		return null;
	}

	/**
	 * Get store ID safe for frontend use.
	 *
	 * @return int|null Store ID or null if not available.
	 */
	public function get_store_id_for_frontend() {
		$store_id = get_option( 'reviewbird_store_id' );
		if ( $store_id ) {
			return absint( $store_id );
		}

		// Extract from token if not cached.
		$store_id = $this->get_store_id_from_token();
		if ( $store_id ) {
			update_option( 'reviewbird_store_id', $store_id );
		}

		return $store_id;
	}

	/**
	 * Get store token for admin operations only.
	 *
	 * @return string|false Store token or false if not available.
	 */
	public function get_store_token_for_admin() {
		// Security check: Only allow in admin context with proper capabilities.
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return get_option( 'reviewbird_store_token' );
	}

	/**
	 * Update store locale information (timezone, language, country).
	 *
	 * @param string|null $timezone WordPress timezone string.
	 * @param string|null $language WordPress locale string.
	 * @param string|null $country  2-character country code from WooCommerce.
	 * @return array|\WP_Error API response or WP_Error on failure.
	 */
	public function update_store_locale( $timezone = null, $language = null, $country = null ) {
		$data = array_filter(
			array(
				'timezone' => $timezone,
				'language' => $language,
				'country'  => $country,
			),
			function( $value ) {
				return ! is_null( $value ) && '' !== $value;
			}
		);

		if ( empty( $data ) ) {
			return new \WP_Error(
				'no_data',
				__( 'No locale data provided', 'reviewbird-reviews' )
			);
		}

		return $this->request( '/api/stores/locale', $data, 'PUT' );
	}

	/**
	 * Log API errors for debugging.
	 *
	 * @param string $type     Error type.
	 * @param string $message  Error message.
	 * @param string $endpoint API endpoint.
	 * @param int    $code     Optional. HTTP response code.
	 */
	private function log_error( $type, $message, $endpoint, $code = null, $context = array() ) {
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
}
