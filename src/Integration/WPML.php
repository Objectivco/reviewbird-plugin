<?php
/**
 * WPML compatibility for Reviewbird products and imports.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Request;

/**
 * Resolve WPML translations and keep imports across all languages.
 */
class WPML {

	/**
	 * WPML query languages to restore after Reviewbird REST requests.
	 *
	 * @var array
	 */
	private $rest_languages = array();

	/**
	 * Register filters when WPML is active.
	 */
	public function __construct() {
		if ( ! has_filter( 'wpml_element_trid' ) ) {
			return;
		}

		add_filter( 'reviewbird_product_translation_ids', array( $this, 'get_translation_ids' ), 10, 2 );
		add_filter( 'reviewbird_original_product_id', array( $this, 'get_original_product_id' ), 10, 2 );
		add_filter( 'reviewbird_product_locale', array( $this, 'get_product_locale' ), 10, 2 );
		add_filter( 'woocommerce_rest_product_review_query', array( $this, 'exclude_duplicate_review_imports' ), 100, 2 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'use_all_wpml_languages' ), 100, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'restore_wpml_language' ), 100, 3 );
	}

	/**
	 * Add the product translations, including unpublished products.
	 *
	 * @param int[] $ids        Product IDs.
	 * @param int   $product_id Actual product ID.
	 * @return int[]
	 */
	public function get_translation_ids( array $ids, int $product_id ): array {
		$type  = 'post_' . get_post_type( $product_id );
		$group = apply_filters( 'wpml_element_trid', null, $product_id, $type );
		if ( ! $group ) {
			return $ids;
		}

		foreach ( (array) apply_filters( 'wpml_get_element_translations', array(), $group, $type, false, true ) as $translation ) {
			$ids[] = (int) $translation->element_id;
		}
		return $ids;
	}

	/**
	 * Select the original product for a new identity.
	 *
	 * @param int $original_id Default identity.
	 * @param int $product_id  Actual product ID.
	 * @return int
	 */
	public function get_original_product_id( int $original_id, int $product_id ): int {
		return (int) apply_filters( 'wpml_original_element_id', $original_id, $product_id, 'post_' . get_post_type( $product_id ) );
	}

	/**
	 * Get the product locale when no locale is supplied.
	 *
	 * @param string $locale     Product locale.
	 * @param int    $product_id Actual product ID.
	 * @return string
	 */
	public function get_product_locale( string $locale, int $product_id ): string {
		if ( $locale ) {
			return $locale;
		}

		$language = apply_filters( 'wpml_post_language_details', null, $product_id );
		return is_array( $language ) && ! empty( $language['language_code'] ) ? (string) ( $language['locale'] ?? '' ) : '';
	}

	/**
	 * Import each original review once when WPML has copied its comments.
	 *
	 * @param array           $args    Comment query arguments.
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function exclude_duplicate_review_imports( array $args, WP_REST_Request $request ): array {
		if ( '1' === $request->get_header( 'X-Reviewbird-Request' ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				$args['meta_query'] ?? array(),
				array(
					'key'     => '_icl_duplicate_of',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		return $args;
	}

	/**
	 * Include every WPML language during Reviewbird REST callbacks.
	 *
	 * @param mixed           $response REST response.
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  REST request.
	 * @return mixed
	 */
	public function use_all_wpml_languages( $response, $handler, WP_REST_Request $request ) {
		if (
			( '1' !== $request->get_header( 'X-Reviewbird-Request' ) && ! preg_match( '#^/reviewbird/v1/(?:products|verified-purchase)(?:/|$)#', $request->get_route() ) )
			|| ! has_filter( 'wpml_current_language' )
			|| ! has_action( 'wpml_switch_language' )
		) {
			return $response;
		}

		// WPML 5 uses nested language scopes; null closes the current scope.
		$this->rest_languages[ spl_object_id( $request ) ] = defined( 'ICL_SITEPRESS_VERSION' ) && version_compare( ICL_SITEPRESS_VERSION, '5.0', '>=' )
			? null : apply_filters( 'wpml_current_language', null );
		do_action( 'wpml_switch_language', 'all' );
		return $response;
	}

	/**
	 * Restore the WPML language after a Reviewbird REST callback.
	 *
	 * @param mixed           $response REST response.
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  REST request.
	 * @return mixed
	 */
	public function restore_wpml_language( $response, $handler, WP_REST_Request $request ) {
		$key = spl_object_id( $request );
		if ( array_key_exists( $key, $this->rest_languages ) ) {
			do_action( 'wpml_switch_language', $this->rest_languages[ $key ] );
			unset( $this->rest_languages[ $key ] );
		}
		return $response;
	}
}
