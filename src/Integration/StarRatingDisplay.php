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

		// Ratings outside the main product, such as shop loops, do not open the widget.
		$is_static = ! is_product() || doing_action( 'woocommerce_after_shop_loop_item_title' )
			|| ( $this->rating_product && $this->rating_product->get_id() !== get_queried_object_id() );
		$is_static = apply_filters( 'reviewbird_rating_is_static', $is_static );
		$label     = $is_static
			// translators: %s is the average rating.
			? __( 'Rated %s out of 5', 'reviewbird' )
			// translators: %s is the average rating.
			: __( 'Rated %s out of 5, click to view reviews', 'reviewbird' );

		$output = sprintf(
			'<div class="rb-wc-rating%s"%s aria-label="%s">',
			$is_static ? ' rb-wc-rating--static' : '',
			$is_static ? '' : ' role="button" tabindex="0"',
			esc_attr( sprintf( $label, number_format_i18n( $rating, 2 ) ) )
		);

		// Round to the nearest half star.
		$halves = (int) round( $rating * 2 );
		$color  = get_option( 'reviewbird_star_color' );
		$color  = $color ? $color : '#ffa500';
		for ( $star = 1; $star <= 5; $star++ ) {
			$fill    = $halves >= 2 * $star ? 'fill' : ( 2 * $star - 1 === $halves ? 'half' : 'empty' );
			$output .= sprintf( '<span class="rb-wc-star rb-wc-star-%s" style="color: %s;"></span>', $fill, esc_attr( 'empty' === $fill ? '#ddd' : $color ) );
		}

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
}
