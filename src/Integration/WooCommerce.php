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
	}

	/**
	 * Render the reviewbird widget.
	 *
	 * This method can be called from templates or hooks to display the widget.
	 */
	public function render_widget() {
		echo reviewbird_render_widget();
	}

	/**
	 * Output product schema markup in head.
	 */
	public function output_product_schema() {
		if ( ! is_product() ) {
			return;
		}

		// Check if store has subscription
		if ( ! reviewbird_is_store_connected() ) {
			return; // Don't output schema without subscription
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
