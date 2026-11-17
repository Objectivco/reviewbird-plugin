<?php
/**
 * Hook callbacks for the storefront, REST API, and plugin screens.
 *
 * Callbacks are named functions so site owners can move or remove them.
 *
 * @package reviewbird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load star styles on every page that can contain a product rating.
 */
function reviewbird_enqueue_star_styles(): void {
	if ( reviewbird_can_show_widget() ) {
		wp_enqueue_style( 'reviewbird-stars', REVIEWBIRD_PLUGIN_URL . 'assets/css/reviewbird-stars.css', array(), REVIEWBIRD_VERSION );
	}
}

/**
 * Enqueue the widget script on pages that show or embed a product.
 */
function reviewbird_enqueue_public_scripts(): void {
	if ( reviewbird_page_should_enqueue_widget() ) {
		reviewbird_enqueue_widget_script();
	}
}

/**
 * Handle the [reviewbird_widget] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function reviewbird_widget_shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'product_id' => null ), $atts, 'reviewbird_widget' );

	return wp_kses_post( reviewbird_product_widget( $atts['product_id'] ) );
}

/**
 * Handle the [reviewbird_stars] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function reviewbird_stars_shortcode( $atts ): string {
	$atts    = shortcode_atts( array( 'product_id' => null ), $atts, 'reviewbird_stars' );
	$product = $atts['product_id'] ? wc_get_product( $atts['product_id'] ) : ( $GLOBALS['product'] ?? null );
	if ( ! $product instanceof \WC_Product ) {
		return '';
	}

	$rating = $product->get_average_rating();
	$count  = $product->get_rating_count();
	if ( $rating <= 0 && $count < 1 ) {
		return '';
	}

	reviewbird_enqueue_star_styles();
	return wp_kses( wc_get_rating_html( $rating, $count ), \reviewbird\Integration\StarRatingDisplay::allowed_rating_tags() );
}

/**
 * Handle the [reviewbird_showcase] shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function reviewbird_carousel_shortcode( $atts ): string {
	$atts     = shortcode_atts( array( 'id' => '' ), $atts, 'reviewbird_showcase' );
	$store_id = reviewbird_get_store_id();
	if ( empty( $atts['id'] ) ) {
		return '<!-- Reviewbird Showcase: Missing showcase ID -->';
	}
	if ( ! $store_id ) {
		return '<!-- Reviewbird Showcase: Store not connected -->';
	}

	if ( reviewbird_enqueue_remote_script( 'reviewbird-carousel', 'review-carousel.js' ) ) {
		wp_localize_script( 'reviewbird-carousel', 'reviewbirdCarouselConfig', array( 'apiUrl' => reviewbird_get_api_url() ) );
	}

	return sprintf(
		'<div data-reviewbird-carousel data-store-id="%s" data-carousel-id="%s" data-locale="%s"></div>',
		esc_attr( $store_id ),
		esc_attr( $atts['id'] ),
		esc_attr( implode( '-', array_slice( explode( '_', get_locale() ), 0, 2 ) ) )
	);
}

/**
 * Handle the [reviewbird_badge] store rating shortcode.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function reviewbird_badge_shortcode( $atts ): string {
	$store_id = reviewbird_get_store_id();
	if ( ! $store_id ) {
		return '<!-- Reviewbird Badge: Store not connected -->';
	}

	$choices = array(
		'layout'    => array( 'horizontal', 'seal' ),
		'theme'     => array( 'light', 'dark' ),
		'alignment' => array( 'left', 'center', 'right' ),
	);
	$atts    = shortcode_atts(
		array(
			'layout'    => 'horizontal',
			'theme'     => 'light',
			'alignment' => 'left',
		),
		$atts,
		'reviewbird_badge'
	);
	foreach ( $choices as $name => $allowed ) {
		if ( ! in_array( $atts[ $name ], $allowed, true ) ) {
			$atts[ $name ] = $allowed[0];
		}
	}

	reviewbird_enqueue_remote_script( 'reviewbird-badge', 'review-badge.js' );

	return sprintf(
		'<div data-reviewbird-store-badge data-store-id="%s" data-layout="%s" data-theme="%s" data-alignment="%s"></div>',
		esc_attr( $store_id ),
		esc_attr( $atts['layout'] ),
		esc_attr( $atts['theme'] ),
		esc_attr( $atts['alignment'] )
	);
}

/**
 * Do not copy cached Reviewbird data to a duplicated product.
 *
 * @param array $excluded Meta keys WooCommerce will not copy.
 * @return array
 */
function reviewbird_exclude_duplicate_meta( array $excluded ): array {
	return array_merge( $excluded, array( '_reviewbird_product_id', '_reviewbird_avg_stars', '_reviewbird_reviews_count', '_reviewbird_rating_counts', '_reviewbird_schema_reviews' ) );
}

/**
 * Remove the reviews tab when the Reviewbird widget replaces it.
 *
 * @param array $tabs Product tabs.
 * @return array
 */
