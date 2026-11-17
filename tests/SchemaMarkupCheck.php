<?php
/**
 * Run: php tests/SchemaMarkupCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Isolated check with WordPress and Action Scheduler stubs.

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../src/Integration/SchemaScheduler.php';
require __DIR__ . '/../src/Integration/SchemaMarkup.php';

class ActionScheduler {
    public static function is_initialized() { return true; }
}
function reviewbird_is_schema_enabled() { return $GLOBALS['enabled']; }
function reviewbird_is_store_connected() { return $GLOBALS['connected']; }
function reviewbird_product_meta_id( $id ) { return $id; }
function absint( $id ) { return abs( (int) $id ); }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function as_next_scheduled_action( $hook, $args ) { return $GLOBALS['queued'][ $args[0] ] ?? false; }
function as_enqueue_async_action( $hook, $args, $group ) { $GLOBALS['queued'][ $args[0] ] = true; ++$GLOBALS['jobs']; }
function check( $condition ) {
    if ( ! $condition ) { throw new RuntimeException( 'Schema recovery check failed.' ); }
}

$product = new class {
    public function get_id() { return 123; }
};
$schema = new reviewbird\Integration\SchemaMarkup();
$key = reviewbird\Integration\SchemaScheduler::META_KEY;
$original = array( 'review' => array( 'native review' ) );
foreach ( array( '', 'invalid', array(), array( array( '@type' => 'Review' ) ) ) as $cached ) {
    $GLOBALS['enabled'] = true;
    $GLOBALS['connected'] = true;
    $GLOBALS['queued'] = array();
    $GLOBALS['jobs'] = 0;
    $GLOBALS['meta'] = array( '_reviewbird_avg_stars' => 4.96, '_reviewbird_reviews_count' => 12, $key => $cached );
    $markup = $schema->filter_woocommerce_structured_data( $original, $product );
    $schema->filter_woocommerce_structured_data( $original, $product );
    check( 4.96 === $markup['aggregateRating']['ratingValue'] );
    check( ( empty( $cached ) || ! is_array( $cached ) ? 1 : 0 ) === $GLOBALS['jobs'] );
    check( ( is_array( $cached ) && $cached ? $cached : $original['review'] ) === $markup['review'] );
}
foreach ( array( 'disabled', 'disconnected', 'zero', 'unknown' ) as $state ) {
    $GLOBALS['enabled'] = 'disabled' !== $state;
    $GLOBALS['connected'] = 'disconnected' !== $state;
    $GLOBALS['queued'] = array();
    $GLOBALS['jobs'] = 0;
    $GLOBALS['meta'] = array( '_reviewbird_reviews_count' => 'zero' === $state ? 0 : '' );
    $schema->filter_woocommerce_structured_data( $original, $product );
    check( 0 === $GLOBALS['jobs'] );
}
echo "Schema recovery checks passed.\n";
