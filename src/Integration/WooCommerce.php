<?php
/**
 * WooCommerce integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

use WC_Order;
use WP_Comment;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce integration class.
 */
class WooCommerce {

	/**
	 * Initialize WooCommerce integration.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WooCommerce hooks.
	 */
	private function register_hooks() {
		add_filter(
			'woocommerce_rest_prepare_product_review',
			array(
				$this,
				'add_cusrev_review_media_to_rest_response',
			),
			10,
			2
		);

		// Save locale when order is created.
		add_action( 'woocommerce_new_order', array( $this, 'save_order_locale' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_order_locale' ), 10, 1 );

		// Expose locale in REST response.
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_locale_to_order_response' ), 10, 2 );
	}

	/**
	 * Add CusRev media data to the WooCommerce product review REST response.
	 *
	 * CusRev stores media as WordPress attachment IDs in comment meta:
	 * - ivole_review_image2: Photo attachment IDs
	 * - ivole_review_video2: Video attachment IDs
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WP_Comment       $review   The review comment object.
	 * @return WP_REST_Response Modified response with media data.
	 */
	public function add_cusrev_review_media_to_rest_response( $response, $review ): WP_REST_Response {
		$media = array_merge(
			$this->get_attachment_media( $review->comment_ID, 'ivole_review_image2', 'image' ),
			$this->get_attachment_media( $review->comment_ID, 'ivole_review_video2', 'video' )
		);

		$response->data['media'] = $media;

		return $response;
	}

	/**
	 * Get media items from comment meta attachment IDs.
	 *
	 * @param int    $comment_id The comment ID.
	 * @param string $meta_key   The meta key containing attachment IDs.
	 * @param string $type       The media type ('image' or 'video').
	 * @return array Array of media items with type and url.
	 */
	private function get_attachment_media( $comment_id, $meta_key, $type ): array {
		$media          = array();
		$attachment_ids = get_comment_meta( $comment_id, $meta_key, false );

		foreach ( $attachment_ids as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$media[] = array(
					'type' => $type,
					'url'  => $url,
				);
			}
		}

		return $media;
	}

	/**
	 * Save WordPress locale as order meta when order is created.
	 *
	 * This captures the locale at the time of purchase, not at sync time.
	 * Supports multilingual stores where different customers may order in different languages.
	 *
	 * @param int|WC_Order  $order_id Order ID or order object.
	 * @param WC_Order|null $order Order object (optional, depends on hook).
	 */
	public function save_order_locale( $order_id, $order = null ) {
		$order = $this->resolve_order( $order_id, $order );

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_reviewbird_locale', get_locale() );
		$order->save();
	}

	/**
	 * Resolve order object from various hook signatures.
	 *
	 * @param int|WC_Order  $order_id Order ID or order object.
	 * @param WC_Order|null $order    Order object (optional).
	 *
	 * @return WC_Order|false Order object or false if not found.
	 */
	private function resolve_order( $order_id, $order ) {
		if ( $order_id instanceof WC_Order ) {
			return $order_id;
		}

		if ( $order ) {
			return $order;
		}

		return wc_get_order( $order_id );
	}

	/**
	 * Add locale to order REST response.
	 *
	 * Uses saved meta if available, falls back to current site locale for
	 * orders created before locale tracking was implemented.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WC_Order         $order    The order object.
	 * @param WP_REST_Request  $request  The request object.
	 * @return WP_REST_Response Modified response with locale.
	 */
	public function add_locale_to_order_response( $response, $order ) {
		$saved_locale             = $order->get_meta( '_reviewbird_locale' );
		$response->data['locale'] = $saved_locale ? $saved_locale : get_locale();
		return $response;
	}
}