function reviewbird_remove_reviews_tab( array $tabs ): array {
	if ( reviewbird_can_show_widget() ) {
		unset( $tabs['reviews'] );
	}

	return $tabs;
}

/**
 * Register the auto-rendered widget on single product pages.
 *
 * Runs on `init` so a theme's functions.php can change the priority.
 */
function reviewbird_register_product_widget_hook(): void {
	/**
	 * Filters the priority of the widget on `woocommerce_after_single_product_summary`
	 * (product tabs run at 10, related products at 20).
	 *
	 * @param int $priority Hook priority. Default 14.
	 */
	$priority = (int) apply_filters( 'reviewbird_product_widget_priority', 14 );

	add_action( 'woocommerce_after_single_product_summary', 'reviewbird_render_product_widget', $priority );
	// Block templates can omit Product Details, which supplies the summary hook.
	add_action( 'woocommerce_after_single_product', 'reviewbird_render_product_widget' );
}

/**
 * Print the widget for the current product.
 */
function reviewbird_render_product_widget(): void {
	echo wp_kses_post( reviewbird_product_widget() );
}

/**
 * Add the widget to products embedded with the Single Product block.
 *
 * @param string    $content  Rendered block content.
 * @param array     $block    Parsed block.
 * @param \WP_Block $instance Block instance.
 * @return string
 */
function reviewbird_render_block_product_widget( $content, $block, $instance ): string {
	$product_id = $block['attrs']['productId'] ?? $instance->context['postId'] ?? 0;
	if ( '' === $content || ! $product_id || post_password_required( $product_id ) ) {
		return $content;
	}

	return $content . wp_kses_post( reviewbird_product_widget( $product_id ) );
}

/**
 * Render each product's widget once across hooks, blocks, and shortcodes.
 *
 * @param int|null $product_id Product ID, or the current product.
 * @return string
 */
function reviewbird_product_widget( $product_id = null ): string {
	static $rendered = array();

	$product = $product_id ? wc_get_product( $product_id ) : ( $GLOBALS['product'] ?? null );
	if ( ! $product instanceof \WC_Product || ! reviewbird_can_show_widget() || isset( $rendered[ $product->get_id() ] ) ) {
		return '';
	}

	$output = reviewbird_render_widget( $product->get_id() );
	if ( $output ) {
		$rendered[ $product->get_id() ] = true;
		// Keep native review links valid after removing the main product's reviews tab.
		if ( is_product() && get_queried_object_id() === $product->get_id() ) {
			$output = '<div id="reviews">' . $output . '</div>';
		}
	}

	return $output;
}

/**
 * Open product comments when "Enable reviews for all products" is on.
 *
 * @param bool $open    Whether comments are open.
 * @param int  $post_id Post ID.
 * @return bool
 */
function reviewbird_force_comments_open( $open, $post_id ): bool {
	return $open || ( reviewbird_is_force_reviews_open() && 'product' === get_post_type( $post_id ) );
}

/**
 * Allow product reviews when "Enable reviews for all products" is on.
 *
 * @param bool $allowed Whether reviews are allowed.
 * @return bool
 */
function reviewbird_force_reviews_allowed( $allowed ): bool {
	return reviewbird_is_force_reviews_open() || $allowed;
}

/**
 * Get a cached Reviewbird rating value, queueing a sync when none exists.
 *
 * Reviewbird groups variation reviews under the parent product.
 *
 * @param \WC_Product $product  Product.
 * @param string      $meta_key Meta key.
 * @return mixed Meta value, or '' when not set.
 */
function reviewbird_rating_meta( $product, string $meta_key ) {
	if ( ! reviewbird_can_show_widget() ) {
		return '';
	}
	$product_id = reviewbird_product_meta_id( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id() );
	$value      = get_post_meta( $product_id, $meta_key, true );

	// Sync fills only a missing total, so an older product without buckets needs no job.
	if ( '' !== $value || '' !== get_post_meta( $product_id, '_reviewbird_reviews_count', true ) ) {
		return $value;
	}

	// Each rating getter lands here, so check the queue once per product per request.
	static $checked = array();
	if ( ! isset( $checked[ $product_id ] ) ) {
		$checked[ $product_id ] = true;
		if ( ! get_transient( 'reviewbird_rating_sync_' . $product_id ) && reviewbird_scheduler_ready() ) {
			reviewbird_enqueue_product_action( 'reviewbird_sync_product_rating', $product_id );
		}
	}

	return $value;
}

/**
 * Use the Reviewbird average rating.
 *
 * @param float       $native_rating WooCommerce rating.
 * @param \WC_Product $product       Product.
 * @return float
 */
function reviewbird_override_average_rating( $native_rating, $product ): float {
	$value = reviewbird_rating_meta( $product, '_reviewbird_avg_stars' );

	return (float) ( '' === $value ? $native_rating : $value );
}

/**
 * Use the Reviewbird review count.
 *
 * @param int         $native_count WooCommerce count.
 * @param \WC_Product $product      Product.
 * @return int
 */
