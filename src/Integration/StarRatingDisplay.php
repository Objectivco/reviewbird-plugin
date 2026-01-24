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
	 * Removes native WooCommerce rating display and adds ReviewBird's version.
	 */
	private function register_hooks(): void {
		// Remove WooCommerce's native rating display.
		add_action( 'init', array( $this, 'remove_woocommerce_rating_hooks' ) );

		// Add ReviewBird rating display at same hooks/priorities.
		add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_loop_rating' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'display_single_rating' ), 10 );
	}

	/**
	 * Remove WooCommerce's native rating display hooks.
	 *
	 * Called on 'init' to ensure WooCommerce functions exist.
	 */
	public function remove_woocommerce_rating_hooks(): void {
		remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
	}

	/**
	 * Display rating in product loop (shop/archive pages).
	 */
	public function display_loop_rating(): void {
		global $product;

		if ( ! $product ) {
			return;
		}

		$this->render_rating( $product );
	}

	/**
	 * Display rating on single product page.
	 */
	public function display_single_rating(): void {
		global $product;

		if ( ! $product ) {
			return;
		}

		$rating_count = $product->get_rating_count();

		if ( $rating_count < 1 ) {
			return;
		}

		$this->render_rating( $product );
	}

	/**
	 * Render the star rating HTML.
	 *
	 * @param \WC_Product $product The product object.
	 */
	private function render_rating( $product ): void {
		$average_rating = (float) $product->get_average_rating();
		$rating_count   = $product->get_rating_count();

		if ( $average_rating <= 0 && $rating_count < 1 ) {
			return;
		}

		$star_color = $this->get_star_color();
		$stars_html = $this->generate_stars_html( $average_rating, $star_color );

		// translators: %s is the average rating.
		$aria_label = sprintf( __( 'Rated %s out of 5', 'reviewbird-reviews' ), number_format( $average_rating, 2 ) );

		echo '<div class="rb-wc-rating" role="img" aria-label="' . esc_attr( $aria_label ) . '">';
		echo $stars_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML is escaped in generate_stars_html.

		if ( $rating_count > 0 ) {
			printf(
				'<span class="rb-wc-rating-count">(%s)</span>',
				esc_html( $rating_count )
			);
		}

		echo '</div>';
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
