<?php
/**
 * Display reviewbird reviews widget
 *
 * This template overrides WooCommerce's single-product-reviews.php to display
 * the reviewbird widget instead of native WordPress comments.
 *
 * @package reviewbird
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! comments_open() ) {
	return;
}

// Get the WooCommerce integration instance to access widget rendering
echo reviewbird_render_widget();
