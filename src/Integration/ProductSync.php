<?php
/**
 * Product sync manager for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

use reviewbird\Api\Client;
use WC_Logger;

/**
 * Product sync class.
 */
class ProductSync {

	/**
	 * Option name for sync status.
	 */
	const SYNC_STATUS_OPTION = 'reviewbird_sync_status';

	/**
	 * Meta key for synced products.
	 */
	const SYNCED_META_KEY = '_reviewbird_synced';

	/**
	 * Batch size for sync operations.
	 */
	const BATCH_SIZE = 10;

	/**
	 * API client instance.
	 *
	 * @var Client
	 */
	private $api_client;

	/**
	 * Logger instance.
	 *
	 * @var WC_Logger
	 */
	private $logger;

	/**
	 * Initialize product sync.
	 */
	public function __construct() {
		$this->api_client = new Client();
		$this->logger     = wc_get_logger();
	}

	/**
	 * Get sync status.
	 *
	 * @return array Sync status data.
	 */
	public function get_sync_status() {
		$status = get_option( self::SYNC_STATUS_OPTION, array(
			'is_syncing'       => false,
			'total_products'   => 0,
			'synced_products'  => 0,
			'failed_products'  => 0,
			'last_sync'        => null,
			'current_batch'    => 0,
		) );

		// Get counts from database
		$products_with_reviews = $this->get_products_with_reviews_count();
		$synced_products       = $this->get_synced_products_count();

		// Update counts
		$status['total_products']  = $products_with_reviews;
		$status['synced_products'] = $synced_products;
		$status['needs_sync']      = $products_with_reviews > $synced_products;

		return $status;
	}

	/**
	 * Start product sync.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function start_sync() {
		// Check if sync is already running
		$status = $this->get_sync_status();
		if ( $status['is_syncing'] ) {
			return new \WP_Error( 'sync_in_progress', __( 'Sync is already in progress', 'reviewbird-reviews' ) );
		}

		// Get all products with reviews
		$product_ids = $this->get_products_with_reviews();

		if ( empty( $product_ids ) ) {
			return new \WP_Error( 'no_products', __( 'No products with reviews found', 'reviewbird-reviews' ) );
		}

		// Initialize sync status
		$total_products = count( $product_ids );
		update_option( self::SYNC_STATUS_OPTION, array(
			'is_syncing'       => true,
			'total_products'   => $total_products,
			'synced_products'  => 0,
			'failed_products'  => 0,
			'last_sync'        => null,
			'current_batch'    => 0,
			'start_time'       => time(),
		) );

		// Schedule batch sync actions
		$batches = array_chunk( $product_ids, self::BATCH_SIZE );
		foreach ( $batches as $batch_index => $batch ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time() + ( $batch_index * 5 ), // Stagger batches by 5 seconds
					'reviewbird_sync_product_batch',
					array( 'batch' => $batch, 'batch_index' => $batch_index ),
					'reviewbird-sync'
				);
			} else {
				// Fallback: process immediately without Action Scheduler
				$this->process_batch( $batch, $batch_index );
			}
		}

		$this->logger->info( 
			sprintf( 'Product sync started: %d products in %d batches', $total_products, count( $batches ) ),
			array( 'source' => 'reviewbird' )
		);

		return true;
	}

	/**
	 * Process a batch of products.
	 *
	 * @param array $product_ids Array of product IDs to sync.
	 * @param int   $batch_index Batch index number.
	 */
	public function process_batch( $product_ids, $batch_index = 0 ) {
		$status         = $this->get_sync_status();
		$synced_count   = 0;
		$failed_count   = 0;

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				$failed_count++;
				$this->logger->error(
					sprintf( 'Product %d not found', $product_id ),
					array( 'source' => 'reviewbird', 'product_id' => $product_id )
				);
				continue;
			}

			// Build product data
			$product_data = $this->build_product_data( $product );
			if ( is_wp_error( $product_data ) ) {
				$failed_count++;
				$this->logger->error(
					sprintf( 'Failed to build data for product %d (%s): %s', $product_id, $product->get_name(), $product_data->get_error_message() ),
					array( 
						'source' => 'reviewbird',
						'product_id' => $product_id,
						'product_name' => $product->get_name(),
					)
				);
				continue;
			}

			// Sync individual product
			$result = $this->api_client->sync_product( $product_data );

