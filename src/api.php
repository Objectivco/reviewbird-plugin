<?php
/**
 * REST endpoints and WooCommerce API data that the Reviewbird service reads.
 *
 * @package reviewbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register Reviewbird REST routes and the system status field.
 */
function reviewbird_register_rest_routes(): void {
	$routes = array(
		'/ratings/update'          => array(
			'methods'             => 'POST',
			'callback'            => 'reviewbird_rest_update_ratings',
			'permission_callback' => 'reviewbird_rest_can_update_ratings',
		),
		'/verified-purchase/check' => array(
			'methods'             => 'POST',
			'callback'            => 'reviewbird_rest_check_verified_purchase',
			// This endpoint reads customer purchase data.
			'permission_callback' => static function () {
				return wc_rest_check_post_permissions( 'shop_order', 'read', 0 );
			},
		),
		'/store-connected'         => array(
			'methods'             => 'POST',
			'callback'            => 'reviewbird_rest_store_connected',
			'permission_callback' => static function () {
				return current_user_can( 'manage_woocommerce' );
			},
			'args'                => array(
				'store_id' => array(
					'required' => true,
					'type'     => 'integer',
					'minimum'  => 1,
				),
			),
		),
		'/coupons/create'          => array(
			'methods'             => 'POST',
			'callback'            => 'reviewbird_rest_create_coupon',
			'permission_callback' => static function () {
				return wc_rest_check_post_permissions( 'shop_coupon', 'create', 0 );
			},
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
		),
		'/products'                => array(
			'methods'             => 'GET',
			'callback'            => 'reviewbird_rest_get_products',
			'permission_callback' => 'reviewbird_rest_can_read_products',
			'args'                => array(
				'per_page'       => array(
					'type'    => 'integer',
					'default' => 100,
					'minimum' => 1,
					'maximum' => 100,
				),
				'page'           => array(
					'type'    => 'integer',
					'default' => 1,
					'minimum' => 1,
				),
				'status'         => array(
					'default'           => 'publish',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'modified_after' => array(
					'default'           => null,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		),
		'/products/(?P<id>\d+)'    => array(
			'methods'             => 'GET',
			'callback'            => 'reviewbird_rest_get_product',
			'permission_callback' => 'reviewbird_rest_can_read_products',
			'args'                => array(
				'id' => array(
					'required'          => true,
					'sanitize_callback' => 'absint',
				),
			),
		),
	);
	foreach ( $routes as $route => $args ) {
		register_rest_route( 'reviewbird/v1', $route, $args );
	}

	register_rest_field(
		'system_status',
		'reviewbird_widget_enabled',
		array(
			'get_callback' => 'reviewbird_is_widget_enabled',
			'schema'       => array(
				'description' => __( 'Whether the Reviewbird widget is enabled.', 'reviewbird' ),
				'type'        => 'boolean',
				'context'     => array( 'view' ),
				'readonly'    => true,
			),
		)
	);
}

/**
 * Let WooCommerce API keys authenticate Reviewbird and ReviewX routes.
 *
 * @param bool $is_request_to_wc_api Whether this is a request to the WooCommerce API.
 * @return bool
 */
function reviewbird_enable_wc_auth( $is_request_to_wc_api ) {
	if ( $is_request_to_wc_api || ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return $is_request_to_wc_api;
	}

	// WooCommerce retries authentication after WordPress resolves the REST route.
	global $wp;
	$route = $wp->query_vars['rest_route'] ?? '';
	return is_string( $route ) && (bool) preg_match( '#^/(?:reviewbird|reviewx)/#i', $route );
}

/**
 * Save the store ID that Reviewbird sends after a store connects.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response
 */
function reviewbird_rest_store_connected( $request ): \WP_REST_Response {
	$store_id = (int) $request->get_param( 'store_id' );
	if ( (int) get_option( 'reviewbird_store_id' ) !== $store_id ) {
		delete_option( 'reviewbird_star_color' );
		delete_transient( 'reviewbird_star_color' );
	}
	update_option( 'reviewbird_store_id', $store_id );
	delete_option( 'reviewbird_store_status' );
	delete_transient( 'reviewbird_admin_health_status' );
	reviewbird_schedule_immediate_health_check();

	wc_get_logger()->info( sprintf( 'Reviewbird store connected: Store ID %d saved', $store_id ), array( 'source' => 'reviewbird' ) );

	return new \WP_REST_Response(
		array(
			'success'  => true,
			'store_id' => $store_id,
			// translators: %d: Store ID number.
			'message'  => sprintf( __( 'Store ID %d has been saved successfully', 'reviewbird' ), $store_id ),
		)
	);
}

/**
 * Validate a product ID from a request. Saved Reviewbird IDs of deleted products resolve too.
 *
 * @param mixed $product_id       Product ID.
 * @param bool  $allow_variations Whether variation IDs are valid.
 * @return int|\WP_Error
 */
function reviewbird_rest_product_id( $product_id, bool $allow_variations = false ) {
	if ( empty( $product_id ) ) {
		return new \WP_Error( 'missing_product_id', __( 'Product ID is required', 'reviewbird' ), array( 'status' => 400 ) );
	}
	if ( ! is_numeric( $product_id ) || false === filter_var( $product_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
		return new \WP_Error( 'invalid_product_id', __( 'Invalid product ID', 'reviewbird' ), array( 'status' => 400 ) );
	}

	$product_id = (int) $product_id;
	$types      = $allow_variations ? array( 'product', 'product_variation' ) : array( 'product' );
	if ( in_array( get_post_type( $product_id ), $types, true ) ) {
		return $product_id;
	}
	$product = reviewbird_find_product( $product_id );
	if ( $product && in_array( get_post_type( $product->get_id() ), $types, true ) ) {
		return $product_id;
	}

	return new \WP_Error( 'product_not_found', __( 'Product not found', 'reviewbird' ), array( 'status' => 404 ) );
}

/**
 * Allow rating updates from keys that can edit the product.
 *
 * @param \WP_REST_Request $request REST request.
 * @return bool
 */
function reviewbird_rest_can_update_ratings( $request ): bool {
	$product_id = reviewbird_rest_product_id( $request->get_param( 'product_id' ) );
	if ( is_wp_error( $product_id ) ) {
		return false;
	}
	if ( 'product' !== get_post_type( $product_id ) ) {
		$product_id = reviewbird_find_product( $product_id )->get_id();
	}

	return wc_rest_check_post_permissions( 'product', 'edit', $product_id );
}

/**
 * Save a product's rating totals from Reviewbird.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function reviewbird_rest_update_ratings( $request ) {
	$product_id    = reviewbird_rest_product_id( $request->get_param( 'product_id' ) );
	$avg_stars     = $request->get_param( 'avg_stars' );
	$review_count  = $request->get_param( 'review_count' );
	$rating_counts = $request->get_param( 'rating_counts' );
	if ( is_wp_error( $product_id ) ) {
		return $product_id;
	}

	if ( ! is_numeric( $review_count ) || false === filter_var( $review_count, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) ) ) {
		return new \WP_Error( 'invalid_count', __( 'Review count must be a whole number of 0 or more', 'reviewbird' ), array( 'status' => 400 ) );
	}
	$review_count = (int) $review_count;

	if ( ! is_numeric( $avg_stars ) || ! is_finite( (float) $avg_stars )
		|| ( 0 === $review_count ? 0.0 !== (float) $avg_stars : ( $avg_stars < 0 || $avg_stars > 5 ) ) ) {
		return new \WP_Error( 'invalid_rating', __( 'Average stars must be between 0 and 5, and 0 when there are no reviews', 'reviewbird' ), array( 'status' => 400 ) );
	}

	// The service owns the average. Rating counts are optional and only validated when sent.
	$avg_stars = (float) $avg_stars;
	if ( 0 === $review_count || ! empty( $rating_counts ) ) {
		$rating_counts = reviewbird_normalize_rating_counts( $rating_counts, $review_count );
		if ( null === $rating_counts ) {
			return new \WP_Error( 'invalid_rating_counts', __( 'Rating counts must contain whole numbers of 0 or more for stars 1 to 5 and must not exceed the review count.', 'reviewbird' ), array( 'status' => 400 ) );
		}
	}

	$shared_id  = reviewbird_product_id( $product_id );
	$product_id = reviewbird_product_meta_id( $product_id );
	update_post_meta( $product_id, '_reviewbird_avg_stars', $avg_stars );
	update_post_meta( $product_id, '_reviewbird_reviews_count', $review_count );
	if ( ! empty( $rating_counts ) ) {
		update_post_meta( $product_id, '_reviewbird_rating_counts', $rating_counts );
	}
	wc_delete_product_transients( $product_id );
	delete_transient( 'reviewbird_ssr_' . $product_id );
	do_action( 'reviewbird_rating_updated', $product_id, $avg_stars, $review_count );

	wc_get_logger()->info(
		sprintf( 'Rating updated for product %d: %.2f stars (%d reviews)', $product_id, $avg_stars, $review_count ),
		array( 'source' => 'reviewbird' )
	);

	return new \WP_REST_Response(
		array(
			'success'      => true,
			'product_id'   => $shared_id,
			'avg_stars'    => $avg_stars,
			'review_count' => $review_count,
		),
		200
	);
}

/**
 * Check whether a customer bought a product or one of its translations.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function reviewbird_rest_check_verified_purchase( $request ) {
	$product_id     = reviewbird_rest_product_id( $request->get_param( 'product_id' ), true );
	$customer_email = $request->get_param( 'customer_email' );
	if ( is_wp_error( $product_id ) ) {
		return $product_id;
	}
	if ( empty( $customer_email ) ) {
		return new \WP_Error( 'missing_email', __( 'Customer email is required', 'reviewbird' ), array( 'status' => 400 ) );
	}
	if ( ! is_string( $customer_email ) || ! is_email( $customer_email ) ) {
		return new \WP_Error( 'invalid_email', __( 'Invalid email address', 'reviewbird' ), array( 'status' => 400 ) );
	}

	$product_ids = reviewbird_product_translation_ids( $product_id );
	$shared_id   = reviewbird_product_id( $product_id );
	// Native orders can still reference the deleted product that owns this saved identity.
	if ( $product_ids && ! get_post_type( $shared_id ) && ! in_array( $shared_id, $product_ids, true ) ) {
		$product_ids[] = $shared_id;
	}

	$verified_purchase = false;
	foreach ( $product_ids as $purchase_product_id ) {
		if ( wc_customer_bought_product( $customer_email, null, $purchase_product_id ) ) {
			$verified_purchase = true;
			break;
		}
	}

	$purchase   = $verified_purchase ? reviewbird_purchase_details( $customer_email, $product_ids ) : array();
	$attributes = $purchase['purchased_attributes'] ?? array();
	$location   = $purchase['location'] ?? null;

	wc_get_logger()->debug(
		sprintf(
			'Verified purchase check for product %d, email %s: %s, location: %s, attributes: %s',
			$product_id,
			$customer_email,
			$verified_purchase ? 'true' : 'false',
			$location ? $location : 'none',
			$attributes ? wp_json_encode( $attributes ) : 'none'
		),
		array( 'source' => 'reviewbird' )
	);

	$response = array(
		'product_id'        => $product_id,
		'customer_email'    => $customer_email,
		'verified_purchase' => $verified_purchase,
	);
	if ( $location ) {
		$response['location'] = $location;
	}
	if ( $attributes ) {
		$response['purchased_attributes'] = $attributes;
	}

	return new \WP_REST_Response( $response, 200 );
}

/**
 * Get the country and chosen attributes from the most recent matching paid order.
 *
 * @param string $customer_email Customer billing email.
 * @param int[]  $product_ids    Product IDs, including translations.
 * @return array Purchase details.
 */
function reviewbird_purchase_details( string $customer_email, array $product_ids ): array {
	// ponytail: Read 100 orders per query; use a product lookup if long order histories become slow.
	$page = 1;
	do {
		$orders      = wc_get_orders(
			array(
				'billing_email' => $customer_email,
				'status'        => wc_get_is_paid_statuses(),
				'limit'         => 100,
				'page'          => $page++,
				'orderby'       => 'date',
				'order'         => 'DESC',
				'lang'          => '',
			)
		);
		$order_count = count( $orders );

		foreach ( $orders as $order ) {
			foreach ( $order->get_items() as $item ) {
				$variation_id = (int) $item->get_variation_id( 'edit' );
				if ( ! in_array( (int) $item->get_product_id( 'edit' ), $product_ids, true ) && ! in_array( $variation_id, $product_ids, true ) ) {
					continue;
				}

				$attributes = array();
				if ( $variation_id ) {
					$parent               = wc_get_product( $item->get_product_id( 'edit' ) );
					$variation_attributes = $parent ? array_filter( $parent->get_attributes( 'edit' ), 'wc_attributes_array_filter_variation' ) : array();
					$variation_keys       = array_map( 'rawurldecode', array_keys( $variation_attributes ) );
					foreach ( $item->get_formatted_meta_data( '_', true ) as $meta ) {
						if ( in_array( $meta->key, $variation_keys, true ) ) {
							$attributes[] = array(
								'name'  => wp_strip_all_tags( $meta->display_key ),
								'value' => wp_strip_all_tags( $meta->display_value ),
							);
						}
					}
				}

				$switched = switch_to_locale( 'en_US' );
				try {
					$countries = ( new \WC_Countries() )->get_countries();
				} finally {
					if ( $switched ) {
						restore_previous_locale();
					}
				}
				// WooCommerce labels are for its checkout dropdown: they contain HTML entities and
				// "United States (US)"-style codes. Send plain names, keeping "Virgin Islands (US)".
				$country = $countries[ $order->get_billing_country() ] ?? null;
				return array(
					'location'             => $country ? preg_replace( '/^(United (?:States|Kingdom)) \((?:US|UK)\)/', '$1', html_entity_decode( $country, ENT_QUOTES, 'UTF-8' ) ) : null,
					'purchased_attributes' => $attributes,
				);
			}
		}
	} while ( 100 === $order_count );

	return array();
}

/**
 * Create a single-use WooCommerce coupon, optionally copying a template coupon.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function reviewbird_rest_create_coupon( $request ) {
	$code           = wc_format_coupon_code( $request->get_param( 'code' ) ?? '' );
	$expiry_date    = $request->get_param( 'expiry_date' );
	$template_code  = $request->get_param( 'template_code' );
	$discount_type  = $request->get_param( 'discount_type' );
	$amount         = $request->get_param( 'amount' );
	$reviewer_email = $request->get_param( 'reviewer_email' );

	// The route schema validates types and the reviewer email format.
	if ( '' === trim( $code ) ) {
		return new \WP_Error( 'missing_code', __( 'Coupon code is required', 'reviewbird' ), array( 'status' => 400 ) );
	}
	if ( $expiry_date && ( false === rest_parse_date( 10 === strlen( $expiry_date ) ? $expiry_date . 'T00:00:00' : $expiry_date )
		|| ! checkdate( (int) substr( $expiry_date, 5, 2 ), (int) substr( $expiry_date, 8, 2 ), (int) substr( $expiry_date, 0, 4 ) ) ) ) {
		return new \WP_Error( 'invalid_expiry_date', __( 'Use a valid date or ISO 8601 date and time for coupon expiry.', 'reviewbird' ), array( 'status' => 400 ) );
	}
	if ( wc_get_coupon_id_by_code( $code ) ) {
		return new \WP_Error( 'duplicate_coupon', __( 'This coupon code already exists.', 'reviewbird' ), array( 'status' => 409 ) );
	}

	$coupon = new \WC_Coupon();
	try {
		if ( ! empty( $template_code ) ) {
			$template_id = wc_get_coupon_id_by_code( $template_code );
			if ( ! $template_id ) {
				// translators: %s: Template coupon code.
				return new \WP_Error( 'template_not_found', sprintf( __( "Template coupon '%s' not found", 'reviewbird' ), $template_code ), array( 'status' => 404 ) );
			}
			$template = new \WC_Coupon( $template_id );
			foreach ( array( 'discount_type', 'amount', 'individual_use', 'product_ids', 'excluded_product_ids', 'limit_usage_to_x_items', 'free_shipping', 'product_categories', 'excluded_product_categories', 'exclude_sale_items', 'minimum_amount', 'maximum_amount' ) as $property ) {
				$coupon->{"set_{$property}"}( $template->{"get_{$property}"}() );
			}
			$coupon->add_meta_data( '_reviewbird_template', $template_code );
		} elseif ( empty( $discount_type ) || empty( $amount ) ) {
			return new \WP_Error( 'missing_parameters', __( 'discount_type and amount are required when not using template', 'reviewbird' ), array( 'status' => 400 ) );
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
		return new \WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => 400 ) );
	} catch ( \Exception $e ) {
		return new \WP_Error( 'coupon_creation_failed', $e->getMessage(), array( 'status' => 500 ) );
	}

	return new \WP_REST_Response(
		array(
			'coupon_id' => $coupon->get_id(),
			'code'      => $code,
		),
		200
	);
}

/**
 * Allow product reads from keys that can read products.
 *
 * @return bool
 */
function reviewbird_rest_can_read_products(): bool {
	return wc_rest_check_post_permissions( 'product', 'read', 0 );
}

/**
 * List products with their variations, one row per shared Reviewbird identity.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response
 */
function reviewbird_rest_get_products( $request ): \WP_REST_Response {
	$args = array(
		'status'   => $request->get_param( 'status' ),
		'limit'    => $request->get_param( 'per_page' ),
		'page'     => $request->get_param( 'page' ),
		'paginate' => true,
		'orderby'  => 'ID',
		'order'    => 'ASC',
		'lang'     => '',
	);
	if ( $request->get_param( 'modified_after' ) ) {
		$args['date_modified'] = '>=' . $request->get_param( 'modified_after' );
	}

	$results  = wc_get_products( $args );
	$products = array();
	foreach ( $results->products as $product ) {
		$shared = reviewbird_export_product( $product->get_id() );
		if ( $shared ) {
			$products[ reviewbird_product_id( $shared->get_id() ) ] = reviewbird_format_product( $shared );
		}
	}

	// Page counts describe the complete native catalog traversal.
	return reviewbird_products_response(
		array_values( $products ),
		array(
			'X-WP-Total'      => (int) $results->total,
			'X-WP-TotalPages' => (int) $results->max_num_pages,
		)
	);
}

/**
 * Get one product with its variations. Variation IDs resolve to the parent.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response|\WP_Error
 */
function reviewbird_rest_get_product( $request ) {
	$requested_id = reviewbird_product_id( absint( $request->get_param( 'id' ) ) );
	$product      = reviewbird_export_product( $requested_id );
	if ( ! $product || ! $product->exists() ) {
		return new \WP_Error( 'product_not_found', __( 'Product not found', 'reviewbird' ), array( 'status' => 404 ) );
	}
	if ( $product->is_type( 'variation' ) ) {
		$parent = reviewbird_export_product( $product->get_parent_id() );
		if ( $parent && $parent->exists() ) {
			$product = $parent;
		}
	}

	return reviewbird_products_response( reviewbird_format_product( $product ), array( 'X-Reviewbird-Product-ID' => (string) $requested_id ) );
}

/**
 * Build a response with the headers that tell Reviewbird which features this plugin supports.
 *
 * @param array $data    Response data.
 * @param array $headers Extra headers.
 * @return \WP_REST_Response
 */
function reviewbird_products_response( array $data, array $headers ): \WP_REST_Response {
	return new \WP_REST_Response(
		$data,
		200,
		array(
			'X-Reviewbird-Version'            => REVIEWBIRD_VERSION,
			'X-Reviewbird-Shared-Products'    => '1',
			'X-Reviewbird-Product-Identities' => '1',
		) + $headers
	);
}

/**
 * Get the product for a shared identity, preferring a published translation.
 *
 * @param int $product_id Actual or shared product ID.
 * @return \WC_Product|false
 */
function reviewbird_export_product( int $product_id ) {
	$product = reviewbird_find_product( reviewbird_product_id( $product_id ) );
	if ( $product && 'publish' !== $product->get_status() ) {
		foreach ( reviewbird_product_translation_ids( $product->get_id() ) as $translation_id ) {
			$translation = wc_get_product( $translation_id );
			if ( $translation && 'publish' === $translation->get_status() ) {
				return $translation;
			}
		}
	}

	return $product;
}

/**
 * Format a product, or a variation when $variation is true, for Reviewbird.
 *
 * @param \WC_Product $product   Product or variation.
 * @param bool        $variation Whether to format a variation.
 * @return array
 */
function reviewbird_format_product( $product, bool $variation = false ): array {
	$id = $product->get_id();

	// Native IDs that redirect to the shared identity.
	$legacy_ids = array_values( array_diff( reviewbird_product_translation_ids( $id ), array( reviewbird_product_id( $id ) ) ) );
	$brands     = wp_get_post_terms( $id, 'product_brand', array( 'fields' => 'names' ) );
	$brand      = ! is_wp_error( $brands ) && $brands ? $brands[0] : $product->get_meta( '_brand' );
	$common     = array(
		'id'                  => reviewbird_product_id( $id ),
		'legacy_external_ids' => $legacy_ids,
		'sku'                 => $product->get_sku(),
		'global_unique_id'    => method_exists( $product, 'get_global_unique_id' ) ? $product->get_global_unique_id() : '',
		'brand'               => $brand ? $brand : null,
		'price'               => $product->get_price(),
		'image'               => wp_get_attachment_url( $product->get_image_id() ),
	);
	if ( $variation ) {
		return $common + array(
			'attributes' => $product->get_attributes(),
			'in_stock'   => $product->is_in_stock(),
		);
	}

	$variations = array();
	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $variation_id ) {
			$child = wc_get_product( $variation_id );
			if ( ! $child || ! $child->exists() ) {
				continue;
			}
			$shared_id = reviewbird_product_id( $child->get_id() );
			$child     = reviewbird_export_product( $shared_id );
			if ( $child ) {
				$variations[ $shared_id ] = reviewbird_format_product( $child, true );
			}
		}
	}

	$tags = get_the_terms( $id, 'product_tag' );
	return array(
		'id'                  => $common['id'],
		'legacy_external_ids' => $legacy_ids,
		'name'                => $product->get_name(),
		'slug'                => $product->get_slug(),
		'permalink'           => $product->get_permalink(),
		'type'                => $product->get_type(),
		'status'              => $product->get_status(),
		'sku'                 => $common['sku'],
		'global_unique_id'    => $common['global_unique_id'],
		'brand'               => $common['brand'],
		'price'               => $common['price'],
		'image'               => $common['image'],
		'images'              => array_values( array_filter( array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() ) ) ),
		'stock_status'        => $product->get_stock_status(),
		'in_stock'            => $product->is_in_stock(),
		'tags'                => is_array( $tags ) ? array_map(
			static function ( $term ) {
				return array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			},
			array_values( $tags )
		) : array(),
		'categories'          => reviewbird_product_categories( $id ),
		'variations'          => array_values( $variations ),
	);
}

/**
 * Get product categories with their ancestors, root first.
 *
 * @param int $product_id Product ID.
 * @return array
 */
function reviewbird_product_categories( int $product_id ): array {
	$categories = array();
	$terms      = get_the_terms( $product_id, 'product_cat' );
	if ( ! is_array( $terms ) ) {
		return $categories;
	}

	foreach ( $terms as $term ) {
		$ancestors = array();
		$names     = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( ! $ancestor || is_wp_error( $ancestor ) ) {
				continue;
			}
			$names[]     = $ancestor->name;
			$ancestors[] = array(
				'id'        => $ancestor->term_id,
				'name'      => $ancestor->name,
				'slug'      => $ancestor->slug,
				'full_name' => implode( ' > ', $names ),
			);
		}
		$categories[] = array(
			'id'        => $term->term_id,
			'name'      => $term->name,
			'slug'      => $term->slug,
			'full_name' => implode( ' > ', array_merge( $names, array( $term->name ) ) ),
			'ancestors' => $ancestors,
		);
	}

	return $categories;
}

