<?php
/**
 * Polylang compatibility for Reviewbird products.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve Polylang product translations through Reviewbird filters.
 */
class Polylang {

	/**
	 * Register filters when Polylang is active.
	 */
	public function __construct() {
		if ( ! function_exists( 'pll_get_post_translations' ) ) {
			return;
		}

		add_filter( 'reviewbird_product_translation_ids', array( $this, 'get_translation_ids' ), 10, 2 );
		add_filter( 'reviewbird_original_product_id', array( $this, 'get_original_product_id' ), 10, 2 );
		add_filter( 'reviewbird_product_locale', array( $this, 'get_product_locale' ), 10, 2 );
	}

	/**
	 * Add the product translations.
	 *
	 * @param int[] $ids        Product IDs.
	 * @param int   $product_id Actual product ID.
	 * @return int[]
	 */
	public function get_translation_ids( array $ids, int $product_id ): array {
		return array_merge( $ids, array_values( pll_get_post_translations( $product_id ) ) );
	}

	/**
	 * Select the default-language product for a new identity.
	 *
	 * @param int $original_id Default identity.
	 * @param int $product_id  Actual product ID.
	 * @return int
	 */
	public function get_original_product_id( int $original_id, int $product_id ): int {
		return (int) pll_get_post( $product_id, pll_default_language() );
	}

	/**
	 * Get the product locale when no locale is supplied.
	 *
	 * @param string $locale     Product locale.
	 * @param int    $product_id Actual product ID.
	 * @return string
	 */
	public function get_product_locale( string $locale, int $product_id ): string {
		return $locale ? $locale : (string) pll_get_post_language( $product_id, 'locale' );
	}
}
