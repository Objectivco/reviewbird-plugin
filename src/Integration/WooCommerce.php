<?php
/**
 * WooCommerce integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

use reviewbird\Api\Client;
use reviewbird\Services\HealthChecker;

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
	 * Health checker instance.
	 *
	 * @var \reviewbird\Services\HealthChecker
	 */
	private $health_checker;

	/**
	 * Initialize WooCommerce integration.
	 *
	 * @param \reviewbird\Services\HealthChecker $health_checker Optional health checker instance.
	 */
	public function __construct( $health_checker = null ) {
		$this->api_client = new Client();
		$this->health_checker = $health_checker;
	}

	/**
	 * Render the reviewbird widget.
	 *
	 * This method can be called from templates or hooks to display the widget.
	 */
	public function render_widget() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$store_id = $this->api_client->get_store_id_for_frontend();
		if ( ! $store_id ) {
			// Add HTML comment for debugging
			echo '<!-- reviewbird: Widget not displayed. Store ID not configured. Please connect your reviewbird account in WP Admin > reviewbird > Settings -->';
			return;
		}

		// Allow developers to disable widget for specific products.
		if ( ! apply_filters( 'reviewbird_show_widget_for_product', true, $product ) ) {
			return;
		}

		$widget_id = 'reviewbird-widget-container-' . $product->get_id();

		// Allow developers to customize widget attributes.
		$widget_attrs = apply_filters(
			'reviewbird_widget_attributes',
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
			'reviewbird_widget_html',
			sprintf(
				'<div id="%s"%s></div><script>if(typeof reviewbird !== "undefined") reviewbird.init();</script>',
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

		update_post_meta( $product_id, '_reviewbird_avg_stars', floatval( $avg_stars ) );
		update_post_meta( $product_id, '_reviewbird_reviews_count', intval( $review_count ) );

		// Clear schema cache when ratings are updated.
		delete_transient( 'reviewbird_schema_reviews_' . $product_id );

		do_action( 'reviewbird_rating_updated', $product_id, $avg_stars, $review_count );
	}

	/**
	 * Output product schema markup in head.
	 */
	public function output_product_schema() {
		if ( ! is_product() ) {
			return;
		}

		// Check if store has subscription
		if ( ! $this->health_checker || ! $this->health_checker->hasSubscription() ) {
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