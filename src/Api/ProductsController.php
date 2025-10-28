<?php
/**
 * Products REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Products controller class.
 */
class ProductsController {

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'reviewbird/v1',
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'status'   => array(
						'default'           => 'publish',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Get products with embedded variations.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_products( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'reviewbird-reviews' ),
				array( 'status' => 503 )
			);
		}

		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$status   = $request->get_param( 'status' );

		// Query products
		$args = array(
			'status'   => $status,
			'limit'    => $per_page,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'ID',
			'order'    => 'ASC',
		);

		$results  = wc_get_products( $args );
		$products = array();

		foreach ( $results->products as $product ) {
			$product_data = $this->format_product( $product );

			// Add variations if this is a variable product
			if ( $product->is_type( 'variable' ) ) {
				$product_data['variations'] = $this->get_product_variations( $product );
			} else {
				$product_data['variations'] = array();
			}

			$products[] = $product_data;
		}

		return new WP_REST_Response( $products, 200 );
	}

	/**
	 * Format product data.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array Formatted product data.
	 */
	private function format_product( $product ) {
		$images = array();
		foreach ( $product->get_gallery_image_ids() as $image_id ) {
			$images[] = wp_get_attachment_url( $image_id );
		}

		return array(
			'id'           => $product->get_id(),
			'name'         => $product->get_name(),
			'slug'         => $product->get_slug(),
			'permalink'    => $product->get_permalink(),
			'type'         => $product->get_type(),
			'status'       => $product->get_status(),
			'image'        => wp_get_attachment_url( $product->get_image_id() ),
			'images'       => $images,
			'stock_status' => $product->get_stock_status(),
			'in_stock'     => $product->is_in_stock(),
		);
	}

	/**
	 * Get product variations.
	 *
	 * @param \WC_Product_Variable $product Variable product object.
	 * @return array Array of formatted variations.
	 */
	private function get_product_variations( $product ) {
		$variations      = array();
		$variation_ids   = $product->get_children();

		foreach ( $variation_ids as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation || ! $variation->exists() ) {
				continue;
			}

			$variations[] = $this->format_variation( $variation );
		}

		return $variations;
	}

	/**
	 * Format variation data.
	 *
	 * @param \WC_Product_Variation $variation Variation object.
	 * @return array Formatted variation data.
	 */
	private function format_variation( $variation ) {
		return array(
			'id'         => $variation->get_id(),
			'sku'        => $variation->get_sku(),
			'price'      => $variation->get_price(),
			'image'      => wp_get_attachment_url( $variation->get_image_id() ),
			'attributes' => $variation->get_attributes(),
			'in_stock'   => $variation->is_in_stock(),
		);
	}

	/**
	 * Check permission for API requests.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool Whether the request has permission.
	 */
	public function permission_callback( WP_REST_Request $request ) {
		$api_key    = $request->get_header( 'X-API-Key' );
		$saved_key = get_option( 'reviewbird_api_key' );

		if ( empty( $api_key ) || empty( $saved_key ) ) {
			return false;
		}

		return hash_equals( $saved_key, $api_key );
	}
}
