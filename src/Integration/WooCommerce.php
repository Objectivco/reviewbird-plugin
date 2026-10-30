<?php
/**
 * WooCommerce integration for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Order;
use WP_Comment;
use WP_REST_Request;
use WP_REST_Response;

/**
 * WooCommerce integration class.
 */
class WooCommerce {

	/**
	 * Initialize WooCommerce integration.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_system_status_field' ), 10, 0 );

		// Suppress WooCommerce review output and queries when Reviewbird replaces them.
		add_filter( 'pre_render_block', array( $this, 'suppress_product_reviews_block' ), 10, 2 );
		add_filter( 'comments_pre_query', array( $this, 'suppress_product_review_queries' ) );

		add_filter(
			'woocommerce_rest_prepare_product_review',
			array(
				$this,
				'add_cusrev_review_data_to_rest_response',
			),
			100,
			3
		);

		// Save locale when order is created.
		add_action( 'woocommerce_new_order', array( $this, 'save_order_locale' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( $this, 'save_order_locale' ), 10, 1 );

		// Expose locale in REST response.
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_locale_to_order_response' ), 100, 3 );
		add_filter( 'woocommerce_rest_shop_order_object_query', array( $this, 'query_all_languages' ), 10, 2 );
		add_filter( 'woocommerce_rest_product_review_query', array( $this, 'query_all_languages' ), 10, 2 );

		// Link account order items to the product review form.
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'add_account_review_link' ), 10, 4 );
		add_action( 'cfw_order_item_after_data', array( $this, 'add_checkoutwc_account_review_link' ), 10, 1 );
	}

	/**
	 * Add the Reviewbird widget setting to WooCommerce system status.
	 */
	public function register_system_status_field(): void {
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
	 * Stop the WooCommerce product reviews block before its child queries run.
	 *
	 * @param string|null $pre_render   Pre-rendered block content.
	 * @param array       $parsed_block Parsed block data.
	 * @return string|null Pre-rendered block content.
	 */
	public function suppress_product_reviews_block( $pre_render, array $parsed_block ) {
		if (
			null === $pre_render
			&& 'woocommerce/product-reviews' === ( $parsed_block['blockName'] ?? '' )
			&& is_product()
			&& reviewbird_can_show_widget()
		) {
			return '';
		}

		return $pre_render;
	}

	/**
	 * Stop product review queries when Reviewbird replaces them.
	 *
	 * @param array|int|null $comments Comment query result.
	 * @return array|int|null Comment query result.
	 */
	public function suppress_product_review_queries( $comments ) {
		return is_product() && reviewbird_can_show_widget() ? array() : $comments;
	}

	/**
	 * Add a review link to a CheckoutWC account order item.
	 *
	 * @param object $item Order item.
	 * @return void
	 */
	public function add_checkoutwc_account_review_link( $item ): void {
		$this->add_account_review_link( $item->get_id(), $item, $item->get_order() );
	}

	/**
	 * Add a review link after each product in account order details.
	 *
	 * @param int      $item_id    Order item ID.
	 * @param object   $item       Order item.
	 * @param WC_Order $order      Order.
	 * @param bool     $plain_text Whether the output is plain text.
	 * @return void
	 */
	public function add_account_review_link( $item_id, $item, $order, $plain_text = false ): void {
		$is_account_order = is_wc_endpoint_url( 'view-order' )
			|| ( is_order_received_page() && isset( $_GET['view'] ) && 'true' === $_GET['view'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $plain_text || ! $is_account_order || ! $order || ! $order->has_status( 'completed' ) || ! reviewbird_can_show_widget() ) {
			return;
		}

		$product = $item->get_product();

		if ( ! $product || ! $product->is_visible() ) {
			return;
		}

		$review_product = wc_get_product( $item->get_product_id() );
		$permalink      = apply_filters( 'woocommerce_order_item_permalink', $product->get_permalink( $item ), $item, $order );

		if ( ! $review_product || ! $permalink || ! comments_open( $review_product->get_id() ) ) {
			return;
		}

		if ( ! apply_filters( 'reviewbird_show_widget_for_product', true, $review_product ) ) {
			return;
		}

		printf(
			'<p><a class="reviewbird-leave-review woocommerce-button button" href="%1$s">%2$s</a></p>',
			esc_url( $permalink . '#write_review' ),
			esc_html__( 'Leave a review', 'reviewbird' )
		);
	}

	/**
	 * Add CusRev title and media data to the WooCommerce review REST response.
	 *
	 * CusRev stores media as WordPress attachment IDs in comment meta:
	 * - ivole_review_image2: Photo attachment IDs
	 * - ivole_review_video2: Video attachment IDs
	 *
	 * @param WP_REST_Response     $response The response object.
	 * @param WP_Comment           $review   The review comment object.
	 * @param WP_REST_Request|null $request  REST request, when available.
	 * @return WP_REST_Response Modified response with title and media data.
	 */
	public function add_cusrev_review_data_to_rest_response( $response, $review, $request = null ): WP_REST_Response {
		$media = array_merge(
			$this->get_attachment_media( $review->comment_ID, 'ivole_review_image2', 'image' ),
			$this->get_attachment_media( $review->comment_ID, 'ivole_review_video2', 'video' )
		);

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
	 * Get media items from comment meta attachment IDs.
	 *
	 * @param int    $comment_id The comment ID.
	 * @param string $meta_key   The meta key containing attachment IDs.
	 * @param string $type       The media type ('image' or 'video').
	 * @return array Array of media items with type and url.
	 */
	private function get_attachment_media( $comment_id, $meta_key, $type ): array {
		$media          = array();
		$attachment_ids = get_comment_meta( $comment_id, $meta_key, false );

		foreach ( $attachment_ids as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );
			if ( $url ) {
				$media[] = array(
					'type' => $type,
					'url'  => $url,
				);
			}
		}

		return $media;
	}

	/**
	 * Save WordPress locale as order meta when order is created.
	 *
	 * This captures the locale at the time of purchase, not at sync time.
	 * Supports multilingual stores where different customers may order in different languages.
	 *
	 * @param int|WC_Order  $order_id Order ID or order object.
	 * @param WC_Order|null $order Order object (optional, depends on hook).
	 */
	public function save_order_locale( $order_id, $order = null ) {
		if ( $order_id instanceof WC_Order ) {
			$order = $order_id;
		} elseif ( ! $order ) {
			$order = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( '_reviewbird_locale', get_locale() );
		$order->save();
	}

	/**
	 * Add locale to order REST response.
	 *
	 * Uses saved meta if available, falls back to current site locale for
	 * orders created before locale tracking was implemented.
	 *
	 * @param WP_REST_Response $response The response object.
	 * @param WC_Order         $order    The order object.
	 * @param WP_REST_Request  $request  The request object.
	 * @return WP_REST_Response Modified response with locale.
	 */
	public function add_locale_to_order_response( $response, $order, $request = null ) {
		$saved_locale             = $order->get_meta( '_reviewbird_locale' );
		$response->data['locale'] = $saved_locale ? $saved_locale : get_locale();
		if ( $request && '1' === $request->get_header( 'X-Reviewbird-Request' ) ) {
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
		}
		return $response;
	}

	/**
	 * Include every language in Reviewbird order and review imports.
	 *
	 * @param array           $args Query arguments.
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function query_all_languages( array $args, WP_REST_Request $request ): array {
		if ( '1' === $request->get_header( 'X-Reviewbird-Request' ) ) {
			$args['lang'] = '';
		}
		return $args;
	}
}
