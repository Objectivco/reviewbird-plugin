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
		'usage_limit',
		'usage_limit_per_user',
		'limit_usage_to_x_items',
		'free_shipping',
		'product_categories',
		'excluded_product_categories',
		'exclude_sale_items',
		'minimum_amount',
		'maximum_amount',
		'email_restrictions',
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
			)
		);
	}

	/**
	 * Permission callback using WooCommerce authentication (consumer key/secret).
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return bool Whether the request has permission.
	 */
	public function permission_callback( WP_REST_Request $request ): bool {
		return wc_rest_check_post_permissions( 'shop_coupon', 'create', 0 );
	}

	/**
	 * Create a WooCommerce coupon.
	 *
	 * @param WP_REST_Request $request The REST API request.
	 * @return WP_REST_Response|WP_Error Response or error.
	 */
	public function create_coupon( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $this->error( 'woocommerce_not_active', 'WooCommerce is not active', 400 );
		}

		$code          = $request->get_param( 'code' );
		$expiry_date   = $request->get_param( 'expiry_date' );
		$template_code = $request->get_param( 'template_code' );
		$discount_type = $request->get_param( 'discount_type' );
		$amount        = $request->get_param( 'amount' );

		if ( empty( $code ) ) {
			return $this->error( 'missing_code', 'Coupon code is required', 400 );
		}

		try {
			$coupon_id = ! empty( $template_code )
				? $this->clone_template_coupon( $template_code, $code, $expiry_date )
				: $this->create_new_coupon( $code, $discount_type, $amount, $expiry_date );

			if ( is_wp_error( $coupon_id ) ) {
				return $coupon_id;
			}

			return new WP_REST_Response(
				array(
					'coupon_id' => $coupon_id,
					'code'      => $code,
				),
				200
			);
		} catch ( \Exception $e ) {
			return $this->error( 'coupon_creation_failed', $e->getMessage(), 500 );
		}
	}

	/**
	 * Clone a template coupon with new code and expiry.
	 *
	 * @param string      $template_code Template coupon code to clone.
	 * @param string      $new_code      New coupon code.
	 * @param string|null $expiry_date   Expiry date for the new coupon.
	 * @return int|WP_Error Coupon ID or error.
	 */
	private function clone_template_coupon( $template_code, $new_code, $expiry_date ) {
		$template_coupon = $this->find_coupon_by_code( $template_code );

		if ( ! $template_coupon ) {
			return $this->error( 'template_not_found', "Template coupon '{$template_code}' not found", 404 );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( strtoupper( $new_code ) );

		$this->copy_coupon_properties( $template_coupon, $coupon );

		if ( ! empty( $expiry_date ) ) {
			$coupon->set_date_expires( $expiry_date );
		}

		$this->add_reviewbird_metadata( $coupon, $template_code );
		$coupon->save();

		return $this->force_uppercase_code( $coupon->get_id(), $new_code );
	}

	/**
	 * Create a new coupon with specified parameters.
	 *
	 * @param string      $code          Coupon code.
	 * @param string      $discount_type Discount type ('percent' or 'fixed_cart').
	 * @param float       $amount        Discount amount.
	 * @param string|null $expiry_date   Expiry date for the coupon.
	 * @return int|WP_Error Coupon ID or error.
	 */
	private function create_new_coupon( $code, $discount_type, $amount, $expiry_date ) {
		if ( empty( $discount_type ) || empty( $amount ) ) {
			return $this->error( 'missing_parameters', 'discount_type and amount are required when not using template', 400 );
		}

		$coupon = new \WC_Coupon();
		$coupon->set_code( strtoupper( $code ) );
		$coupon->set_discount_type( $discount_type );
		$coupon->set_amount( floatval( $amount ) );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_limit_per_user( 1 );

		if ( ! empty( $expiry_date ) ) {
			$coupon->set_date_expires( $expiry_date );
		}

		$this->add_reviewbird_metadata( $coupon );
		$coupon->save();

		return $this->force_uppercase_code( $coupon->get_id(), $code );
	}

	/**
	 * Force coupon code to uppercase by directly updating post_title.
	 *
	 * WooCommerce's set_code() uses sanitize_title() which converts to lowercase.
	 * This bypasses that sanitization to preserve uppercase codes.
	 *
	 * @param int    $coupon_id Coupon post ID.
	 * @param string $code      Coupon code.
	 * @return int Coupon ID.
	 */
	private function force_uppercase_code( $coupon_id, $code ) {
		wp_update_post(
			array(
				'ID'         => $coupon_id,
				'post_title' => strtoupper( $code ),
			)
		);

		return $coupon_id;
	}

	/**
	 * Find a coupon by its code.
	 *
	 * @param string $code Coupon code to find.
	 * @return \WC_Coupon|null Coupon object or null if not found.
	 */
	private function find_coupon_by_code( $code ) {
		$coupons = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'title'          => $code,
			)
		);

		if ( empty( $coupons ) ) {
			return null;
		}

		return new \WC_Coupon( $coupons[0]->ID );
	}

	/**
	 * Copy coupon properties from source to destination.
	 *
	 * @param \WC_Coupon $source      Source coupon to copy from.
	 * @param \WC_Coupon $destination Destination coupon to copy to.
	 */
	private function copy_coupon_properties( $source, $destination ) {
		foreach ( self::CLONEABLE_PROPERTIES as $property ) {
			$getter = "get_{$property}";
			$setter = "set_{$property}";
			$destination->$setter( $source->$getter() );
		}
	}

	/**
	 * Add reviewbird metadata to a coupon.
	 *
	 * @param \WC_Coupon  $coupon        Coupon object.
	 * @param string|null $template_code Template code if cloned from template.
	 */
	private function add_reviewbird_metadata( $coupon, $template_code = null ) {
		$coupon->add_meta_data( '_reviewbird_generated', true );

		if ( $template_code ) {
			$coupon->add_meta_data( '_reviewbird_template', $template_code );
		}
	}

	/**
	 * Create a WP_Error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return WP_Error Error object.
	 */
	private function error( $code, $message, $status ) {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
