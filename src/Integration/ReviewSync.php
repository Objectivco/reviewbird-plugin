<?php
/**
 * Review sync manager for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

use reviewbird\Api\Client;
use WC_Logger;

/**
 * Review sync class.
 */
class ReviewSync {

	/**
	 * Option name for sync status.
	 */
	const SYNC_STATUS_OPTION = 'reviewbird_review_sync_status';

	/**
	 * Meta key for synced reviews.
	 */
	const SYNCED_META_KEY = '_reviewbird_synced';

	/**
	 * Batch size for sync operations.
	 */
	const BATCH_SIZE = 20;

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
	 * Initialize review sync.
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
			'is_syncing'      => false,
			'total_reviews'   => 0,
			'synced_reviews'  => 0,
			'failed_reviews'  => 0,
			'last_sync'       => null,
			'current_batch'   => 0,
		) );

		// Get counts from database
		$total_reviews  = $this->get_approved_reviews_count();
		$synced_reviews = $this->get_synced_reviews_count();

		// Update counts
		$status['total_reviews']  = $total_reviews;
		$status['synced_reviews'] = $synced_reviews;
		$status['needs_sync']     = $total_reviews > $synced_reviews;

		return $status;
	}

	/**
	 * Start review sync.
	 *
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function start_sync() {
		// Check if sync is already running
		$status = $this->get_sync_status();
		if ( $status['is_syncing'] ) {
			return new \WP_Error( 'sync_in_progress', __( 'Sync is already in progress', 'reviewbird-reviews' ) );
		}

		// Get all approved reviews
		$review_ids = $this->get_approved_reviews();

		if ( empty( $review_ids ) ) {
			return new \WP_Error( 'no_reviews', __( 'No approved reviews found', 'reviewbird-reviews' ) );
		}

		// Initialize sync status
		$total_reviews = count( $review_ids );
		update_option( self::SYNC_STATUS_OPTION, array(
			'is_syncing'      => true,
			'total_reviews'   => $total_reviews,
			'synced_reviews'  => 0,
			'failed_reviews'  => 0,
			'last_sync'       => null,
			'current_batch'   => 0,
			'start_time'      => time(),
		) );

		// Schedule batch sync actions
		$batches = array_chunk( $review_ids, self::BATCH_SIZE );
		foreach ( $batches as $batch_index => $batch ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time() + ( $batch_index * 3 ), // Stagger batches by 3 seconds
					'reviewbird_sync_review_batch',
					array( 'batch' => $batch, 'batch_index' => $batch_index ),
					'reviewbird-sync'
				);
			} else {
				// Fallback: process immediately without Action Scheduler
				$this->process_batch( $batch, $batch_index );
			}
		}

		$this->logger->info( 
			sprintf( 'Review sync started: %d reviews in %d batches', $total_reviews, count( $batches ) ),
			array( 'source' => 'reviewbird' )
		);

		return true;
	}

	/**
	 * Process a batch of reviews.
	 *
	 * @param array $review_ids   Array of review comment IDs to sync.
	 * @param int   $batch_index Batch index number.
	 */
	public function process_batch( $review_ids, $batch_index = 0 ) {
		$status        = $this->get_sync_status();
		$synced_count  = 0;
		$failed_count  = 0;

		foreach ( $review_ids as $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( ! $comment || 'review' !== $comment->comment_type ) {
				$failed_count++;
				$this->logger->error(
					sprintf( 'Review %d not found or not a review', $comment_id ),
					array( 'source' => 'reviewbird', 'comment_id' => $comment_id )
				);
				continue;
			}

			// Build review data
			$review_data = $this->build_review_data( $comment );
			if ( is_wp_error( $review_data ) ) {
				$failed_count++;
				$this->logger->error(
					sprintf( 'Failed to build data for review %d: %s', $comment_id, $review_data->get_error_message() ),
					array( 
						'source' => 'reviewbird',
						'comment_id' => $comment_id,
					)
				);
				continue;
			}

			// Sync individual review
			$result = $this->api_client->push_review( $review_data );

			if ( is_wp_error( $result ) ) {
				$failed_count++;
				$this->logger->error(
					sprintf( 
						'Failed to sync review %d in batch %d: %s',
						$comment_id,
						$batch_index,
						$result->get_error_message()
					),
					array( 
						'source' => 'reviewbird',
						'comment_id' => $comment_id,
						'batch' => $batch_index,
						'error_data' => $result->get_error_data(),
					)
				);
			} else {
				// Successfully synced
				$synced_count++;
				update_comment_meta( $comment_id, self::SYNCED_META_KEY, time() );
			}
		}

		// Update sync status
		$status['synced_reviews']  = ( $status['synced_reviews'] ?? 0 ) + $synced_count;
		$status['failed_reviews']  = ( $status['failed_reviews'] ?? 0 ) + $failed_count;
		$status['current_batch']   = $batch_index + 1;

		// Check if this is the last batch
		$total_batches = ceil( $status['total_reviews'] / self::BATCH_SIZE );
		if ( $status['current_batch'] >= $total_batches ) {
			$status['is_syncing'] = false;
			$status['last_sync']  = time();
			
			$this->logger->info(
				sprintf( 
					'Review sync completed: %d synced, %d failed out of %d total',
					$status['synced_reviews'],
					$status['failed_reviews'],
					$status['total_reviews']
				),
				array( 'source' => 'reviewbird' )
			);
		}

		update_option( self::SYNC_STATUS_OPTION, $status );

		$this->logger->info(
			sprintf( 
				'Batch %d processed: %d synced, %d failed',
				$batch_index,
				$synced_count,
				$failed_count
			),
			array( 'source' => 'reviewbird' )
		);
	}

	/**
	 * Build review data for API.
	 *
	 * @param object $comment Comment object.
	 * @return array|\WP_Error Review data array or error.
	 */
	private function build_review_data( $comment ) {
		if ( ! $comment ) {
			return new \WP_Error( 'invalid_comment', __( 'Invalid comment', 'reviewbird-reviews' ) );
		}

		$product_id = $comment->comment_post_ID;
		$rating     = get_comment_meta( $comment->comment_ID, 'rating', true );
		
		// Check if this is a verified purchase
		$verified_purchase = (bool) get_comment_meta( $comment->comment_ID, 'verified', true );

		$data = array(
			'product_external_id' => (string) $product_id,
			'review_external_id'  => 'wp_' . $comment->comment_ID,
			'author'              => array(
				'name'  => $comment->comment_author,
				'email' => $comment->comment_author_email,
			),
			'rating'              => $rating ? absint( $rating ) : 5,
			'body'                => $comment->comment_content,
			'status'              => '1' === $comment->comment_approved ? 'approved' : 'pending',
			'verified_purchase'   => $verified_purchase,
			'created_at'          => gmdate( 'c', strtotime( $comment->comment_date_gmt ) ),
		);

		return $data;
	}

	/**
	 * Get approved review comment IDs.
	 *
	 * @return array Array of comment IDs.
	 */
	private function get_approved_reviews() {
		global $wpdb;

		$comment_ids = $wpdb->get_col(
			"SELECT comment_ID 
			FROM {$wpdb->comments} 
			WHERE comment_type = 'review' 
			AND comment_approved = '1'
			AND comment_post_ID IN (
				SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'
			)"
		);

		return array_map( 'absint', $comment_ids );
	}

	/**
	 * Get count of approved reviews.
	 *
	 * @return int Count of reviews.
	 */
	private function get_approved_reviews_count() {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) 
			FROM {$wpdb->comments} 
			WHERE comment_type = 'review' 
			AND comment_approved = '1'
			AND comment_post_ID IN (
				SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'
			)"
		);
	}

	/**
	 * Get count of synced reviews.
	 *
	 * @return int Count of synced reviews.
	 */
	private function get_synced_reviews_count() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->commentmeta} 
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
			'Review sync status reset',
			array( 'source' => 'reviewbird' )
		);
	}

	/**
	 * Clear all synced review meta.
	 */
	public function clear_synced_meta() {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->commentmeta} WHERE meta_key = %s",
				self::SYNCED_META_KEY
			)
		);

		$this->logger->info(
			sprintf( 'Cleared sync meta for %d reviews', $deleted ),
			array( 'source' => 'reviewbird' )
		);

		return $deleted;
	}
}
