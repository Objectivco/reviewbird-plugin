<?php
/**
 * Connection REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use reviewbird\Integration\HealthScheduler;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Connection controller class.
 */
class ConnectionController {
	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
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
						'validate_callback' => function ( $param ) {
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
	 * @return WP_REST_Response Response with connection status.
	 */
	public function store_connected( WP_REST_Request $request ): WP_REST_Response {
		$store_id = $request->get_param( 'store_id' );

		update_option( 'reviewbird_store_id', $store_id );

		reviewbird_clear_status_cache();

		// Schedule immediate health status refresh.
		$health_scheduler = new HealthScheduler();
		$health_scheduler->schedule_immediate_refresh();

		wc_get_logger()->info(
			sprintf( 'reviewbird store connected: Store ID %d saved', $store_id ),
			array( 'source' => 'reviewbird' )
		);

		return new WP_REST_Response(
			array(
				'success'  => true,
				'store_id' => $store_id,
				// translators: %d: Store ID number.
				'message'  => sprintf( __( 'Store ID %d has been saved successfully', 'reviewbird' ), $store_id ),
			),
			200
		);
	}

	/**
	 * Check permission for API requests using WooCommerce authentication.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool Whether the request has permission.
	 */
	public function permission_callback( WP_REST_Request $request ): bool {
		return wc_rest_check_post_permissions( 'shop_order', 'create', 0 );
	}
}
