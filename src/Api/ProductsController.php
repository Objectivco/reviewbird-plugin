<?php
/**
 * Products REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

use WC_Product_Variable;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Products controller class.
 */
class ProductsController {

	/**
	 * Create an error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error The error response.
	 */
	private static function error( string $code, string $message, int $status = 400 ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @return WP_Error|null Error if WooCommerce is not active, null otherwise.
	 */
	private static function require_woocommerce() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return self::error( 'woocommerce_not_active', 'WooCommerce is not active', 503 );
		}
		return null;
	}

	/**
	 * Get image URLs from an array of attachment IDs.
	 *
	 * @param array $image_ids Array of attachment IDs.
	 * @return array Array of image URLs.
	 */
	private static function get_image_urls( array $image_ids ): array {
		return array_filter( array_map( 'wp_get_attachment_url', $image_ids ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'reviewbird/v1',
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'per_page'       => array(
						'default'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'           => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'status'         => array(
						'default'           => 'publish',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'modified_after' => array(
						'default'           => null,
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
		$wc_error = self::require_woocommerce();
		if ( $wc_error ) {
			return $wc_error;
		}

		$args = array(
			'status'   => $request->get_param( 'status' ),
			'limit'    => $request->get_param( 'per_page' ),
			'page'     => $request->get_param( 'page' ),
			'paginate' => true,
			'orderby'  => 'ID',
			'order'    => 'ASC',
		);

		$modified_after = $request->get_param( 'modified_after' );
		if ( $modified_after ) {
			$args['date_modified'] = '>=' . $modified_after;
		}

		$results = wc_get_products( $args );

		$products = array_map( array( $this, 'format_product_with_variations' ), $results->products );

		return new WP_REST_Response( $products, 200 );
	}

	/**
	 * Format product data including variations.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array Formatted product data with variations.
	 */
	private function format_product_with_variations( $product ): array {
		$product_data               = $this->format_product( $product );
		$product_data['variations'] = $product->is_type( 'variable' )
			? $this->get_product_variations( $product )
			: array();

		return $product_data;
	}

	/**
	 * Format product data.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array Formatted product data.
	 */
	private function format_product( $product ): array {
		return array(
			'id'               => $product->get_id(),
			'name'             => $product->get_name(),
			'slug'             => $product->get_slug(),
			'permalink'        => $product->get_permalink(),
			'type'             => $product->get_type(),
			'status'           => $product->get_status(),
			'sku'              => $product->get_sku(),
			'global_unique_id' => $product->get_global_unique_id(),
			'brand'            => $this->get_product_brand( $product ),
			'price'            => $product->get_price(),
			'image'            => wp_get_attachment_url( $product->get_image_id() ),
			'images'           => self::get_image_urls( $product->get_gallery_image_ids() ),
			'stock_status'     => $product->get_stock_status(),
			'in_stock'         => $product->is_in_stock(),
		);
	}

	/**
	 * Get product brand from taxonomy or meta.
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Brand name or null if not found.
	 */
	private function get_product_brand( $product ): ?string {
		// Try taxonomy first (product_brand)
		$brands = wp_get_post_terms( $product->get_id(), 'product_brand', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $brands ) && ! empty( $brands ) ) {
			return $brands[0];
		}

		// Fallback to meta
		$meta_brand = $product->get_meta( '_brand' );
		return $meta_brand ? $meta_brand : null;
	}

	/**
	 * Get product variations.
	 *
	 * @param WC_Product_Variable $product Variable product object.
	 * @return array Array of formatted variations.
	 */
	private function get_product_variations( $product ): array {
		$variations = array();

		foreach ( $product->get_children() as $variation_id ) {
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
	private function format_variation( $variation ): array {
		return array(
			'id'               => $variation->get_id(),
			'sku'              => $variation->get_sku(),
			'global_unique_id' => $variation->get_global_unique_id(),
			'brand'            => $this->get_product_brand( $variation ),
			'price'            => $variation->get_price(),
			'image'            => wp_get_attachment_url( $variation->get_image_id() ),
			'attributes'       => $variation->get_attributes(),
			'in_stock'         => $variation->is_in_stock(),
		);
	}

	/**
	 * Check permission for API requests.
	 * Uses WooCommerce authentication (consumer key/secret) via wc_rest_check_post_permissions.
	 *
	 * @return bool Whether the request has permission.
	 */
	public function permission_callback( WP_REST_Request $request ): bool {
		return wc_rest_check_post_permissions( 'product', 'read', 0 );
	}
}
