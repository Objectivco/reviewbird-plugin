<?php
/**
 * Coupon REST API controller for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Coupon controller class.
 */
class CouponController {

	/**
	 * Coupon properties to clone from template.
	 *
	 * @var array
	 */
	private const CLONEABLE_PROPERTIES = array(
		'discount_type',
		'amount',
		'individual_use',
		'product_ids',
		'excluded_product_ids',
		'limit_usage_to_x_items',
		'free_shipping',
		'product_categories',
		'excluded_product_categories',
		'exclude_sale_items',
		'minimum_amount',
		'maximum_amount',
	);

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'reviewbird/v1',
			'/coupons/create',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_coupon' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'args'                => array(
					'code'           => array(
						'type'     => 'string',
						'required' => true,
					),
					'reviewer_email' => array(
						'type'   => array( 'string', 'null' ),
						'format' => 'email',
					),
					'template_code'  => array( 'type' => array( 'string', 'null' ) ),
					'discount_type'  => array(
						'type' => 'string',
						'enum' => array_keys( wc_get_coupon_types() ),
					),
					'amount'         => array(
						'type'             => 'number',
						'minimum'          => 0,
						'exclusiveMinimum' => true,
						'maximum'          => PHP_FLOAT_MAX,
					),
					'expiry_date'    => array( 'type' => array( 'string', 'null' ) ),
				),
			)
		);
	}

	/**
	 * Permission callback using WooCommerce authentication (consumer key/secret).
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool Whether the request has permission.
	 */
	public function permission_callback( WP_REST_Request $request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Keep the REST permission callback signature.
		return wc_rest_check_post_permissions( 'shop_coupon', 'create', 0 );
	}

	/**
	 * Create a single-use WooCommerce coupon, optionally copying a template coupon.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function create_coupon( WP_REST_Request $request ) {
		$code           = wc_format_coupon_code( $request->get_param( 'code' ) ?? '' );
		$expiry_date    = $request->get_param( 'expiry_date' );
		$template_code  = $request->get_param( 'template_code' );
		$discount_type  = $request->get_param( 'discount_type' );
		$amount         = $request->get_param( 'amount' );
		$reviewer_email = $request->get_param( 'reviewer_email' );

		// The route schema validates types and the reviewer email format.
		if ( '' === trim( $code ) ) {
			return new WP_Error( 'missing_code', __( 'Coupon code is required', 'reviewbird' ), array( 'status' => 400 ) );
		}
		if ( $expiry_date && ( false === rest_parse_date( 10 === strlen( $expiry_date ) ? $expiry_date . 'T00:00:00' : $expiry_date )
			|| ! checkdate( (int) substr( $expiry_date, 5, 2 ), (int) substr( $expiry_date, 8, 2 ), (int) substr( $expiry_date, 0, 4 ) ) ) ) {
			return new WP_Error( 'invalid_expiry_date', __( 'Use a valid date or ISO 8601 date and time for coupon expiry.', 'reviewbird' ), array( 'status' => 400 ) );
		}
		if ( wc_get_coupon_id_by_code( $code ) ) {
			return new WP_Error( 'duplicate_coupon', __( 'This coupon code already exists.', 'reviewbird' ), array( 'status' => 409 ) );
		}

		$coupon = new \WC_Coupon();
		try {
			if ( ! empty( $template_code ) ) {
				$template_id = wc_get_coupon_id_by_code( $template_code );
				if ( ! $template_id ) {
					// translators: %s: Template coupon code.
					return new WP_Error( 'template_not_found', sprintf( __( "Template coupon '%s' not found", 'reviewbird' ), $template_code ), array( 'status' => 404 ) );
				}
				$template = new \WC_Coupon( $template_id );
				foreach ( self::CLONEABLE_PROPERTIES as $property ) {
					$coupon->{"set_{$property}"}( $template->{"get_{$property}"}() );
				}
				$coupon->add_meta_data( '_reviewbird_template', $template_code );
			} elseif ( empty( $discount_type ) || empty( $amount ) ) {
				return new WP_Error( 'missing_parameters', __( 'discount_type and amount are required when not using template', 'reviewbird' ), array( 'status' => 400 ) );
			} else {
				$coupon->set_discount_type( $discount_type );
				$coupon->set_amount( $amount );
			}

			$coupon->set_code( $code );
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );
			$coupon->set_email_restrictions( $reviewer_email ? array( $reviewer_email ) : array() );
			if ( ! empty( $expiry_date ) ) {
				$coupon->set_date_expires( $expiry_date );
			}
			$coupon->add_meta_data( '_reviewbird_generated', true );
			$coupon->save();
		} catch ( \WC_Data_Exception $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => 400 ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'coupon_creation_failed', $e->getMessage(), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'coupon_id' => $coupon->get_id(),
				'code'      => $code,
			),
			200
		);
	}
}
