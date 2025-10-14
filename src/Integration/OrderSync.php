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

			// Get the actual product ID (for variable products, we need the parent)
			$product_id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

			// Sync product if it hasn't been synced yet
			$this->ensure_product_synced( $product_id );

			$line_item = array(
				'product_external_id' => (string) $product_id,
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
			$response = $this->api_client->track_order_event( $payload );

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

	/**
	 * Ensure product is synced to ReviewApp before including in order.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	private function ensure_product_synced( $product_id ) {
		// Check if product has been synced before
		$last_synced = get_post_meta( $product_id, '_reviewapp_last_synced', true );

		// If synced within last 24 hours, skip
		if ( $last_synced && ( time() - $last_synced ) < DAY_IN_SECONDS ) {
			return;
		}

		// Get product data
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		// Build product sync data
		$product_data = array(
			'external_id' => (string) $product_id,
			'slug'        => $product->get_slug(),
			'title'       => $product->get_name(),
			'url'         => get_permalink( $product_id ),
			'image'       => wp_get_attachment_url( $product->get_image_id() ),
			'active'      => true,
			'in_stock'    => $product->is_in_stock(),
			'variations'  => array(),
		);

		// Add variations for variable products
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();
			foreach ( $variations as $variation_data ) {
				$variation = wc_get_product( $variation_data['variation_id'] );
				if ( $variation ) {
					$product_data['variations'][] = array(
						'external_id' => (string) $variation->get_id(),
						'sku'         => $variation->get_sku(),
						'title'       => implode( ', ', $variation->get_variation_attributes() ),
						'price'       => (float) $variation->get_price(),
						'active'      => true,
					);
				}
			}
		} else {
			// For simple products, add a single variation
			$product_data['variations'][] = array(
				'external_id' => (string) $product_id,
				'sku'         => $product->get_sku(),
				'price'       => (float) $product->get_price(),
				'active'      => true,
			);
		}

		// Sync product to ReviewApp
		$result = $this->api_client->sync_product( $product_data );

		if ( ! is_wp_error( $result ) ) {
			// Update last synced timestamp
			update_post_meta( $product_id, '_reviewapp_last_synced', time() );
			error_log( 'ReviewApp: Auto-synced product #' . $product_id . ' during order processing' );
		} else {
			error_log( 'ReviewApp: Failed to auto-sync product #' . $product_id . ': ' . $result->get_error_message() );
		}
	}
}