/**
 * Add CusRev titles and media, plus the shared product ID and locale for Reviewbird imports.
 *
 * CusRev stores photo and video attachment IDs in ivole_review_image2 and ivole_review_video2.
 *
 * @param \WP_REST_Response     $response Review response.
 * @param \WP_Comment           $review   Review comment.
 * @param \WP_REST_Request|null $request  REST request, when available.
 * @return \WP_REST_Response
 */
function reviewbird_add_review_data_to_rest_response( $response, $review, $request = null ) {
	$media = array();
	foreach ( array(
		'ivole_review_image2' => 'image',
		'ivole_review_video2' => 'video',
	) as $meta_key => $type ) {
		foreach ( get_comment_meta( $review->comment_ID, $meta_key, false ) as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$media[] = array(
					'type' => $type,
					'url'  => $url,
				);
			}
		}
	}
	$response->data['media'] = $media;

	$title = get_comment_meta( $review->comment_ID, 'cr_rev_title', true );
	if ( is_string( $title ) && '' !== trim( $title ) && empty( $response->data['title'] ) ) {
		$response->data['title'] = $title;
	}
	if ( $request && '1' === $request->get_header( 'X-Reviewbird-Request' ) ) {
		$response->data['product_id'] = reviewbird_product_id( (int) $review->comment_post_ID );
		$locale                       = get_comment_meta( $review->comment_ID, '_reviewbird_locale', true );
		if ( ! $locale ) {
			/**
			 * Filter the product locale when the review has no saved locale.
			 *
			 * @param string $locale     Product locale.
			 * @param int    $product_id Actual product ID.
			 */
			$locale = apply_filters( 'reviewbird_product_locale', '', (int) $review->comment_post_ID );
		}
		if ( is_string( $locale ) && '' !== $locale ) {
			$response->data['locale'] = $locale;
		}
	}

	return $response;
}

