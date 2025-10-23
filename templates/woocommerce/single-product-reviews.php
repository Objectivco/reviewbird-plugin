<?php
/**
 * Display ReviewBop reviews widget
 *
 * This template overrides WooCommerce's single-product-reviews.php to display
 * the ReviewBop widget instead of native WordPress comments.
 *
 * @package ReviewBop
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! comments_open() ) {
	return;
}

// Get the WooCommerce integration instance to access widget rendering
$woocommerce_integration = new \ReviewBop\Integration\WooCommerce();
$woocommerce_integration->render_widget();
