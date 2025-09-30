<?php
/**
 * API client for ReviewApp communication.
 *
 * @package ReviewApp
 */

namespace ReviewApp\Api;

/**
 * API client class for handling ReviewApp communication.
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
		$this->api_url = reviewapp_get_api_url();
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
		$store_token = get_option( 'reviewapp_store_token' );

		if ( ! $store_token ) {
			return new \WP_Error(
				'no_token',
				__( 'ReviewApp store token not configured', 'reviewapp-reviews' )
			);
		}

		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $store_token,
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'ReviewApp WordPress Plugin/' . REVIEWAPP_VERSION,
			),
			'timeout' => 30,
			'method'  => strtoupper( $method ),
            'sslverify' => ! reviewapp_should_disable_ssl_verify()
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

		$response_code = wp_remote_retrieve_response_code( $response, array( 'sslverify' => ! reviewapp_should_disable_ssl_verify() ) );
		$body          = wp_remote_retrieve_body( $response );
		$decoded       = json_decode( $body, true );

		if ( $response_code >= 400 ) {
			$error_message = isset( $decoded['message'] )
				? $decoded['message']
				: __( 'API request failed', 'reviewapp-reviews' );

			$this->log_error( 'API Error', $error_message, $endpoint, $response_code );

			return new \WP_Error(
				'reviewapp_api_error',
				$error_message,
				array(
					'status'   => $response_code,
					'response' => $decoded,
				)
			);
		}

		return $decoded;
	}

	/**
	 * Queue an API request using Action Scheduler.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @param array  $data     Optional. Data to send with the request.
	 * @param string $method   Optional. HTTP method. Default 'POST'.
	 * @param int    $delay    Optional. Delay in seconds. Default 0.
	 * @return int|false Action ID or false on failure.
	 */
	public function queue_request( $endpoint, $data = null, $method = 'POST', $delay = 0 ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			error_log( 'ReviewApp: Action Scheduler not available for queuing API request' );
			return false;
		}

		$args = array(
			'endpoint' => $endpoint,
			'data'     => $data,
			'method'   => $method,
		);

		if ( $delay > 0 ) {
			return as_schedule_single_action( time() + $delay, 'reviewapp_process_api_request', $args );
		}

		return as_enqueue_async_action( 'reviewapp_process_api_request', $args );
	}

	/**
	 * Process queued API request (Action Scheduler callback).
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     Request data.
	 * @param string $method   HTTP method.
	 */
	public function process_queued_request( $endpoint, $data, $method ) {
		$result = $this->request( $endpoint, $data, $method );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'API request failed: ' . $result->get_error_message() );
		}

		return $result;
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
	 * Sync product data to ReviewApp.
	 *
	 * @param array $product_data Product data.
	 * @return array|\WP_Error API response or WP_Error on failure.
	 */
	public function sync_product( $product_data ) {
		return $this->request( '/api/products.upsert', $product_data, 'POST' );
	}

	/**
	 * Queue product sync using Action Scheduler.
	 *
	 * @param array $product_data Product data.
	 * @param int   $delay        Optional delay in seconds.
	 * @return int|false Action ID or false on failure.
	 */
	public function queue_product_sync( $product_data, $delay = 0 ) {
		return $this->queue_request( '/api/products.upsert', $product_data, 'POST', $delay );
	}

	/**
	 * Push review data to ReviewApp.
	 *
	 * @param array $review_data Review data.
	 * @return array|\WP_Error API response or WP_Error on failure.
	 */
	public function push_review( $review_data ) {
		return $this->request( '/api/reviews.push', $review_data, 'POST' );
	}

	/**
	 * Queue review push using Action Scheduler.
	 *
	 * @param array $review_data Review data.
	 * @param int   $delay       Optional delay in seconds.
	 * @return int|false Action ID or false on failure.
	 */
	public function queue_review_push( $review_data, $delay = 0 ) {
		return $this->queue_request( '/api/reviews.push', $review_data, 'POST', $delay );
	}

	/**
	 * Delete review from ReviewApp.
	 *
	 * @param array $delete_data Review deletion data.
	 * @return array|\WP_Error API response or WP_Error on failure.
	 */
	public function delete_review( $delete_data ) {
		return $this->request( '/api/reviews.delete', $delete_data, 'POST' );
	}

	/**
	 * Track order events in ReviewApp.
	 *
	 * @param array $order_data Order event data.
	 * @return array|\WP_Error API response or WP_Error on failure.
	 */
	public function track_order_event( $order_data ) {
		return $this->request( '/api/orders.event', $order_data, 'POST' );
	}

	/**
	 * Queue order event tracking using Action Scheduler.
	 *
	 * @param array $order_data Order event data.
	 * @param int   $delay      Optional delay in seconds.
	 * @return int|false Action ID or false on failure.
	 */
	public function queue_order_event( $order_data, $delay = 0 ) {
		return $this->queue_request( '/api/orders.event', $order_data, 'POST', $delay );
	}

	/**
	 * Configure media domains for video playback.
	 *
	 * @param array  $domains Array of domain names.
	 * @param string $action  Action to perform: 'add', 'remove', or 'replace'.
	 * @return array|\WP_Error API response or WP_Error on failure.
	 */
	public function configure_media_domains( $domains, $action = 'add' ) {
		$data = array(
			'domains' => $domains,
			'action'  => $action,
		);

		return $this->request( '/api/media/configure-domains', $data, 'POST' );
	}

	/**
	 * Extract store ID from store token.
	 *
	 * @param string $token Optional. Store token. Uses saved token if not provided.
	 * @return int|null Store ID or null if not found.
	 */
	public function get_store_id_from_token( $token = null ) {
		if ( ! $token ) {
			$token = get_option( 'reviewapp_store_token' );
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
		$store_id = get_option( 'reviewapp_store_id' );
		if ( $store_id ) {
			return absint( $store_id );
		}

		// Extract from token if not cached.
		$store_id = $this->get_store_id_from_token();
		if ( $store_id ) {
			update_option( 'reviewapp_store_id', $store_id );
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

		return get_option( 'reviewapp_store_token' );
	}

	/**
	 * Log API errors for debugging.
	 *
	 * @param string $type     Error type.
	 * @param string $message  Error message.
	 * @param string $endpoint API endpoint.
	 * @param int    $code     Optional. HTTP response code.
	 */
	private function log_error( $type, $message, $endpoint, $code = null ) {
		$log_message = sprintf(
			'ReviewApp %s: %s [Endpoint: %s]',
			$type,
			$message,
			$endpoint
		);

		if ( $code ) {
			$log_message .= sprintf( ' [Code: %d]', $code );
		}

		error_log( $log_message );
	}
}
