<?php
/**
 * Admin settings page for ReviewApp.
 *
 * @package ReviewApp
 */

namespace ReviewApp\Admin;

use ReviewApp\Api\Client;

/**
 * Admin settings page.
 */
class Settings {

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'ReviewApp Settings', 'reviewapp-reviews' ),
			__( 'ReviewApp', 'reviewapp-reviews' ),
			'manage_options',
			'reviewapp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Initialize settings.
	 */
	public function init_settings() {
		// Register settings.
		register_setting(
			'reviewapp_settings',
			'reviewapp_store_token',
			array(
				'sanitize_callback' => array( $this, 'sanitize_store_token' ),
				'show_in_rest'      => false,
			)
		);

		// Removed auto_inject and widget_style options - using filters instead

		// Add settings sections.
		add_settings_section(
			'reviewapp_connection_section',
			__( 'Connection Settings', 'reviewapp-reviews' ),
			array( $this, 'render_connection_section' ),
			'reviewapp_settings'
		);

		// Widget section removed - using opinionated defaults with filters

		// Add settings fields.
		add_settings_field(
			'reviewapp_oauth_connect',
			__( 'Connect to ReviewApp', 'reviewapp-reviews' ),
			array( $this, 'render_oauth_connect_field' ),
			'reviewapp_settings',
			'reviewapp_connection_section'
		);

		add_settings_field(
			'reviewapp_store_token',
			__( 'Store Token (Advanced)', 'reviewapp-reviews' ),
			array( $this, 'render_store_token_field' ),
			'reviewapp_settings',
			'reviewapp_connection_section'
		);

		add_settings_field(
			'reviewapp_connection_status',
			__( 'Connection Status', 'reviewapp-reviews' ),
			array( $this, 'render_connection_status_field' ),
			'reviewapp_settings',
			'reviewapp_connection_section'
		);

		// Widget fields removed - using opinionated defaults with filters
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'settings_page_reviewapp-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'reviewapp-admin',
			REVIEWAPP_PLUGIN_URL . 'assets/build/admin.js',
			array( 'jquery' ),
			REVIEWAPP_VERSION,
			true
		);

		wp_localize_script(
			'reviewapp-admin',
			'reviewapp_admin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'reviewapp_oauth_start' ),
			)
		);

		wp_enqueue_style(
			'reviewapp-admin',
			REVIEWAPP_PLUGIN_URL . 'assets/build/admin.css',
			array(),
			REVIEWAPP_VERSION
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		// Show OAuth success message if available.
		if ( get_transient( 'reviewapp_oauth_success' ) ) {
			echo '<div class="notice notice-success"><p>' . 
				 esc_html__( 'Successfully connected to ReviewApp!', 'reviewapp-reviews' ) . 
				 '</p></div>';
			delete_transient( 'reviewapp_oauth_success' );
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'reviewapp_messages',
				'reviewapp_message',
				__( 'Settings saved successfully!', 'reviewapp-reviews' ),
				'updated'
			);
		}

		settings_errors( 'reviewapp_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="reviewapp-admin-header">
				<p><?php esc_html_e( 'Connect your WooCommerce store to ReviewApp for advanced review collection and display.', 'reviewapp-reviews' ); ?></p>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'reviewapp_settings' );
				do_settings_sections( 'reviewapp_settings' );
				submit_button( __( 'Save Settings', 'reviewapp-reviews' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize store token input.
	 *
	 * @param string $token The store token.
	 * @return string The sanitized token.
	 */
	public function sanitize_store_token( $token ) {
		$token = sanitize_text_field( $token );

		// Allow empty token to disconnect.
		if ( empty( $token ) ) {
			delete_option( 'reviewapp_store_id' );
			return '';
		}

		// Validate token format.
		if ( ! preg_match( '/^ra_st_\d+_[a-zA-Z0-9]+$/', $token ) ) {
			add_settings_error(
				'reviewapp_store_token',
				'invalid_token_format',
				__( 'Invalid store token format. Please copy the token exactly from your ReviewApp dashboard.', 'reviewapp-reviews' )
			);
			return get_option( 'reviewapp_store_token' );
		}

		// If token changed, validate it and update store ID.
		$existing_token = get_option( 'reviewapp_store_token' );
		if ( $token !== $existing_token ) {
			$api_client = new Client();
			
			// Temporarily set token for validation.
			$old_token = get_option( 'reviewapp_store_token' );
			update_option( 'reviewapp_store_token', $token );
			
			$result = $api_client->validate_token();

			if ( is_wp_error( $result ) ) {
				// Restore old token on validation failure.
				update_option( 'reviewapp_store_token', $old_token );
				
				add_settings_error(
					'reviewapp_store_token',
					'invalid_token',
					sprintf(
						/* translators: %s: Error message */
						__( 'Invalid store token: %s', 'reviewapp-reviews' ),
						$result->get_error_message()
					)
				);
				return $old_token;
			}

			// Extract and save store ID.
			$store_id = $api_client->get_store_id_from_token( $token );
			if ( $store_id ) {
				update_option( 'reviewapp_store_id', $store_id );
			}

			// Configure media domains using Action Scheduler.
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				$domains = array( parse_url( get_site_url(), PHP_URL_HOST ) );
				as_enqueue_async_action( 'reviewapp_configure_domains', array( 'domains' => $domains ) );
			}
		}

		return $token;
	}

	/**
	 * Render connection section description.
	 */
	public function render_connection_section() {
		echo '<p>' . esc_html__( 'Connect your WordPress site to ReviewApp to start collecting and displaying reviews.', 'reviewapp-reviews' ) . '</p>';
	}



	/**
	 * Render OAuth connect field.
	 */
	public function render_oauth_connect_field() {
		$connected = get_option( 'reviewapp_store_token' );
		
		if ( $connected ) {
			echo '<p class="reviewapp-connected">' . 
				 esc_html__( 'Your store is connected to ReviewApp.', 'reviewapp-reviews' ) . 
				 '</p>';
			echo '<button type="button" class="button button-secondary" onclick="reviewappDisconnect()">' . 
				 esc_html__( 'Disconnect', 'reviewapp-reviews' ) . 
				 '</button>';
		} else {
			echo '<p>' . esc_html__( 'Connect your store to ReviewApp to get started.', 'reviewapp-reviews' ) . '</p>';
			echo '<button type="button" class="button button-primary" onclick="reviewappStartOAuth()">' . 
				 esc_html__( 'Connect to ReviewApp', 'reviewapp-reviews' ) . 
				 '</button>';
		}
		?>
		<script>
		function reviewappStartOAuth() {
			var form = document.createElement('form');
			form.method = 'POST';
			form.action = ajaxurl;
			
			var actionField = document.createElement('input');
			actionField.type = 'hidden';
			actionField.name = 'action';
			actionField.value = 'reviewapp_start_oauth';
			form.appendChild(actionField);
			
			var nonceField = document.createElement('input');
			nonceField.type = 'hidden';
			nonceField.name = 'nonce';
			nonceField.value = '<?php echo esc_js( wp_create_nonce( 'reviewapp_oauth_start' ) ); ?>';
			form.appendChild(nonceField);
			
			document.body.appendChild(form);
			form.submit();
		}
		
		function reviewappDisconnect() {
			if (confirm('<?php echo esc_js( __( 'Are you sure you want to disconnect from ReviewApp?', 'reviewapp-reviews' ) ); ?>')) {
				document.getElementById('reviewapp_store_token').value = '';
				document.querySelector('form').submit();
			}
		}
		</script>
		<?php
	}

	/**
	 * Render store token field.
	 */
	public function render_store_token_field() {
		$token = get_option( 'reviewapp_store_token' );
		?>
		<input type="password" 
			   id="reviewapp_store_token" 
			   name="reviewapp_store_token" 
			   value="<?php echo esc_attr( $token ); ?>" 
			   class="regular-text"
			   placeholder="ra_st_123_abc..."
		/>
		<p class="description">
			<?php esc_html_e( 'For advanced users: manually enter your store token from the ReviewApp dashboard.', 'reviewapp-reviews' ); ?>
		</p>
		<?php
	}

	/**
	 * Render connection status field.
	 */
	public function render_connection_status_field() {
		$token = get_option( 'reviewapp_store_token' );
		
		if ( ! $token ) {
			echo '<span class="reviewapp-status reviewapp-status-disconnected">' . 
				 esc_html__( 'Not connected', 'reviewapp-reviews' ) . 
				 '</span>';
			return;
		}

		$api_client = new Client();
		$result     = $api_client->validate_token();

		if ( is_wp_error( $result ) ) {
			echo '<span class="reviewapp-status reviewapp-status-error">' . 
				 esc_html__( 'Connection error: ', 'reviewapp-reviews' ) . 
				 esc_html( $result->get_error_message() ) . 
				 '</span>';
		} else {
			$store_info = $api_client->get_store_info();
			if ( ! is_wp_error( $store_info ) && isset( $store_info['store_id'] ) ) {
				echo '<span class="reviewapp-status reviewapp-status-connected">' . 
					 esc_html__( 'Connected', 'reviewapp-reviews' ) . 
					 ' (Store ID: ' . esc_html( $store_info['store_id'] ) . ')' .
					 '</span>';
			} else {
				echo '<span class="reviewapp-status reviewapp-status-connected">' . 
					 esc_html__( 'Connected', 'reviewapp-reviews' ) . 
					 '</span>';
			}
		}
	}


}