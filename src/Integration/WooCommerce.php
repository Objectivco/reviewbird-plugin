<?php
/**
 * WooCommerce integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

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
		add_filter( 'woocommerce_rest_prepare_product_review', array( $this, 'add_review_media_to_rest_response' ), 10, 2 );

		// Save locale when order is created.
		add_action( 'woocommerce_new_order', array( $this, 'save_order_locale' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_order_locale' ), 10, 1 );

		// Expose locale in REST response.
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_locale_to_order_response' ), 10, 3 );
	}

	/**
	 * Add CusRev media data to the WooCommerce product review REST response.
	 *
	 * CusRev stores media as WordPress attachment IDs in comment meta:
	 * - ivole_review_image2: Photo attachment IDs
	 * - ivole_review_video2: Video attachment IDs
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Comment       $review   The review comment object.
	 * @return \WP_REST_Response Modified response with media data.
	 */
	public function add_review_media_to_rest_response( $response, $review ) {
		$media = array();

		// Get CusRev photo meta (attachment IDs).
		$image_ids = get_comment_meta( $review->comment_ID, 'ivole_review_image2', false );
		foreach ( $image_ids as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$media[] = array(
					'type' => 'image',
					'url'  => $url,
				);
			}
		}

		// Get CusRev video meta (attachment IDs).
		$video_ids = get_comment_meta( $review->comment_ID, 'ivole_review_video2', false );
		foreach ( $video_ids as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$media[] = array(
					'type' => 'video',
					'url'  => $url,
				);
			}
		}

		$response->data['media'] = $media;

		return $response;
	}

	/**
	 * Save WordPress locale as order meta when order is created.
	 *
	 * This captures the locale at the time of purchase, not at sync time.
	 * Supports multilingual stores where different customers may order in different languages.
	 *
	 * @param int|\WC_Order $order_id Order ID or order object.
	 * @param \WC_Order|null $order Order object (optional, depends on hook).
	 */
	public function save_order_locale( $order_id, $order = null ) {
		// Handle both hook signatures.
		if ( $order_id instanceof \WC_Order ) {
			$order = $order_id;
		} elseif ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( $order ) {
			$order->update_meta_data( '_reviewbird_locale', get_locale() );
			$order->save();
		}
	}

	/**
	 * Add locale to order REST response.
	 *
	 * Uses saved meta if available, falls back to current site locale for
	 * orders created before locale tracking was implemented.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WC_Order         $order    The order object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response Modified response with locale.
	 */
	public function add_locale_to_order_response( $response, $order, $request ) {
		$saved_locale         = $order->get_meta( '_reviewbird_locale' );
		$response->data['locale'] = $saved_locale ? $saved_locale : get_locale();
		return $response;
	}

}
