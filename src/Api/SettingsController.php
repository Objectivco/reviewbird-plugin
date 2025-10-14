<?php
/**
 * REST API Settings Controller.
 *
 * @package ReviewApp
 */

namespace ReviewApp\Api;

use ReviewApp\Integration\ProductSync;
use ReviewApp\Integration\ReviewSync;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Settings REST API controller.
 */
class SettingsController {

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'reviewapp/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $this->get_settings_schema(),
				),
			)
		);

		register_rest_route(
			'reviewapp/v1',
			'/sync/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_sync_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			'reviewapp/v1',
			'/sync/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'start_sync' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			'reviewapp/v1',
			'/sync/reviews/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_review_sync_status' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);

		register_rest_route(
			'reviewapp/v1',
			'/sync/reviews/start',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'start_review_sync' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			)
		);
	}

	/**
	 * Check if user has permission to manage settings.
	 *
	 * @return bool
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get current settings.
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		$client = new Client();
		$token  = get_option( 'reviewapp_store_token', '' );

		$connection_status = 'disconnected';
		if ( $token ) {
			$result            = $client->validate_token();
			$connection_status = is_wp_error( $result ) ? 'error' : 'connected';
		}

		// Get WooCommerce order statuses
		$order_statuses = \ReviewApp\Integration\OrderSync::get_order_statuses();

		return rest_ensure_response(
			array(
				'store_token'                           => $token,
				'store_id'                              => get_option( 'reviewapp_store_id', '' ),
				'connection_status'                     => $connection_status,
				'oauth_nonce'                           => wp_create_nonce( 'reviewapp_oauth_start' ),
				'review_request_trigger_status'         => get_option( 'reviewapp_review_request_trigger_status', 'completed' ),
				'available_order_statuses'              => $order_statuses,
				'orders_synced_count'                   => (int) get_option( 'reviewapp_orders_synced_count', 0 ),
				'orders_last_synced'                    => (int) get_option( 'reviewapp_orders_last_synced', 0 ),
			)
		);
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		// Update review request trigger status if provided
		if ( $request->has_param( 'review_request_trigger_status' ) ) {
			$trigger_status = sanitize_text_field( $request->get_param( 'review_request_trigger_status' ) );
			update_option( 'reviewapp_review_request_trigger_status', $trigger_status );
		}

		// If only updating review request settings, return early
		if ( ! $request->has_param( 'store_token' ) ) {
			return $this->get_settings();
		}

		$token = sanitize_text_field( $request->get_param( 'store_token' ) );

		// Allow empty token to disconnect.
		if ( empty( $token ) ) {
			delete_option( 'reviewapp_store_token' );
			delete_option( 'reviewapp_store_id' );
			delete_option( reviewapp_get_env_option_key( 'oauth_client_id' ) );

			return $this->get_settings();
		}

		// Validate token format.
		if ( ! preg_match( '/^ra_st_\d+_[a-zA-Z0-9]+$/', $token ) ) {
			return new WP_Error(
				'invalid_token_format',
				__( 'Invalid store token format. Please copy the token exactly from your ReviewApp dashboard.', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		// Validate token with API.
		$api_client = new Client();
		$old_token  = get_option( 'reviewapp_store_token' );

		// Temporarily set token for validation.
		update_option( 'reviewapp_store_token', $token );

		$result = $api_client->validate_token();

		if ( is_wp_error( $result ) ) {
			// Restore old token on validation failure.
			update_option( 'reviewapp_store_token', $old_token );

			return new WP_Error(
				'invalid_token',
				sprintf(
					/* translators: %s: Error message */
					__( 'Invalid store token: %s', 'reviewapp-reviews' ),
					$result->get_error_message()
				),
				array( 'status' => 400 )
			);
		}

		// Extract and save store ID.
		$store_id = $api_client->get_store_id_from_token( $token );
		if ( $store_id ) {
			update_option( 'reviewapp_store_id', $store_id );
		}

		// Configure media domains using Action Scheduler.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$domains = array( parse_url( get_site_url(), PHP_URL_HOST ) );
			as_enqueue_async_action( 'reviewapp_configure_domains', array( 'domains' => $domains ) );
		}

		return $this->get_settings();
	}

	/**
	 * Get sync status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_sync_status() {
		$product_sync = new ProductSync();
		$status       = $product_sync->get_sync_status();

		return rest_ensure_response( $status );
	}

	/**
	 * Start product sync.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_sync() {
		$product_sync = new ProductSync();
		$result       = $product_sync->start_sync();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Product sync started', 'reviewapp-reviews' ),
		) );
	}

	/**
	 * Get review sync status.
	 *
	 * @return WP_REST_Response
	 */
	public function get_review_sync_status() {
		$review_sync = new ReviewSync();
		$status      = $review_sync->get_sync_status();

		return rest_ensure_response( $status );
	}

	/**
	 * Start review sync.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_review_sync() {
		$review_sync = new ReviewSync();
		$result      = $review_sync->start_sync();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Review sync started', 'reviewapp-reviews' ),
		) );
	}

	/**
	 * Get settings schema for validation.
	 *
	 * @return array
	 */
	private function get_settings_schema() {
		return array(
			'store_token'                   => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'review_request_trigger_status' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