function reviewbird_override_review_count( $native_count, $product ): int {
	$value = reviewbird_rating_meta( $product, '_reviewbird_reviews_count' );

	return (int) ( '' === $value ? $native_count : $value );
}

/**
 * Use the Reviewbird rating distribution, keyed 1 to 5.
 *
 * @param array       $native_counts WooCommerce counts.
 * @param \WC_Product $product       Product.
 * @return array
 */
function reviewbird_override_rating_counts( $native_counts, $product ): array {
	$counts = reviewbird_rating_meta( $product, '_reviewbird_rating_counts' );
	if ( ! is_array( $counts ) ) {
		return $native_counts;
	}

	$normalized = array();
	foreach ( range( 1, 5 ) as $stars ) {
		$normalized[ $stars ] = (int) ( $counts[ $stars ] ?? 0 );
	}
	return $normalized;
}

/**
 * Register Reviewbird REST routes.
 */
function reviewbird_register_rest_routes(): void {
	$ratings = new \reviewbird\Api\RatingsController();
	register_rest_route(
		'reviewbird/v1',
		'/ratings/update',
		array(
			'methods'             => 'POST',
			'callback'            => array( $ratings, 'update_ratings' ),
			'permission_callback' => array( \reviewbird\Api\RatingsController::class, 'permission_callback' ),
		)
	);
	register_rest_route(
		'reviewbird/v1',
		'/verified-purchase/check',
		array(
			'methods'             => 'POST',
			'callback'            => array( $ratings, 'check_verified_purchase' ),
			'permission_callback' => array( \reviewbird\Api\RatingsController::class, 'verified_purchase_permission_callback' ),
		)
	);
	register_rest_route(
		'reviewbird/v1',
		'/store-connected',
		array(
			'methods'             => 'POST',
			'callback'            => 'reviewbird_store_connected',
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
		)
	);
	( new \reviewbird\Api\CouponController() )->register_routes();
	( new \reviewbird\Api\ProductsController() )->register_routes();
}

/**
 * Save the store ID that Reviewbird sends after a store connects.
 *
 * @param \WP_REST_Request $request REST request.
 * @return \WP_REST_Response
 */
function reviewbird_store_connected( $request ): \WP_REST_Response {
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
 * Add Settings and Documentation links on the Plugins screen.
 *
 * @param array $links Plugin action links.
 * @return array
 */
function reviewbird_plugin_action_links( array $links ): array {
	array_unshift(
		$links,
		sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=reviewbird-settings' ), __( 'Settings', 'reviewbird' ) ),
		sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s <span class="dashicons dashicons-external" aria-hidden="true" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom;"></span></a>',
			esc_url( 'https://reviewbird.com/documentation/' ),
			__( 'Documentation', 'reviewbird' )
		)
	);

	return $links;
}

/**
 * Ask for the Get Started redirect on the next admin page load.
 */
function reviewbird_activate(): void {
	update_option( 'reviewbird_do_activation_redirect', 'yes' );
}

/**
 * Send the admin to the Get Started screen right after a single activation.
 */
function reviewbird_activation_redirect(): void {
	if ( 'yes' !== get_option( 'reviewbird_do_activation_redirect' ) ) {
		return;
	}
	delete_option( 'reviewbird_do_activation_redirect' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking a core activation query flag before redirecting.
	if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'admin.php?page=reviewbird-get-started' ) );
	exit;
}

/**
 * Keep remote Reviewbird assets out of WP Rocket's minification and combination.
 *
 * @param array $excluded Excluded assets.
 * @return array
 */
function reviewbird_wp_rocket_exclusions( array $excluded ): array {
	$excluded[] = 'app.reviewbird.com';
	return $excluded;
}

/**
 * Register compatibility filters for WP Rocket and Polylang when they are active.
 */
function reviewbird_register_compatibility(): void {
	if ( defined( 'WP_ROCKET_VERSION' ) ) {
		// External JS needs this filter; rocket_exclude_js only matches file paths.
		add_filter( 'rocket_minify_excluded_external_js', 'reviewbird_wp_rocket_exclusions' );
		add_filter( 'rocket_exclude_css', 'reviewbird_wp_rocket_exclusions' );
	}

	if ( function_exists( 'pll_get_post_translations' ) ) {
		add_filter(
			'reviewbird_product_translation_ids',
			static function ( array $ids, int $product_id ): array {
				return array_merge( $ids, array_values( pll_get_post_translations( $product_id ) ) );
			},
			10,
			2
		);
		add_filter(
			'reviewbird_original_product_id',
			static function ( $original_id, int $product_id ): int {
				return (int) pll_get_post( $product_id, pll_default_language() );
			},
			10,
			2
		);
		add_filter(
			'reviewbird_product_locale',
			static function ( string $locale, int $product_id ): string {
				return $locale ? $locale : (string) pll_get_post_language( $product_id, 'locale' );
			},
			10,
			2
		);
	}
}
