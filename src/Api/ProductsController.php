<?php
/**
 * Products REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Product_Variable;
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

		register_rest_route(
			'reviewbird/v1',
			'/products/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_product' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
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
			return new WP_Error( 'woocommerce_not_active', __( 'WooCommerce is not active', 'reviewbird' ), array( 'status' => 503 ) );
		}

		$args = array(
			'status'   => $request->get_param( 'status' ),
			'limit'    => $request->get_param( 'per_page' ),
			'page'     => $request->get_param( 'page' ),
			'paginate' => true,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'lang'     => '',
		);

		$modified_after = $request->get_param( 'modified_after' );
		if ( $modified_after ) {
			$args['date_modified'] = '>=' . $modified_after;
		}

		$results = wc_get_products( $args );

		$products = array();
		foreach ( $results->products as $product ) {
			$shared = $this->get_export_product( $product->get_id() );
			if ( $shared ) {
				$products[ reviewbird_product_id( $shared->get_id() ) ] = $this->format_product_with_variations( $shared );
			}
		}

		$response = new WP_REST_Response( array_values( $products ), 200 );
		$response->header( 'X-Reviewbird-Version', REVIEWBIRD_VERSION );
		$response->header( 'X-Reviewbird-Shared-Products', '1' );
		$response->header( 'X-Reviewbird-Product-Identities', '1' );
		// Page counts describe the complete native catalog traversal.
		$response->header( 'X-WP-Total', (int) $results->total );
		$response->header( 'X-WP-TotalPages', (int) $results->max_num_pages );

		return $response;
	}

	/**
	 * Get a single product with embedded variations.
	 *
	 * Variation IDs resolve to their parent product so the response always
	 * describes the full product with its variations.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function get_product( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'woocommerce_not_active', __( 'WooCommerce is not active', 'reviewbird' ), array( 'status' => 503 ) );
		}

		$requested_id = reviewbird_product_id( absint( $request->get_param( 'id' ) ) );
		$product      = $this->get_export_product( $requested_id );

		if ( ! $product || ! $product->exists() ) {
			return new WP_Error( 'product_not_found', __( 'Product not found', 'reviewbird' ), array( 'status' => 404 ) );
		}

		if ( $product->is_type( 'variation' ) ) {
			$parent = $this->get_export_product( $product->get_parent_id() );

			if ( $parent && $parent->exists() ) {
				$product = $parent;
			}
		}

		$response = new WP_REST_Response( $this->format_product_with_variations( $product ), 200 );
		$response->header( 'X-Reviewbird-Version', REVIEWBIRD_VERSION );
		$response->header( 'X-Reviewbird-Shared-Products', '1' );
		$response->header( 'X-Reviewbird-Product-Identities', '1' );
		$response->header( 'X-Reviewbird-Product-ID', (string) $requested_id );

		return $response;
	}

	/**
	 * Use a published translation when the saved product is not published.
	 *
	 * @param int $product_id Actual or shared product ID.
	 * @return \WC_Product|false
	 */
	private function get_export_product( int $product_id ) {
		$product = reviewbird_find_product( reviewbird_product_id( $product_id ) );
		if ( $product && 'publish' !== $product->get_status() ) {
			foreach ( reviewbird_product_translation_ids( $product->get_id() ) as $translation_id ) {
				$translation = wc_get_product( $translation_id );
				if ( $translation && 'publish' === $translation->get_status() ) {
					return $translation;
				}
			}
		}
		return $product;
	}

	/**
	 * Get native product IDs that redirect to the shared product identity.
	 *
	 * @param int $product_id Actual product ID.
	 * @return int[]
	 */
	private function get_legacy_external_ids( int $product_id ): array {
		return array_values( array_diff( reviewbird_product_translation_ids( $product_id ), array( reviewbird_product_id( $product_id ) ) ) );
	}

	/**
	 * Format product data including variations.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array Formatted product data with variations.
	 */
	private function format_product_with_variations( $product ): array {
		return array(
			'id'                  => reviewbird_product_id( $product->get_id() ),
			'legacy_external_ids' => $this->get_legacy_external_ids( $product->get_id() ),
			'name'                => $product->get_name(),
			'slug'                => $product->get_slug(),
			'permalink'           => $product->get_permalink(),
			'type'                => $product->get_type(),
			'status'              => $product->get_status(),
			'sku'                 => $product->get_sku(),
			'global_unique_id'    => method_exists( $product, 'get_global_unique_id' ) ? $product->get_global_unique_id() : '',
			'brand'               => $this->get_product_brand( $product ),
			'price'               => $product->get_price(),
			'image'               => wp_get_attachment_url( $product->get_image_id() ),
			'images'              => array_filter( array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() ) ),
			'stock_status'        => $product->get_stock_status(),
			'in_stock'            => $product->is_in_stock(),
			'tags'                => $this->get_product_tags( $product ),
			'categories'          => $this->get_product_categories( $product ),
			'variations'          => $product->is_type( 'variable' ) ? $this->get_product_variations( $product ) : array(),
		);
	}

	/**
	 * Get product tags.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array Array of tags with id, name, and slug.
	 */
	private function get_product_tags( $product ): array {
		$terms = get_the_terms( $product->get_id(), 'product_tag' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$tags = array();

		foreach ( $terms as $term ) {
			$tags[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}

		return $tags;
	}

	/**
	 * Get product categories with their ancestors (root first).
	 *
	 * @param \WC_Product $product Product object.
	 * @return array Array of categories with id, name, slug, full_name, and ancestors.
	 */
	private function get_product_categories( $product ): array {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$ancestors      = array();
			$ancestor_names = array();

			foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat' ) ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, 'product_cat' );

				if ( ! $ancestor || is_wp_error( $ancestor ) ) {
					continue;
				}

				$ancestor_names[] = $ancestor->name;
				$ancestors[]      = array(
					'id'        => $ancestor->term_id,
					'name'      => $ancestor->name,
					'slug'      => $ancestor->slug,
					'full_name' => implode( ' > ', $ancestor_names ),
				);
			}

			$categories[] = array(
				'id'        => $term->term_id,
				'name'      => $term->name,
				'slug'      => $term->slug,
				'full_name' => implode( ' > ', array_merge( $ancestor_names, array( $term->name ) ) ),
				'ancestors' => $ancestors,
			);
		}

		return $categories;
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

			$shared_id = reviewbird_product_id( $variation->get_id() );
			$variation = $this->get_export_product( $shared_id );
			if ( $variation ) {
				$variations[ $shared_id ] = $this->format_variation( $variation );
			}
		}

		return array_values( $variations );
	}

	/**
	 * Format variation data.
	 *
	 * @param \WC_Product_Variation $variation Variation object.
	 * @return array Formatted variation data.
	 */
	private function format_variation( $variation ): array {
		return array(
			'id'                  => reviewbird_product_id( $variation->get_id() ),
			'legacy_external_ids' => $this->get_legacy_external_ids( $variation->get_id() ),
			'sku'                 => $variation->get_sku(),
			'global_unique_id'    => method_exists( $variation, 'get_global_unique_id' ) ? $variation->get_global_unique_id() : '',
			'brand'               => $this->get_product_brand( $variation ),
			'price'               => $variation->get_price(),
			'image'               => wp_get_attachment_url( $variation->get_image_id() ),
			'attributes'          => $variation->get_attributes(),
			'in_stock'            => $variation->is_in_stock(),
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
