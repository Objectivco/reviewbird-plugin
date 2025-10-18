<?php
/**
 * Action Scheduler integration for ReviewBop.
 *
 * @package ReviewBop
 */

namespace ReviewBop\Core;

use ReviewBop\Api\Client;
use ReviewBop\OAuth\Handler;

/**
 * Action Scheduler integration.
 */
class ActionScheduler {

	/**
	 * Initialize Action Scheduler hooks.
	 */
	public static function init() {
		// Only proceed if Action Scheduler is available.
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		// API request processing.
		add_action( 'reviewbop_process_api_request', array( self::class, 'process_api_request' ), 10, 3 );

		// OAuth cleanup.
		add_action( 'reviewbop_cleanup_oauth_states', array( Handler::class, 'cleanup_expired_oauth_states' ) );

		// OAuth token processing.
		add_action( 'reviewbop_process_oauth_token', array( new Handler(), 'process_oauth_token' ), 10, 3 );

		// Media domain configuration.
		add_action( 'reviewbop_configure_domains', array( self::class, 'configure_domains' ) );

		// Review deletion.
		add_action( 'reviewbop_delete_review', array( self::class, 'delete_review' ) );

		// Schedule recurring cleanup if not already scheduled.
		if ( ! as_next_scheduled_action( 'reviewbop_cleanup_oauth_states' ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'reviewbop_cleanup_oauth_states' );
		}
	}

	/**
	 * Process API request (Action Scheduler callback).
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     Request data.
	 * @param string $method   HTTP method.
	 * @throws \Exception If API request fails.
	 */
	public static function process_api_request( $endpoint, $data, $method ) {
		$api_client = new Client();
		$result = $api_client->process_queued_request( $endpoint, $data, $method );

		if ( is_wp_error( $result ) ) {
			throw new \Exception( 'API request failed: ' . $result->get_error_message() );
		}
	}

	/**
	 * Configure media domains (Action Scheduler callback).
	 *
	 * @param array $domains Array of domains to configure.
	 */
	public static function configure_domains( $domains ) {
		$api_client = new Client();
		$result = $api_client->configure_media_domains( $domains );

		if ( is_wp_error( $result ) ) {
			error_log( 'ReviewBop: Failed to configure media domains: ' . $result->get_error_message() );
		}
	}

	/**
	 * Delete review (Action Scheduler callback).
	 *
	 * @param array $delete_data Review deletion data.
	 */
	public static function delete_review( $delete_data ) {
		$api_client = new Client();
		$result = $api_client->delete_review( $delete_data );

		if ( is_wp_error( $result ) ) {
			error_log( 'ReviewBop: Failed to delete review: ' . $result->get_error_message() );
		}
	}
}