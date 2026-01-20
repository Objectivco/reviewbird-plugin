<?php
/**
 * Connection REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

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
		$this->auto_enable_widget();
		$this->clear_status_cache();
		$this->log_connection( $store_id );

		return new WP_REST_Response(
			array(
				'success'        => true,
				'store_id'       => $store_id,
				'widget_enabled' => true,
				'message'        => sprintf( 'Store ID %d has been saved successfully and widget enabled', $store_id ),
			),
			200
		);
	}

	/**
	 * Auto-enable widget if not already configured.
	 */
	private function auto_enable_widget(): void {
		if ( false === get_option( 'reviewbird_enable_widget' ) ) {
			update_option( 'reviewbird_enable_widget', 'yes' );
		}
	}

	/**
	 * Clear any cached connection status.
	 */
	private function clear_status_cache(): void {
		if ( function_exists( 'reviewbird_clear_status_cache' ) ) {
			reviewbird_clear_status_cache();
		}
	}

	/**
	 * Log the store connection event.
	 *
	 * @param int $store_id The connected store ID.
	 */
	private function log_connection( int $store_id ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->info(
			sprintf( 'reviewbird store connected: Store ID %d saved, widget auto-enabled', $store_id ),
			array( 'source' => 'reviewbird' )
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
