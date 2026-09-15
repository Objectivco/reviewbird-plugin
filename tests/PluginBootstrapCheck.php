<?php
/**
 * Run with and without WooCommerce on a disposable WordPress site:
 * wp --path=/path/to/site eval-file tests/PluginBootstrapCheck.php
 * wp --path=/path/to/site --skip-plugins=woocommerce eval-file tests/PluginBootstrapCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- The WordPress bootstrap supplies the test runtime.

if ( ! defined( 'DB_NAME' ) || ! preg_match( '/^reviewbird_scheduler_test_[a-z0-9_]+$/', DB_NAME ) ) {
	throw new RuntimeException( 'Use the disposable test database.' );
}
$active = class_exists( 'WooCommerce' );
if ( shortcode_exists( 'reviewbird_widget' ) !== $active || shortcode_exists( 'reviewbird_showcase' ) !== $active ) {
	throw new RuntimeException( 'Plugin hooks do not match WooCommerce availability.' );
}
do_action( 'wp_enqueue_scripts' );
if ( ! $active && do_shortcode( '[reviewbird_widget]' ) !== '[reviewbird_widget]' ) {
	throw new RuntimeException( 'The widget ran without WooCommerce.' );
}
$routes = rest_get_server()->get_routes();
if ( isset( $routes['/reviewbird/v1/products'] ) !== $active ) {
	throw new RuntimeException( 'REST routes do not match WooCommerce availability.' );
}
if ( $active ) {
	( new reviewbird\Admin\Settings() )->enqueue_scripts( 'reviewbird_page_reviewbird-settings' );
	if ( ! wp_scripts()->all_deps( array( 'reviewbird-admin' ) ) ) {
		throw new RuntimeException( 'The admin build requires a script that WordPress does not provide.' );
	}
}
echo 'Plugin bootstrap passed with WooCommerce ' . ( $active ? 'active' : 'inactive' ) . ".\n";