			if ( is_wp_error( $result ) ) {
				$failed_count++;
				$this->logger->error(
					sprintf( 
						'Failed to sync product %d (%s) in batch %d: %s',
						$product_id,
						$product->get_name(),
						$batch_index,
						$result->get_error_message()
					),
					array( 
						'source' => 'reviewbird',
						'product_id' => $product_id,
						'product_name' => $product->get_name(),
						'batch' => $batch_index,
						'error_data' => $result->get_error_data(),
					)
				);
			} else {
				// Successfully synced
				$synced_count++;
				update_post_meta( $product_id, self::SYNCED_META_KEY, time() );
			}
		}

		// Update sync status
		$status['synced_products']  = ( $status['synced_products'] ?? 0 ) + $synced_count;
		$status['failed_products']  = ( $status['failed_products'] ?? 0 ) + $failed_count;
		$status['current_batch']    = $batch_index + 1;

		// Check if this is the last batch
		$total_batches = ceil( $status['total_products'] / self::BATCH_SIZE );
		if ( $status['current_batch'] >= $total_batches ) {
			$status['is_syncing'] = false;
			$status['last_sync']  = time();
			
			$this->logger->info(
				sprintf( 
					'Product sync completed: %d synced, %d failed out of %d total',
					$status['synced_products'],
					$status['failed_products'],
					$status['total_products']
				),
				array( 'source' => 'reviewbird' )
			);
		}

		update_option( self::SYNC_STATUS_OPTION, $status );
	}

	/**
	 * Build product data for API.
	 *
	 * @param \WC_Product $product Product object.
	 * @return array|WP_Error Product data array or error.
	 */
	private function build_product_data( $product ) {
		if ( ! $product ) {
			return new \WP_Error( 'invalid_product', __( 'Invalid product', 'reviewbird-reviews' ) );
		}

		$product_id = $product->get_id();

		// Build base product data
		$data = array(
			'external_id' => (string) $product_id,
			'slug'        => $product->get_slug(),
			'sku'         => $product->get_sku() ?: null,
			'title'       => $product->get_name(),
			'url'         => get_permalink( $product_id ),
			'image'       => wp_get_attachment_url( $product->get_image_id() ) ?: null,
			'vendor'      => null, // Can be extended with vendor plugin support
			'category'    => null,
			'tags'        => array(),
			'description' => $product->get_description() ?: $product->get_short_description(),
			'images'      => array(),
			'in_stock'    => $product->is_in_stock(),
		);

		// Get categories
		$categories = get_the_terms( $product_id, 'product_cat' );
		if ( $categories && ! is_wp_error( $categories ) ) {
			$data['category'] = $categories[0]->name;
		}

		// Get tags
		$tags = get_the_terms( $product_id, 'product_tag' );
		if ( $tags && ! is_wp_error( $tags ) ) {
			$data['tags'] = wp_list_pluck( $tags, 'name' );
		}

		// Get gallery images
		$gallery_ids = $product->get_gallery_image_ids();
		if ( ! empty( $gallery_ids ) ) {
			foreach ( $gallery_ids as $image_id ) {
				$image_url = wp_get_attachment_url( $image_id );
				if ( $image_url ) {
					$data['images'][] = $image_url;
				}
			}
		}

		// Handle variations - required by API (min:1)
		$variations = array();
		
		if ( $product->is_type( 'variable' ) ) {
			// Variable product: get all variations
			$variation_ids = $product->get_children();
			foreach ( $variation_ids as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation ) {
					continue;
				}

				$variation_data = array(
					'external_id' => (string) $variation_id,
					'slug'        => $variation->get_slug(),
					'sku'         => $variation->get_sku() ?: null,
					'barcode'     => get_post_meta( $variation_id, '_barcode', true ) ?: null,
					'title'       => $variation->get_name(),
					'image'       => wp_get_attachment_url( $variation->get_image_id() ) ?: null,
					'price'       => $variation->get_price() ? (float) $variation->get_price() : null,
					'attributes'  => array(),
					'active'      => $variation->is_purchasable(),
				);

				// Get variation attributes
				$attributes = $variation->get_variation_attributes();
				if ( ! empty( $attributes ) ) {
					foreach ( $attributes as $key => $value ) {
						// Remove 'attribute_' prefix if present
						$clean_key = str_replace( 'attribute_', '', $key );
						$variation_data['attributes'][ $clean_key ] = $value;
					}
				}

				$variations[] = $variation_data;
			}
		} else {
			// Simple product: create a single variation representing the product itself
			$variations[] = array(
				'external_id' => (string) $product_id,
				'slug'        => $product->get_slug(),
				'sku'         => $product->get_sku() ?: null,
				'barcode'     => get_post_meta( $product_id, '_barcode', true ) ?: null,
				'title'       => $product->get_name(),
				'image'       => wp_get_attachment_url( $product->get_image_id() ) ?: null,
				'price'       => $product->get_price() ? (float) $product->get_price() : null,
				'attributes'  => array(),
				'active'      => $product->is_purchasable(),
			);
		}

		// Variations are always required (min:1)
		$data['variations'] = $variations;

		return $data;
	}

	/**
	 * Get product IDs that have reviews.
	 *
	 * @return array Array of product IDs.
	 */
	private function get_products_with_reviews() {
		global $wpdb;

		$product_ids = $wpdb->get_col(
			"SELECT DISTINCT comment_post_ID 
			FROM {$wpdb->comments} 
			WHERE comment_type = 'review' 
			AND comment_approved = '1'
			AND comment_post_ID IN (
				SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'
			)"
		);

		return array_map( 'absint', $product_ids );
	}

	/**
	 * Get count of products with reviews.
	 *
	 * @return int Count of products.
	 */
	private function get_products_with_reviews_count() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT comment_post_ID) 
			FROM {$wpdb->comments} 
			WHERE comment_type = 'review' 
			AND comment_approved = '1'
			AND comment_post_ID IN (
				SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'
			)"
		);
	}

	/**
	 * Get count of synced products.
	 *
	 * @return int Count of synced products.
	 */
	private function get_synced_products_count() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->postmeta} 
				WHERE meta_key = %s",
				self::SYNCED_META_KEY
			)
		);
	}

	/**
	 * Reset sync status.
	 */
	public function reset_sync() {
		delete_option( self::SYNC_STATUS_OPTION );
		
		$this->logger->info(
			'Sync status reset',
			array( 'source' => 'reviewbird' )
		);
	}

	/**
	 * Clear all synced product meta.
	 */
	public function clear_synced_meta() {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::SYNCED_META_KEY
			)
		);

		$this->logger->info(
			sprintf( 'Cleared sync meta for %d products', $deleted ),
			array( 'source' => 'reviewbird' )
		);

		return $deleted;
	}
}
