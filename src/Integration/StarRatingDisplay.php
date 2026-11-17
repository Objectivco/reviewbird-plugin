<?php
/**
 * Star rating display override for WooCommerce.
 *
 * Replaces WooCommerce's native star rating display with reviewbird's
 * custom star icons using CSS mask-image approach.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * Product being rendered by a WooCommerce product rating that omits its count.
	 *
	 * @var \WC_Product|null
	 */
	private $rating_product = null;

	/**
	 * Initialize star rating display overrides.
	 */
	public function __construct() {
		if ( ! reviewbird_can_show_widget() ) {
			return;
		}

		// Filter rating HTML output - works for both classic and block themes.
		add_filter( 'woocommerce_product_get_rating_html', array( $this, 'filter_rating_html' ), 999, 3 );

		// WooCommerce's loop rating and product rating block call wc_get_rating_html() without a count.
		// Set their product just before they render and clear it right after, so other ratings
		// (such as individual reviews) never inherit a product count.
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'set_loop_rating_product' ), 4 );
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'clear_rating_product' ), 6 );
		add_filter( 'render_block_context', array( $this, 'set_rating_block_product' ), 10, 2 );
		add_filter( 'render_block_woocommerce/product-rating', array( $this, 'clear_rating_product' ) );

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
		$native_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating' );
		if ( false === $native_priority || ! reviewbird_can_show_widget() ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', $native_priority );

		/**
		 * Filters the priority at which the reviewbird star rating is output in
		 * the single product summary. The simplest way to move the rating: return
		 * a different priority (e.g. 25 to place it below the short description).
		 *
		 * @param int $priority Hook priority. Default 6 (just below the title).
		 */
		$priority = (int) apply_filters( 'reviewbird_single_rating_priority', 6 );

		// Registered via a named procedural callback (reviewbird_display_single_rating)
		// rather than an object method, so site owners can also relocate or remove
		// it with a plain remove_action()/add_action() call. Because this runs on
		// `init`, those calls must be nested in a later hook (e.g. `wp`) for timing,
		// and must pass the same priority. See reviewbird_display_single_rating().
		add_action( 'woocommerce_single_product_summary', 'reviewbird_display_single_rating', $priority );
	}

	/**
	 * Filter the WooCommerce rating HTML output.
	 *
	 * Replaces the native rating HTML with reviewbird's custom star display.
	 * Works for both classic themes (via template functions) and block themes
	 * (via the woocommerce/product-rating block).
	 *
	 * @param string $html   The HTML string for the rating.
	 * @param float  $rating The average rating.
	 * @param int    $count  The number of ratings.
	 * @return string The filtered HTML string.
	 */
	public function filter_rating_html( string $html, float $rating, int $count ): string {
		if ( ! reviewbird_can_show_widget() ) {
			return $html;
		}

		if ( $count < 1 && $this->rating_product ) {
			$count = (int) $this->rating_product->get_rating_count();
		}

		if ( $rating <= 0 && $count < 1 ) {
			return '';
		}

		$star_color = get_transient( self::STAR_COLOR_TRANSIENT );
		$star_color = $star_color ? $star_color : get_option( self::STAR_COLOR_TRANSIENT );
		$stars_html = $this->generate_stars_html( $rating, $star_color ? $star_color : self::DEFAULT_STAR_COLOR );

		// Check if rating should be non-interactive (e.g., shop loop context).
		$is_static = ! is_product() || doing_action( 'woocommerce_after_shop_loop_item_title' )
			|| ( $this->rating_product && $this->rating_product->get_id() !== get_queried_object_id() );
		$is_static = apply_filters( 'reviewbird_rating_is_static', $is_static );

		// Build class and attributes based on context.
		$class             = 'rb-wc-rating' . ( $is_static ? ' rb-wc-rating--static' : '' );
		$interactive_attrs = $is_static ? '' : ' role="button" tabindex="0"';

		if ( $is_static ) {
			// translators: %s is the average rating.
			$aria_label = sprintf( __( 'Rated %s out of 5', 'reviewbird' ), number_format_i18n( $rating, 2 ) );
		} else {
			// translators: %s is the average rating.
			$aria_label = sprintf( __( 'Rated %s out of 5, click to view reviews', 'reviewbird' ), number_format_i18n( $rating, 2 ) );
		}

		$output  = '<div class="' . esc_attr( $class ) . '"' . $interactive_attrs . ' aria-label="' . esc_attr( $aria_label ) . '">';
		$output .= $stars_html;

		if ( $count > 0 ) {
			$output .= sprintf( '<span class="rb-wc-rating-count">(%s)</span>', esc_html( number_format_i18n( $count ) ) );
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Use the loop product for woocommerce_template_loop_rating(), which runs at priority 5.
	 */
	public function set_loop_rating_product(): void {
		global $product;
		$this->rating_product = $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Use the block's postId product while a woocommerce/product-rating block renders.
	 *
	 * @param array $context      Block context.
	 * @param array $parsed_block Parsed block.
	 * @return array Unchanged block context.
	 */
	public function set_rating_block_product( $context, $parsed_block ) {
		if ( 'woocommerce/product-rating' === ( $parsed_block['blockName'] ?? '' ) ) {
			$product              = empty( $context['postId'] ) ? null : wc_get_product( $context['postId'] );
			$this->rating_product = $product instanceof \WC_Product ? $product : null;
		}
		return $context;
	}

	/**
	 * Stop using the product once its rating has rendered.
	 *
	 * @param string $block_content Block content when used as a filter.
	 * @return string Unchanged block content.
	 */
	public function clear_rating_product( $block_content = '' ) {
		$this->rating_product = null;
		return $block_content;
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
	 * Allowed HTML tags and attributes for rating output.
	 *
	 * Covers both our custom filter_rating_html() output and the
	 * native WooCommerce wc_get_rating_html() / wc_get_star_rating_html()
	 * output as a fallback.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_rating_tags(): array {
		return array(
			'div'    => array(
				'class'      => true,
				'role'       => true,
				'tabindex'   => true,
				'aria-label' => true,
			),
			'span'   => array(
				'class' => true,
				'style' => true,
			),
			'strong' => array(
				'class' => true,
			),
		);
	}

	/**
	 * Fetch and cache star color from widget config API.
	 *
	 * Called from the settings page and the background health check.
	 *
	 * @return string|null The star color or null on failure.
	 */
	public static function fetch_and_cache_star_color(): ?string {
		$store_id = reviewbird_get_store_id();

		if ( ! $store_id ) {
			return null;
		}

		$endpoint = sprintf( '/api/widget/%d/config', $store_id );
		$response = reviewbird_api_request( $endpoint, null, 'GET' );

		$star_color = is_wp_error( $response ) ? null : ( $response['widget_settings']['star_color'] ?? null );
		$star_color = is_string( $star_color ) ? sanitize_hex_color( $star_color ) : null;

		if ( $star_color ) {
			update_option( self::STAR_COLOR_TRANSIENT, $star_color, false );
			set_transient( self::STAR_COLOR_TRANSIENT, $star_color, DAY_IN_SECONDS );
			return $star_color;
		}

		// Keep the last valid color and wait before another request.
		set_transient( self::STAR_COLOR_TRANSIENT, get_option( self::STAR_COLOR_TRANSIENT, self::DEFAULT_STAR_COLOR ), HOUR_IN_SECONDS );
		return null;
	}
}
