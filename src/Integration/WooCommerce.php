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

}
