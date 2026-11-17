<?php
/**
 * Run: php tests/CouponControllerCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Isolated check with WooCommerce stubs.
define( 'ABSPATH', __DIR__ );
class WooCommerce {}
class WP_Error { public function __construct( public $code, public $message, public $data ) {} }
class WP_REST_Request {
    public function __construct( public $params ) {}
    public function get_param( $key ) { return $this->params[$key] ?? null; }
}
class WP_REST_Response { public function __construct( public $data, public $status ) {} }
class WC_Coupon {
    public $id;
    public $data;
    public function __construct( $id = 0 ) {
        $this->id = $id;
        $this->data = $id ? array( 'usage_limit' => 0, 'usage_limit_per_user' => 0, 'email_restrictions' => array( 'template@example.com' ), 'amount' => 20, 'minimum_amount' => 50 ) : array();
    }
    public function __call( $method, $args ) {
        $key = substr( $method, 4 );
        if ( str_starts_with( $method, 'get_' ) ) { return $this->data[$key] ?? null; }
        $this->data[$key] = $args[0];
    }
    public function add_meta_data( ...$args ) {}
    public function save() { $this->id = 100; $GLOBALS['saved'][] = $this; }
    public function get_id() { return $this->id; }
}
function __( $text ) { return $text; }
function is_email( $email ) { return filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wc_format_coupon_code( $code ) { return strtolower( trim( $code ) ); }
function wc_get_coupon_types() { return array( 'percent' => 'Percentage', 'fixed_cart' => 'Fixed cart' ); }
function rest_parse_date( $date ) { return strtotime( $date ); }
function wc_get_coupon_id_by_code( $code ) { return in_array( strtolower( $code ), array( 'template', 'duplicate' ), true ) ? 5 : 0; }
function wp_update_post( $post ) { throw new RuntimeException( 'Do not bypass coupon setters.' ); }
function register_rest_route( $namespace, $route, $args ) { $GLOBALS['route_args'] = $args['args']; }
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } ++$GLOBALS['checks']; }
require __DIR__ . '/../src/Api/CouponController.php';
$saved = array();
$checks = 0;
$controller = new reviewbird\Api\CouponController();
foreach ( array( null, 'TEMPLATE' ) as $template ) {
    $result = $controller->create_coupon( new WP_REST_Request( array(
        'code' => ' REVIEW-123 ', 'template_code' => $template, 'reviewer_email' => 'buyer@example.com',
        'discount_type' => 'percent', 'amount' => 10, 'expiry_date' => '2027-01-01',
    ) ) );
    check( 100 === $result->data['coupon_id'], 'Create the coupon.' );
    $coupon = end( $saved );
    check( 'review-123' === $result->data['code'] && 'review-123' === $coupon->data['code'], 'Return the native normalized coupon code.' );
    check( 1 === $coupon->data['usage_limit'] && 1 === $coupon->data['usage_limit_per_user'], 'Both coupon paths must be single use.' );
    check( array( 'buyer@example.com' ) === $coupon->data['email_restrictions'], 'Restrict the coupon to the reviewer, not the template recipient.' );
    if ( $template ) {
        check( 20 === $coupon->data['amount'] && 50 === $coupon->data['minimum_amount'], 'Keep the template discount and cart constraints.' );
    }
}
foreach ( array(
    array( 'code' => 'DUPLICATE', 'expected' => 'duplicate_coupon' ),
    array( 'code' => 'NEW', 'expiry_date' => '2027-02-30', 'expected' => 'invalid_expiry_date' ),
    array( 'code' => 'NEW', 'expiry_date' => 'invalid', 'expected' => 'invalid_expiry_date' ),
) as $params ) {
    $before = count( $saved );
    $result = $controller->create_coupon( new WP_REST_Request( $params ) );
    check( $result instanceof WP_Error && $params['expected'] === $result->code && $before === count( $saved ), 'Reject duplicate codes before saving.' );
}
$controller->register_routes();
check( true === $route_args['code']['required'] && 'string' === $route_args['code']['type'], 'Require a string coupon code in the route schema.' );
check( 'email' === $route_args['reviewer_email']['format'] && array( 'string', 'null' ) === $route_args['reviewer_email']['type'], 'Validate the reviewer email in the route schema.' );
check( 'number' === $route_args['amount']['type'] && 0 === $route_args['amount']['minimum'] && true === $route_args['amount']['exclusiveMinimum'], 'Require a positive number before the coupon setter.' );
check( array_keys( wc_get_coupon_types() ) === $route_args['discount_type']['enum'], 'Use native coupon types.' );
echo "Coupon restrictions: {$checks} checks passed.\n";
