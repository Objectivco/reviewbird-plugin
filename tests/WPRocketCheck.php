<?php
/**
 * Run: php tests/WPRocketCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Small WordPress stubs for this standalone check.

define( 'ABSPATH', __DIR__ );

$filters = array();
function add_filter( $hook, $callback ) {
	$GLOBALS['filters'][ $hook ] = $callback;
}
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require dirname( __DIR__ ) . '/src/Integration/WPRocket.php';

new reviewbird\Integration\WPRocket();
check( array() === $filters, 'Filters were registered without WP Rocket.' );

define( 'WP_ROCKET_VERSION', 'test' );
new reviewbird\Integration\WPRocket();
check(
	array( 'rocket_minify_excluded_external_js', 'rocket_exclude_css' ) === array_keys( $filters ),
	'Only local asset processing should be excluded. Keep delay and defer unchanged.'
);

foreach ( $filters as $hook => $callback ) {
	$existing = array( 'cdn.example.com', '/other-plugin/script.js' );
	$excluded = $callback( $existing );
	check( array_merge( $existing, array( 'app.reviewbird.com' ) ) === $excluded, $hook . ' changed existing exclusions.' );
}

echo "WP Rocket compatibility checks passed.\n";
