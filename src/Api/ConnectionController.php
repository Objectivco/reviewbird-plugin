<?php
/**
 * Connection REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Connection controller class.
 */
class ConnectionController {

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'reviewbird/v1',
			'/store-connected',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'store_connected' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'store_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					),
				),
			)
		);
	}

	/**
	 * Handle store connection notification from Laravel.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function store_connected( WP_REST_Request $request ) {
		$store_id = $request->get_param( 'store_id' );

		// Save the store ID to WordPress options
		update_option( 'reviewbird_store_id', $store_id );

		// Log the connection for debugging
		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->info(
				sprintf( 'reviewbird store connected: Store ID %d saved', $store_id ),
				array( 'source' => 'reviewbird' )
			);
		}

		return new WP_REST_Response(
			array(
				'success'  => true,
				'store_id' => $store_id,
				'message'  => sprintf( 'Store ID %d has been saved successfully', $store_id ),
			),
			200
		);
	}

	/**
	 * Check permission for API requests.
	 * Uses WooCommerce authentication (consumer key/secret).
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool Whether the request has permission.
	 */
	public function permission_callback( WP_REST_Request $request ) {
		// Use WooCommerce's post permissions check which validates OAuth credentials
		return wc_rest_check_post_permissions( 'shop_order', 'create', 0 );
	}
}
