<?php
/**
 * Order synchronization with ReviewApp API.
 *
 * @package ReviewApp
 */

namespace ReviewApp\Integration;

use ReviewApp\Api\Client;

/**
 * Order Sync integration.
 */
class OrderSync {

	/**
	 * API client.
	 *
	 * @var Client
	 */
	private $api_client;

	/**
	 * Constructor.
	 *
	 * @param Client $api_client API client instance.
	 */
	public function __construct( Client $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		// Hook order status changes
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_change' ), 10, 4 );
	}

	/**
	 * Handle order status change.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @param \WC_Order $order   Order object.
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status, $order ) {
		// Check if review requests are enabled
		if ( ! get_option( 'reviewapp_review_requests_enabled', false ) ) {
			return;
		}

		// Get the trigger status from settings
		$trigger_status = get_option( 'reviewapp_review_request_trigger_status', 'completed' );

		// Check if new status matches trigger
		if ( $new_status !== $trigger_status ) {
			return;
		}

		// Push order event to ReviewApp
		$this->push_order_event( $order );
	}

	/**
	 * Push order event to ReviewApp API.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function push_order_event( $order ) {
		$line_items = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$line_item = array(
				'product_external_id' => (string) $product->get_id(),
				'quantity'            => $item->get_quantity(),
				'price'               => (float) $item->get_total(),
			);

			// Add variation ID if it's a variable product
			if ( $product->is_type( 'variation' ) ) {
				$line_item['variation_external_id'] = (string) $product->get_id();
			}

			$line_items[] = $line_item;
		}

		// Build payload
		$payload = array(
			'order_external_id' => (string) $order->get_id(),
			'customer_email'    => $order->get_billing_email(),
			'customer_name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'status'            => $order->get_status(),
			'completed_at'      => $order->get_date_completed() ? $order->get_date_completed()->format( 'c' ) : null,
			'fulfilled_at'      => current_time( 'mysql' ), // Now, since it just reached fulfilled status
			'line_items'        => $line_items,
		);

		try {
			$response = $this->api_client->post( '/orders.event', $payload );

			if ( is_wp_error( $response ) ) {
				error_log( 'ReviewApp: Failed to push order event: ' . $response->get_error_message() );
			} else {
				error_log( 'ReviewApp: Order event pushed successfully for order #' . $order->get_id() );

				// Update sync stats
				$synced_count = (int) get_option( 'reviewapp_orders_synced_count', 0 );
				update_option( 'reviewapp_orders_synced_count', $synced_count + 1 );
				update_option( 'reviewapp_orders_last_synced', time() );
			}
		} catch ( \Exception $e ) {
			error_log( 'ReviewApp: Exception pushing order event: ' . $e->getMessage() );
		}
	}

	/**
	 * Get WooCommerce order statuses for settings.
	 *
	 * @return array
	 */
	public static function get_order_statuses() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array(
				'completed'  => __( 'Completed', 'reviewapp-reviews' ),
				'processing' => __( 'Processing', 'reviewapp-reviews' ),
			);
		}

		$statuses = wc_get_order_statuses();

		// Remove wc- prefix for cleaner values
		$clean_statuses = array();
		foreach ( $statuses as $key => $label ) {
			$clean_key                   = str_replace( 'wc-', '', $key );
			$clean_statuses[ $clean_key ] = $label;
		}

		return $clean_statuses;
	}
}
