<?php
/**
 * Product REST API endpoint for ReviewApp.
 *
 * @package ReviewApp
 */

namespace ReviewApp\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Product endpoint class.
 */
class ProductEndpoint {

	/**
	 * Get product data by external ID.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_product( WP_REST_Request $request ) {
		$external_id = $request->get_param( 'external_id' );

		if ( empty( $external_id ) ) {
			return new WP_Error(
				'missing_external_id',
				__( 'External ID is required', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		// Try to find product by external_id (which is the WC product ID)
		$product_id = absint( $external_id );

		if ( ! $product_id ) {
			return new WP_Error(
				'invalid_product_id',
				__( 'Invalid product ID', 'reviewapp-reviews' ),
				array( 'status' => 400 )
			);
		}

		// Check if WooCommerce is active
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'reviewapp-reviews' ),
				array( 'status' => 500 )
			);
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return new WP_Error(
				'product_not_found',
				__( 'Product not found', 'reviewapp-reviews' ),
				array( 'status' => 404 )
			);
		}

		// Return product data in ReviewApp format
		$product_data = array(
			'external_id' => (string) $product->get_id(),
			'slug'        => $product->get_slug(),
			'title'       => $product->get_name(),
			'vendor'      => '', // WooCommerce doesn't have vendor by default
			'category'    => $this->get_primary_category( $product ),
			'tags'        => $this->get_product_tags( $product ),
			'description' => $product->get_description(),
			'url'         => get_permalink( $product->get_id() ),
			'image'       => wp_get_attachment_url( $product->get_image_id() ) ?: '',
			'images'      => $this->get_product_images( $product ),
			'active'      => $product->is_purchasable() && 'publish' === $product->get_status(),
			'in_stock'    => $product->is_in_stock(),
		);

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$logger->info(
				sprintf(
					'Product data fetched for ReviewApp: %s (ID: %d)',
					$product->get_name(),
					$product->get_id()
				),
				array( 'source' => 'reviewapp' )
			);
		}

		return new WP_REST_Response( $product_data, 200 );
	}

	/**
	 * Get the primary category for a product.
	 *
	 * @param \WC_Product $product The WooCommerce product.
	 * @return string The primary category name.
	 */
	private function get_primary_category( $product ) {
		$categories = get_the_terms( $product->get_id(), 'product_cat' );

		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			return $categories[0]->name;
		}

		return '';
	}

	/**
	 * Get product tags.
	 *
	 * @param \WC_Product $product The WooCommerce product.
	 * @return array Array of tag names.
	 */
	private function get_product_tags( $product ) {
		$tags = get_the_terms( $product->get_id(), 'product_tag' );

		if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
			return wp_list_pluck( $tags, 'name' );
		}

		return array();
	}

	/**
	 * Get product gallery images.
	 *
	 * @param \WC_Product $product The WooCommerce product.
	 * @return array Array of image URLs.
	 */
	private function get_product_images( $product ) {
		$gallery_ids = $product->get_gallery_image_ids();

		if ( empty( $gallery_ids ) ) {
			return array();
		}

		$images = array();
		foreach ( $gallery_ids as $image_id ) {
			$image_url = wp_get_attachment_url( $image_id );
			if ( $image_url ) {
				$images[] = $image_url;
			}
		}

		return $images;
	}

	/**
	 * Permission callback for product endpoint.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public static function permission_callback( WP_REST_Request $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) ) {
			return new WP_Error(
				'missing_auth',
				__( 'Authorization header is required', 'reviewapp-reviews' ),
				array( 'status' => 401 )
			);
		}

		// Extract Bearer token
		if ( ! preg_match( '/^Bearer\s+(\S+)$/', $auth_header, $matches ) ) {
			return new WP_Error(
				'invalid_auth_format',
				__( 'Invalid Authorization header format', 'reviewapp-reviews' ),
				array( 'status' => 401 )
			);
		}

		$provided_token = $matches[1];
		$stored_token   = get_option( 'reviewapp_store_token' );

		if ( empty( $stored_token ) ) {
			return new WP_Error(
				'no_token_configured',
				__( 'ReviewApp store token not configured', 'reviewapp-reviews' ),
				array( 'status' => 500 )
			);
		}

		// Constant-time comparison to prevent timing attacks
		if ( ! hash_equals( $stored_token, $provided_token ) ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid authentication token', 'reviewapp-reviews' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
