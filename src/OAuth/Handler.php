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

		$client_id = reviewapp_ensure_oauth_client();

		if ( is_wp_error( $client_id ) ) {
			set_transient( 'reviewapp_oauth_error', $client_id->get_error_message(), 60 );
			wp_safe_redirect( admin_url( 'options-general.php?page=reviewapp-settings' ) );
			exit;
		}

		$state        = wp_generate_uuid4();
		$redirect_uri = admin_url( 'admin.php?page=reviewapp-settings' );
		$callback_url = add_query_arg( 'reviewapp_oauth_callback', '1', $redirect_uri );

		// Generate PKCE parameters for OAuth security.
		$code_verifier = $this->generate_code_verifier();
		$code_challenge = $this->generate_code_challenge( $code_verifier );

		// Store OAuth state and code verifier in database for security.
		$this->store_oauth_state( $state, $redirect_uri, $code_verifier );

		// Schedule cleanup of expired states using Action Scheduler.
		if ( function_exists( 'as_schedule_single_action' ) && ! as_next_scheduled_action( 'reviewapp_cleanup_oauth_states' ) ) {
			as_schedule_single_action( time() + self::STATE_EXPIRATION, 'reviewapp_cleanup_oauth_states' );
		}

        // Build OAuth URL with PKCE parameters.
        $oauth_params = array(
            'client_id'              => $client_id,
            'redirect_uri'           => $callback_url,
            'response_type'          => 'code',
            'state'                  => $state,
            'scope'                  => 'store:manage',
            'code_challenge'         => $code_challenge,
            'code_challenge_method'  => 'S256',
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

		// Verify OAuth state for security and get code verifier.
		$code_verifier = $this->verify_oauth_state( $state );
		if ( ! $code_verifier ) {
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-error"><p>' . 
					 esc_html__( 'Invalid OAuth state. Please try again.', 'reviewapp-reviews' ) . 
					 '</p></div>';
			});
			return;
		}

		// Exchange authorization code for access token using Action Scheduler.
		$client_id = reviewapp_get_oauth_client_id();

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'reviewapp_process_oauth_token',
				array(
					'code'          => $code,
					'code_verifier' => $code_verifier,
					'client_id'     => $client_id,
				)
			);
			
			add_action( 'admin_notices', function() {
				echo '<div class="notice notice-info"><p>' . 
					 esc_html__( 'Processing ReviewApp connection...', 'reviewapp-reviews' ) . 
					 '</p></div>';
			});
		} else {
			// Fallback to immediate processing if Action Scheduler not available.
			$this->process_oauth_token( $code, $code_verifier, $client_id );
		}

		// Clean up OAuth state.
		$this->cleanup_oauth_state( $state );
	}

	/**
	 * Process OAuth token exchange (Action Scheduler callback).
	 *
	 * @param string $code Authorization code.
	 * @param string $code_verifier PKCE code verifier.
	 * @param string $client_id OAuth client identifier.
	 */
	public function process_oauth_token( $code, $code_verifier = '', $client_id = '' ) {
		$callback_url = reviewapp_get_oauth_callback_url();

		if ( empty( $client_id ) ) {
			$client_id = reviewapp_get_oauth_client_id();
		}

		if ( empty( $client_id ) ) {
			error_log( 'ReviewApp: OAuth client ID missing during token exchange.' );
			return;
		}

        $token_params = array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $callback_url,
            'client_id'     => $client_id,
            'code_verifier' => $code_verifier,
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

		// Store store ID from token response.
		if ( isset( $token_data['store_id'] ) ) {
			update_option( 'reviewapp_store_id', $token_data['store_id'] );
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
	 * @param string $code_verifier PKCE code verifier.
	 */
	private function store_oauth_state( $state, $redirect_uri, $code_verifier = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reviewapp_oauth_states';
		$user_id    = get_current_user_id();
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::STATE_EXPIRATION );

		$wpdb->insert(
			$table_name,
			array(
				'state'         => $state,
				'redirect_uri'  => $redirect_uri,
				'user_id'       => $user_id,
				'code_verifier' => $code_verifier,
				'expires_at'    => $expires_at,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Verify OAuth state.
	 *
	 * @param string $state OAuth state to verify.
	 * @return string|false Code verifier if valid, false otherwise.
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

		return $result ? $result->code_verifier : false;
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

	/**
	 * Generate PKCE code verifier.
	 *
	 * @return string Base64 URL-safe encoded random string.
	 */
	private function generate_code_verifier() {
		// Generate random bytes and create base64url encoded string.
		$random_bytes = wp_generate_password( 32, false );
		return rtrim( strtr( base64_encode( $random_bytes ), '+/', '-_' ), '=' );
	}

	/**
	 * Generate PKCE code challenge from code verifier.
	 *
	 * @param string $code_verifier Code verifier.
	 * @return string Base64 URL-safe encoded SHA256 hash.
	 */
	private function generate_code_challenge( $code_verifier ) {
		$challenge = hash( 'sha256', $code_verifier, true );
		return rtrim( strtr( base64_encode( $challenge ), '+/', '-_' ), '=' );
	}
}