<?php
/**
 * WooCommerce integration for ReviewBop.
 *
 * @package ReviewBop
 */

namespace ReviewBop\Integration;

use ReviewBop\Api\Client;

/**
 * WooCommerce integration class.
 */
class WooCommerce {

	/**
	 * API client instance.
	 *
	 * @var Client
	 */
	private $api_client;

	/**
	 * Initialize WooCommerce integration.
	 */
	public function __construct() {
		$this->api_client = new Client();
	}

	/**
	 * Sync product data to ReviewBop.
	 *
	 * @param int $product_id WooCommerce product ID.
	 */
	public function sync_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		// Determine in_store status based on product status
		$post_status = get_post_status( $product_id );
		$in_store = in_array( $post_status, array( 'publish' ), true );

		$product_data = array(
			'external_id' => (string) $product_id,
			'sku'         => $product->get_sku(),
			'title'       => $product->get_name(),
			'slugs'       => array( $product->get_slug() ),
			'url'         => get_permalink( $product_id ),
			'image'       => wp_get_attachment_url( $product->get_image_id() ),
			'in_store'    => $in_store,
		);

		// Use Action Scheduler for reliable background processing.
		$action_id = $this->api_client->queue_product_sync( $product_data );

		if ( ! $action_id ) {
			// Fallback to immediate sync if Action Scheduler unavailable.
			$result = $this->api_client->sync_product( $product_data );
			
			if ( is_wp_error( $result ) ) {
				error_log(
					sprintf(
						/* translators: 1: Product ID, 2: Error message */
						__( 'ReviewBop: Failed to sync product %1$d: %2$s', 'reviewbop-reviews' ),
						$product_id,
						$result->get_error_message()
					)
				);
			}
		}
	}

	/**
	 * Handle product meta updates.
	 *
	 * @param int    $meta_id     Meta ID.
	 * @param int    $object_id   Post ID.
	 * @param string $meta_key    Meta key.
	 * @param mixed  $meta_value  Meta value.
	 */
	public function handle_product_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Only process product posts
		if ( 'product' !== get_post_type( $object_id ) ) {
			return;
		}

		// Re-sync product on relevant meta changes
		$relevant_meta_keys = array( '_price', '_regular_price', '_sale_price', '_stock_status', '_manage_stock', '_stock' );
		if ( in_array( $meta_key, $relevant_meta_keys, true ) ) {
			$this->sync_product( $object_id );
		}
	}

	/**
	 * Handle product duplication.
	 *
	 * @param int $duplicate_id ID of the duplicated product.
	 * @param int $original_id  ID of the original product.
	 */
	public function handle_product_duplicate( $duplicate_id, $original_id ) {
		// Sync the new duplicate as a separate product
		$this->sync_product( $duplicate_id );
	}

	/**
	 * Handle product being trashed.
	 *
	 * @param int $product_id Product ID.
	 */
	public function handle_product_trash( $product_id ) {
		// Only process product posts
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		// Re-sync with in_store = false
		$this->sync_product( $product_id );
	}

	/**
	 * Handle product being untrashed (restored).
	 *
	 * @param int $product_id Product ID.
	 */
	public function handle_product_untrash( $product_id ) {
		// Only process product posts
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		// Re-sync with updated in_store status
		$this->sync_product( $product_id );
	}

	/**
	 * Handle product being permanently deleted.
	 *
	 * @param int $product_id Product ID.
	 */
	public function handle_product_delete( $product_id ) {
		// Only process product posts
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		// Mark product as deleted in ReviewBop (in_store = false)
		$product_data = array(
			'external_id' => (string) $product_id,
			'in_store'    => false,
			'active'      => false,
		);

		$result = $this->api_client->sync_product( $product_data );
		
		if ( is_wp_error( $result ) ) {
			error_log(
				sprintf(
					/* translators: 1: Product ID, 2: Error message */
					__( 'ReviewBop: Failed to mark product %1$d as deleted: %2$s', 'reviewbop-reviews' ),
					$product_id,
					$result->get_error_message()
				)
			);
		}
	}

	/**
	 * Sync review data to ReviewBop.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function sync_review( $comment_id ) {
		$comment = get_comment( $comment_id );
		
		if ( ! $comment || 'review' !== $comment->comment_type ) {
			return;
		}

		$review_data = array(
			'product_external_id' => (string) $comment->comment_post_ID,
			'review_external_id'  => 'wp_' . $comment_id,
			'author'              => array(
				'name'  => $comment->comment_author,
				'email' => $comment->comment_author_email,
			),
			'rating'              => get_comment_meta( $comment_id, 'rating', true ) ?: 5,
			'body'                => $comment->comment_content,
			'status'              => $comment->comment_approved ? 'approved' : 'pending',
			'verified_purchase'   => (bool) get_comment_meta( $comment_id, 'verified', true ),
			'created_at'          => gmdate( 'c', strtotime( $comment->comment_date_gmt ) ),
		);

		// Use Action Scheduler for reliable processing.
		$action_id = $this->api_client->queue_review_push( $review_data );

		if ( ! $action_id ) {
			// Fallback to immediate sync.
			$result = $this->api_client->push_review( $review_data );
			
			if ( is_wp_error( $result ) ) {
				error_log(
					sprintf(
						/* translators: 1: Comment ID, 2: Error message */
						__( 'ReviewBop: Failed to sync review %1$d: %2$s', 'reviewbop-reviews' ),
						$comment_id,
						$result->get_error_message()
					)
				);
			}
		}
	}

	/**
	 * Sync review status changes to ReviewBop.
	 *
	 * @param int    $comment_id     Comment ID.
	 * @param string $comment_status New comment status.
	 */
	public function sync_review_status( $comment_id, $comment_status ) {
		$comment = get_comment( $comment_id );
		
		if ( ! $comment || 'review' !== $comment->comment_type ) {
			return;
		}

		// Re-sync the review with updated status.
		$this->sync_review( $comment_id );
	}

	/**
	 * Delete review from ReviewBop.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function delete_review( $comment_id ) {
		$comment = get_comment( $comment_id );
		
		if ( ! $comment || 'review' !== $comment->comment_type ) {
			return;
		}

		// Only allow deletion from admin context with proper permissions.
		if ( ! is_admin() || ! current_user_can( 'moderate_comments' ) ) {
			return;
		}

		$delete_data = array(
			'review_external_id' => 'wp_' . $comment_id,
			'reason'             => 'merchant_deleted',
		);

		// Use Action Scheduler for reliable processing.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'reviewbop_delete_review', $delete_data );
		} else {
			// Fallback to immediate deletion.
			$result = $this->api_client->delete_review( $delete_data );
			
			if ( is_wp_error( $result ) ) {
				error_log(
					sprintf(
						/* translators: 1: Comment ID, 2: Error message */
						__( 'ReviewBop: Failed to delete review %1$d: %2$s', 'reviewbop-reviews' ),
						$comment_id,
						$result->get_error_message()
					)
				);
			}
		}
	}

	/**
	 * Track order events in ReviewBop.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Old order status.
	 * @param string $new_status New order status.
	 */
	public function track_order_event( $order_id, $old_status, $new_status ) {
		// Only track completion events for review follow-up.
		if ( ! in_array( $new_status, array( 'completed', 'delivered' ), true ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$order_data = array(
			'order_id'     => 'wc_' . $order_id,
			'status'       => $new_status,
			'completed_at' => current_time( 'mysql', true ),
			'event_type'   => 'order_status',
			'metadata'     => array(
				'previous_status' => $old_status,
				'customer_email'  => $order->get_billing_email(),
				'items'           => array(),
			),
		);

		// Add order items for review follow-up emails.
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$order_data['metadata']['items'][] = array(
					'product_id' => $product->get_id(),
					'name'       => $product->get_name(),
					'quantity'   => $item->get_quantity(),
				);
			}
		}

		// Use Action Scheduler for reliable processing.
		$action_id = $this->api_client->queue_order_event( $order_data );

		if ( ! $action_id ) {
			// Fallback to immediate tracking.
			$result = $this->api_client->track_order_event( $order_data );
			
			if ( is_wp_error( $result ) ) {
				error_log(
					sprintf(
						/* translators: 1: Order ID, 2: Error message */
						__( 'ReviewBop: Failed to track order event %1$d: %2$s', 'reviewbop-reviews' ),
						$order_id,
						$result->get_error_message()
					)
				);
			}
		}
	}

	/**
	 * Add review widget to product page.
	 */
	public function add_widget_to_product_page() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$store_id = $this->api_client->get_store_id_for_frontend();
		if ( ! $store_id ) {
			// Add HTML comment for debugging
			echo '<!-- ReviewBop: Widget not displayed. Store ID not configured. Please connect your ReviewBop account in WP Admin > ReviewBop > Settings -->';
			return;
		}

		// Allow developers to disable widget for specific products.
		if ( ! apply_filters( 'reviewbop_show_widget_for_product', true, $product ) ) {
			return;
		}

		$widget_id = 'reviewbop-widget-container-' . $product->get_id();

		// Allow developers to customize widget attributes.
		$widget_attrs = apply_filters(
			'reviewbop_widget_attributes',
			array(
				'store-id'    => $store_id,
				'product-key' => $product->get_id(),
			),
			$product
		);

		$attrs_html = '';
		foreach ( $widget_attrs as $key => $value ) {
			$attrs_html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		// Allow developers to customize widget wrapper.
		$widget_html = apply_filters(
			'reviewbop_widget_html',
			sprintf(
				'<div id="%s"%s></div><script>if(typeof ReviewBop !== "undefined") ReviewBop.init();</script>',
				esc_attr( $widget_id ),
				$attrs_html
			),
			$product,
			$widget_id,
			$widget_attrs
		);

		echo $widget_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above.
	}

	/**
	 * Update product rating metadata.
	 *
	 * @param int   $product_id   WooCommerce product ID.
	 * @param float $avg_stars    Average rating (0-5).
	 * @param int   $review_count Number of reviews.
	 */
	public function update_product_rating_meta( $product_id, $avg_stars, $review_count ) {
		$product_id = absint( $product_id );

		if ( ! $product_id ) {
			return;
		}

		update_post_meta( $product_id, '_reviewbop_avg_stars', floatval( $avg_stars ) );
		update_post_meta( $product_id, '_reviewbop_reviews_count', intval( $review_count ) );

		// Clear schema cache when ratings are updated.
		delete_transient( 'reviewbop_schema_reviews_' . $product_id );

		do_action( 'reviewbop_rating_updated', $product_id, $avg_stars, $review_count );
	}

	/**
	 * Output product schema markup in head.
	 */
	public function output_product_schema() {
		if ( ! is_product() ) {
			return;
		}

		// Get product ID from the current post.
		$product_id = get_the_ID();
		if ( ! $product_id ) {
			return;
		}

		// Verify this is actually a product.
		if ( 'product' !== get_post_type( $product_id ) ) {
			return;
		}

		// Load schema markup class.
		$schema_markup = new SchemaMarkup();
		$schema_markup->output_product_schema( $product_id );
	}
}