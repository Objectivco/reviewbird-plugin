<?php
/**
 * OAuth flow handler for ReviewApp integration.
 *
 * @package ReviewApp
 */

namespace ReviewApp\OAuth;

use ReviewApp\Api\Client;

/**
 * OAuth flow handler.
 */
class Handler {

	/**
	 * OAuth state expiration time (1 hour).
	 */
	const STATE_EXPIRATION = 3600;

	/**
	 * Start OAuth flow.
	 */
	public function start_oauth_flow() {
		// Verify nonce for security.
		if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'reviewapp_oauth_start' ) ) {
			wp_die( esc_html__( 'Security check failed', 'reviewapp-reviews' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'reviewapp-reviews' ) );
		}

		$state        = wp_generate_uuid4();
		$redirect_uri = admin_url( 'admin.php?page=reviewapp-settings' );
		$callback_url = add_query_arg( 'reviewapp_oauth_callback', '1', $redirect_uri );

		// Store OAuth state in database for security.
		$this->store_oauth_state( $state, $redirect_uri );

		// Schedule cleanup of expired states using Action Scheduler.
		if ( function_exists( 'as_schedule_single_action' ) && ! as_next_scheduled_action( 'reviewapp_cleanup_oauth_states' ) ) {
			as_schedule_single_action( time() + self::STATE_EXPIRATION, 'reviewapp_cleanup_oauth_states' );
		}

		// Build OAuth URL.
		$oauth_params = array(
			'client_id'     => 'wordpress-plugin',
			'redirect_uri'  => $callback_url,
			'response_type' => 'code',
			'state'         => $state,
			'scope'         => 'store:manage',
		);

		$oauth_url = reviewapp_get_oauth_url() . '/authorize?' . http_build_query( $oauth_params );

		// Redirect to OAuth provider.
		wp_redirect( $oauth_url );
		exit;
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_oauth_callback() {
		// Check if this is an OAuth callback.
		if ( ! isset( $_GET['reviewapp_oauth_callback'] ) ) {
			return;
		}

		// Check for error from OAuth provider.
		if ( isset( $_GET['error'] ) ) {
			$error_description = sanitize_text_field( $_GET['error_description'] ?? 'Unknown error' );
			add_action( 'admin_notices', function() use ( $error_description ) {
				echo '<div class="notice notice-error"><p>' . 
					 sprintf( 
						/* translators: %s: Error description */
						esc_html__( 'ReviewApp connection failed: %s', 'reviewapp-reviews' ), 
						esc_html( $error_description ) 
					) . 
					 '</p></div>';
			});
			return;
		}

		$code  = sanitize_text_field( $_GET['code'] ?? '' );
		$state = sanitize_text_field( $_GET['state'] ?? '' );

		if ( ! $code || ! $state ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					 esc_html__( 'Invalid OAuth callback parameters', 'reviewapp-reviews' ) . 
					 '</p></div>';
			});
			return;
		}

		// Verify OAuth state for security.
		if ( ! $this->verify_oauth_state( $state ) ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					 esc_html__( 'Invalid OAuth state. Please try again.', 'reviewapp-reviews' ) . 
					 '</p></div>';
			});
			return;
		}

		// Exchange authorization code for access token using Action Scheduler.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'reviewapp_process_oauth_token', array( 'code' => $code ) );
			
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-info"><p>' . 
					 esc_html__( 'Processing ReviewApp connection...', 'reviewapp-reviews' ) . 
					 '</p></div>';
			});
		} else {
			// Fallback to immediate processing if Action Scheduler not available.
			$this->process_oauth_token( $code );
		}

		// Clean up OAuth state.
		$this->cleanup_oauth_state( $state );
	}

	/**
	 * Process OAuth token exchange (Action Scheduler callback).
	 *
	 * @param string $code Authorization code.
	 */
	public function process_oauth_token( $code ) {
		$callback_url = add_query_arg( 'reviewapp_oauth_callback', '1', admin_url( 'admin.php?page=reviewapp-settings' ) );

		$token_params = array(
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => $callback_url,
			'client_id'    => 'wordpress-plugin',
		);

		$response = wp_remote_post(
			reviewapp_get_oauth_url() . '/token',
			array(
				'body'    => $token_params,
				'headers' => array(
					'Accept' => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'ReviewApp OAuth token exchange failed: ' . $response->get_error_message() );
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$body          = wp_remote_retrieve_body( $response );
		$token_data    = json_decode( $body, true );

		if ( $response_code !== 200 || ! isset( $token_data['access_token'] ) ) {
			error_log( 'ReviewApp OAuth token exchange failed: Invalid response' );
			return;
		}

		// Store the access token (this will be the store token).
		$store_token = $token_data['access_token'];
		update_option( 'reviewapp_store_token', $store_token );

		// Extract and store store ID.
		$api_client = new Client();
		$store_id = $api_client->get_store_id_from_token( $store_token );
		if ( $store_id ) {
			update_option( 'reviewapp_store_id', $store_id );
		}

		// Configure media domains.
		$domains = array( parse_url( get_site_url(), PHP_URL_HOST ) );
		$api_client->configure_media_domains( $domains );

		// Set connection success flag for admin notice.
		set_transient( 'reviewapp_oauth_success', true, 300 );
	}

	/**
	 * Store OAuth state in database.
	 *
	 * @param string $state        OAuth state.
	 * @param string $redirect_uri Redirect URI.
	 */
	private function store_oauth_state( $state, $redirect_uri ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reviewapp_oauth_states';
		$user_id    = get_current_user_id();
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::STATE_EXPIRATION );

		$wpdb->insert(
			$table_name,
			array(
				'state'        => $state,
				'redirect_uri' => $redirect_uri,
				'user_id'      => $user_id,
				'expires_at'   => $expires_at,
			),
			array( '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Verify OAuth state.
	 *
	 * @param string $state OAuth state to verify.
	 * @return bool True if valid, false otherwise.
	 */
	private function verify_oauth_state( $state ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reviewapp_oauth_states';
		$user_id    = get_current_user_id();
		$now        = current_time( 'mysql', true );

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE state = %s AND user_id = %d AND expires_at > %s",
				$state,
				$user_id,
				$now
			)
		);

		return $result !== null;
	}

	/**
	 * Clean up specific OAuth state.
	 *
	 * @param string $state OAuth state to clean up.
	 */
	private function cleanup_oauth_state( $state ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reviewapp_oauth_states';

		$wpdb->delete(
			$table_name,
			array( 'state' => $state ),
			array( '%s' )
		);
	}

	/**
	 * Clean up expired OAuth states (Action Scheduler callback).
	 */
	public function cleanup_expired_oauth_states() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reviewapp_oauth_states';
		$now        = current_time( 'mysql', true );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE expires_at < %s",
				$now
			)
		);
	}
}