/**
 * Add the order locale, and shared product IDs for Reviewbird imports.
 *
 * Orders created before locale tracking use the site locale.
 *
 * @param \WP_REST_Response     $response Order response.
 * @param \WC_Order             $order    Order.
 * @param \WP_REST_Request|null $request  REST request.
 * @return \WP_REST_Response
 */
function reviewbird_add_locale_to_order_response( $response, $order, $request = null ) {
	$saved_locale             = $order->get_meta( '_reviewbird_locale' );
	$response->data['locale'] = $saved_locale ? $saved_locale : get_locale();
	if ( ! $request || '1' !== $request->get_header( 'X-Reviewbird-Request' ) ) {
		return $response;
	}

	foreach ( $response->data['line_items'] ?? array() as $key => $item ) {
		$source = $order->get_item( (int) $item['id'] );
		if ( ! $source instanceof \WC_Order_Item_Product ) {
			continue;
		}
		$product_id   = $source->get_product_id( 'edit' );
		$variation_id = $source->get_variation_id( 'edit' );
		$product      = wc_get_product( $variation_id ? $variation_id : $product_id );

		$response->data['line_items'][ $key ]['name'] = $source->get_name( 'edit' );
		if ( $product ) {
			$image = wp_get_attachment_url( $product->get_image_id() );
			$response->data['line_items'][ $key ]['product_url']   = $product->get_permalink();
			$response->data['line_items'][ $key ]['product_image'] = $image ? $image : null;
		}
		$response->data['line_items'][ $key ]['product_id']   = reviewbird_product_id( (int) $product_id );
		$response->data['line_items'][ $key ]['variation_id'] = $variation_id ? reviewbird_product_id( (int) $variation_id ) : 0;
	}

	return $response;
}

/**
 * Include every language in Reviewbird order and review imports.
 *
 * @param array            $args    Query arguments.
 * @param \WP_REST_Request $request REST request.
 * @return array
 */
function reviewbird_query_all_languages( array $args, $request ): array {
	if ( '1' === $request->get_header( 'X-Reviewbird-Request' ) ) {
		$args['lang'] = '';
	}

	return $args;
}
