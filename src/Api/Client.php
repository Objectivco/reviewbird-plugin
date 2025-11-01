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
	 * Make API requests using WordPress HTTP API.
	 *
	 * Note: This method no longer uses Bearer token authentication.
	 * Authentication is handled by domain-based validation on the Laravel side.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @param array  $data     Optional. Data to send with the request.
	 * @param string $method   Optional. HTTP method. Default 'GET'.
	 * @return array|\WP_Error The API response or WP_Error on failure.
	 */
	public function request( $endpoint, $data = null, $method = 'GET' ) {
		$args = array(
			'headers' => array(
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
	 * Get store ID safe for frontend use.
	 *
	 * @return int|null Store ID or null if not available.
	 */
	public function get_store_id_for_frontend() {
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
