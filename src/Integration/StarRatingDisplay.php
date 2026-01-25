<?php
/**
 * Star rating display override for WooCommerce.
 *
 * Replaces WooCommerce's native star rating display with ReviewBird's
 * custom star icons using CSS mask-image approach.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

/**
 * Star rating display class.
 */
class StarRatingDisplay {

	/**
	 * Default star color fallback.
	 *
	 * @var string
	 */
	private const DEFAULT_STAR_COLOR = '#ffa500';

	/**
	 * Transient key for cached star color.
	 *
	 * @var string
	 */
	private const STAR_COLOR_TRANSIENT = 'reviewbird_star_color';

	/**
	 * Initialize star rating display overrides.
	 */
	public function __construct() {
		if ( ! reviewbird_get_store_id() ) {
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Register WooCommerce rating display hooks.
	 *
	 * Filters the rating HTML output to work with both classic and block themes.
	 */
	private function register_hooks(): void {
		// Filter rating HTML output - works for both classic and block themes.
		add_filter( 'woocommerce_product_get_rating_html', array( $this, 'filter_rating_html' ), 999, 3 );

		// Display ratings in shop loop - ensures ratings appear even if theme doesn't call wc_get_rating_html().
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_loop_rating' ), 5 );

		// Replace single product rating template - only needed for classic themes.
		// Block themes use the woocommerce/product-rating block which calls wc_get_rating_html()
		// directly, so the filter above handles those.
		if ( ! wp_is_block_theme() ) {
			add_action( 'init', array( $this, 'replace_single_product_rating' ) );
		}
	}

	/**
	 * Replace WooCommerce's single product rating template.
	 *
	 * The default template outputs a "customer reviews" link outside of the
	 * filtered rating HTML. We remove it and add our own implementation.
	 */
	public function replace_single_product_rating(): void {
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_single_rating' ), 6 );
	}

	/**
	 * Display rating on single product page without the review link.
	 */
	public function display_single_rating(): void {
		global $product;

		if ( ! $product || ! wc_review_ratings_enabled() ) {
			return;
		}

		// Don't show rating if reviews are disabled for this product.
		if ( ! comments_open( $product->get_id() ) ) {
			return;
		}

		$rating_count = $product->get_rating_count();
		if ( $rating_count < 1 ) {
			return;
		}

		echo '<div class="woocommerce-product-rating">';
		echo wc_get_rating_html( $product->get_average_rating(), $rating_count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
	}

	/**
	 * Display rating in shop/archive loop.
	 *
	 * Ensures ratings appear on shop pages even if the theme doesn't
	 * include rating display in its loop template.
	 */
	public function display_loop_rating(): void {
		global $product;

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// Check if reviews are enabled globally.
		if ( ! wc_review_ratings_enabled() ) {
			return;
		}

		// Don't show rating if reviews are disabled for this product.
		if ( ! comments_open( $product->get_id() ) ) {
			return;
		}

		$rating = $product->get_average_rating();
		$count  = $product->get_rating_count();

		// Only show if there are reviews.
		if ( $count < 1 ) {
			return;
		}

		// Make rating non-interactive for shop loop.
		add_filter( 'rb_rating_is_static', '__return_true' );

		// Use wc_get_rating_html which triggers our filter_rating_html.
		echo wc_get_rating_html( $rating, $count ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		remove_filter( 'rb_rating_is_static', '__return_true' );
	}

	/**
	 * Filter the WooCommerce rating HTML output.
	 *
	 * Replaces the native rating HTML with ReviewBird's custom star display.
	 * Works for both classic themes (via template functions) and block themes
	 * (via the woocommerce/product-rating block).
	 *
	 * @param string $html   The HTML string for the rating.
	 * @param float  $rating The average rating.
	 * @param int    $count  The number of ratings.
	 * @return string The filtered HTML string.
	 */
	public function filter_rating_html( string $html, float $rating, int $count ): string {
		// If count not provided, try to get it from the global product.
		if ( $count < 1 ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				$count = $product->get_rating_count();
			}
		}

		if ( $rating <= 0 && $count < 1 ) {
			return '';
		}

		$star_color = $this->get_star_color();
		$stars_html = $this->generate_stars_html( $rating, $star_color );

		// Check if rating should be non-interactive (e.g., shop loop context).
		$is_static = apply_filters( 'rb_rating_is_static', false );

		// Build class and attributes based on context.
		$class             = 'rb-wc-rating' . ( $is_static ? ' rb-wc-rating--static' : '' );
		$interactive_attrs = $is_static ? '' : ' role="button" tabindex="0"';

		if ( $is_static ) {
			// translators: %s is the average rating.
			$aria_label = sprintf( __( 'Rated %s out of 5', 'reviewbird-reviews' ), number_format( $rating, 2 ) );
		} else {
			// translators: %s is the average rating.
			$aria_label = sprintf( __( 'Rated %s out of 5, click to view reviews', 'reviewbird-reviews' ), number_format( $rating, 2 ) );
		}

		$output  = '<div class="' . esc_attr( $class ) . '"' . $interactive_attrs . ' aria-label="' . esc_attr( $aria_label ) . '">';
		$output .= $stars_html;

		if ( $count > 0 ) {
			$output .= sprintf( '<span class="rb-wc-rating-count">(%s)</span>', esc_html( $count ) );
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Generate HTML for star icons.
	 *
	 * @param float  $rating     The average rating (0-5).
	 * @param string $star_color The star color hex code.
	 * @return string HTML for star icons.
	 */
	private function generate_stars_html( float $rating, string $star_color ): string {
		$full_stars  = (int) floor( $rating );
		$remainder   = $rating - $full_stars;
		$has_half    = $remainder >= 0.25 && $remainder < 0.75;
		$round_up    = $remainder >= 0.75;
		$empty_color = '#ddd';

		if ( $round_up ) {
			++$full_stars;
		}

		$half_stars  = $has_half ? 1 : 0;
		$empty_stars = 5 - $full_stars - $half_stars;

		$html = '';

		// Full stars.
		for ( $i = 0; $i < $full_stars; $i++ ) {
			$html .= sprintf(
				'<span class="rb-wc-star rb-wc-star-fill" style="color: %s;"></span>',
				esc_attr( $star_color )
			);
		}

		// Half star.
		if ( $has_half ) {
			$html .= sprintf(
				'<span class="rb-wc-star rb-wc-star-half" style="color: %s;"></span>',
				esc_attr( $star_color )
			);
		}

		// Empty stars.
		for ( $i = 0; $i < $empty_stars; $i++ ) {
			$html .= sprintf(
				'<span class="rb-wc-star rb-wc-star-empty" style="color: %s;"></span>',
				esc_attr( $empty_color )
			);
		}

		return $html;
	}

	/**
	 * Get the star color from transient or fallback.
	 *
	 * @return string The star color hex code.
	 */
	private function get_star_color(): string {
		$star_color = get_transient( self::STAR_COLOR_TRANSIENT );

		if ( false !== $star_color && ! empty( $star_color ) ) {
			return $star_color;
		}

		return self::DEFAULT_STAR_COLOR;
	}

	/**
	 * Fetch and cache star color from widget config API.
	 *
	 * Called from admin settings page load.
	 *
	 * @return string|null The star color or null on failure.
	 */
	public static function fetch_and_cache_star_color(): ?string {
		$store_id = reviewbird_get_store_id();

		if ( ! $store_id ) {
			return null;
		}

		// Use a placeholder product key - the star color is store-wide.
		$endpoint = sprintf( '/api/widget-config/%d/placeholder', $store_id );
		$response = reviewbird_api_request( $endpoint, null, 'GET' );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$star_color = $response['widgetSettings']['star_color'] ?? null;

		if ( $star_color ) {
			// Cache for 24 hours.
			set_transient( self::STAR_COLOR_TRANSIENT, $star_color, DAY_IN_SECONDS );
			return $star_color;
		}

		return null;
	}
}
