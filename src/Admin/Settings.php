<?php
/**
 * Admin settings page for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Admin;

/**
 * Admin settings page.
 */
class Settings {

	/**
	 * Initialize admin hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_reviewbird_update_enable_schema_setting', array( $this, 'handle_schema_setting_update' ) );
		add_action( 'wp_ajax_reviewbird_update_enable_widget_setting', array( $this, 'handle_widget_setting_update' ) );
		add_action( 'wp_ajax_reviewbird_clear_health_cache', array( $this, 'handle_clear_health_cache' ) );
		add_action( 'admin_notices', array( $this, 'display_oauth_notices' ) );
	}

	/**
	 * Display OAuth success/error notices from transients.
	 */
	public function display_oauth_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_reviewbird-settings' !== $screen->id ) {
			return;
		}

		$this->display_oauth_error_notice();
		$this->display_oauth_success_notice();
	}

	/**
	 * Display OAuth error notice if present.
	 */
	private function display_oauth_error_notice(): void {
		$error = get_transient( 'reviewbird_oauth_error' );
		if ( ! $error ) {
			return;
		}

		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'reviewbird Connection Error:', 'reviewbird-reviews' ); ?></strong>
				<?php echo esc_html( $error ); ?>
			</p>
		</div>
		<?php
		delete_transient( 'reviewbird_oauth_error' );
	}

	/**
	 * Display OAuth success notice if present.
	 */
	private function display_oauth_success_notice(): void {
		$success = get_transient( 'reviewbird_oauth_success' );
		if ( ! $success ) {
			return;
		}

		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Successfully connected to reviewbird!', 'reviewbird-reviews' ); ?></p>
		</div>
		<?php
		delete_transient( 'reviewbird_oauth_success' );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'reviewbird Settings', 'reviewbird-reviews' ),
			__( 'reviewbird', 'reviewbird-reviews' ),
			'manage_options',
			'reviewbird-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'settings_page_reviewbird-settings' !== $hook ) {
			return;
		}

		$asset_data = $this->get_asset_data();

		wp_enqueue_script(
			'reviewbird-admin',
			REVIEWBIRD_PLUGIN_URL . 'assets/build/admin.js',
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);

		wp_localize_script( 'reviewbird-admin', 'reviewbirdAdmin', $this->get_script_localization_data() );

		wp_enqueue_style(
			'reviewbird-admin',
			REVIEWBIRD_PLUGIN_URL . 'assets/build/admin.css',
			array(),
			$asset_data['version']
		);
	}

	/**
	 * Get asset data from the build manifest.
	 *
	 * @return array{dependencies: array<string>, version: string}
	 */
	private function get_asset_data(): array {
		$asset_file    = REVIEWBIRD_PLUGIN_DIR . 'assets/build/admin.asset.php';
		$default_asset = array(
			'dependencies' => array(),
			'version'      => REVIEWBIRD_VERSION,
		);

		return file_exists( $asset_file ) ? include $asset_file : $default_asset;
	}

	/**
	 * Get localization data for the admin script.
	 *
	 * @return array<string, mixed>
	 */
	private function get_script_localization_data(): array {
		return array(
			'restUrl'      => rest_url( 'reviewbird/v1' ),
			'nonce'        => wp_create_nonce( 'reviewbird_admin_nonce' ),
			'apiUrl'       => reviewbird_get_api_url(),
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'enableSchema' => get_option( 'reviewbird_enable_schema', 'yes' ) === 'yes',
			'enableWidget' => get_option( 'reviewbird_enable_widget', 'yes' ) === 'yes',
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		?>
		<div class="wrap">
			<div id="reviewbird-settings-root"></div>
		</div>
		<?php
	}

	/**
	 * Verify AJAX request has valid nonce and user permissions.
	 *
	 * Sends JSON error response and terminates if validation fails.
	 */
	private function verify_ajax_request(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'reviewbird_admin_nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token', 'reviewbird-reviews' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'reviewbird-reviews' ), 403 );
		}
	}

	/**
	 * Check if a POST parameter is set to '1'.
	 *
	 * @param string $key The POST parameter key.
	 * @return bool True if the parameter equals '1'.
	 */
	private function is_post_param_enabled( string $key ): bool {
		return isset( $_POST[ $key ] ) && '1' === $_POST[ $key ];
	}

	/**
	 * Handle AJAX request to update schema setting.
	 */
	public function handle_schema_setting_update(): void {
		$this->verify_ajax_request();

		$enabled = $this->is_post_param_enabled( 'enable_schema' );
		update_option( 'reviewbird_enable_schema', $enabled ? 'yes' : 'no' );

		wp_send_json_success(
			array(
				'enable_schema' => $enabled,
				'message'       => __( 'Schema setting updated successfully', 'reviewbird-reviews' ),
			)
		);
	}

	/**
	 * Handle AJAX request to update widget setting.
	 */
	public function handle_widget_setting_update(): void {
		$this->verify_ajax_request();

		$enabled = $this->is_post_param_enabled( 'enable_widget' );
		update_option( 'reviewbird_enable_widget', $enabled ? 'yes' : 'no' );

		wp_send_json_success(
			array(
				'enable_widget' => $enabled,
				'message'       => __( 'Widget setting updated successfully', 'reviewbird-reviews' ),
			)
		);
	}

	/**
	 * Handle AJAX request to clear health check cache.
	 */
	public function handle_clear_health_cache(): void {
		$this->verify_ajax_request();

		reviewbird_clear_status_cache();
		$status = reviewbird_get_store_status( true );

		wp_send_json_success(
			array(
				'status'  => $status,
				'message' => __( 'Health check cache cleared', 'reviewbird-reviews' ),
			)
		);
	}
}