<?php
/**
 * Admin settings page for reviewbird.
 *
 * @package reviewbird
 */

namespace reviewbird\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use reviewbird\Integration\StarRatingDisplay;

/**
 * Admin settings page.
 */
class Settings {

	/**
	 * Allowed settings that can be updated via AJAX.
	 *
	 * @var array
	 */
	private const ALLOWED_SETTINGS = array(
		'enable_schema',
		'enable_widget',
		'force_reviews_open',
	);

	/**
	 * Initialize admin hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_reviewbird_update_setting', array( $this, 'handle_setting_update' ) );
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
				<strong><?php esc_html_e( 'reviewbird Connection Error:', 'reviewbird' ); ?></strong>
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
			<p><?php esc_html_e( 'Successfully connected to reviewbird!', 'reviewbird' ); ?></p>
		</div>
		<?php
		delete_transient( 'reviewbird_oauth_success' );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'reviewbird Settings', 'reviewbird' ),
			__( 'reviewbird', 'reviewbird' ),
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

		wp_set_script_translations(
			'reviewbird-admin',
			'reviewbird',
			REVIEWBIRD_PLUGIN_DIR . 'languages'
		);

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
			'restUrl'          => rest_url( 'reviewbird/v1' ),
			'nonce'            => wp_create_nonce( 'reviewbird_admin_nonce' ),
			'apiUrl'           => reviewbird_get_api_url(),
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'enableSchema'     => reviewbird_is_schema_enabled(),
			'enableWidget'     => reviewbird_is_widget_enabled(),
			'forceReviewsOpen' => reviewbird_is_force_reviews_open(),
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		// Refresh star color cache when admin visits settings page.
		$this->maybe_refresh_star_color();

		?>
		<div class="wrap">
			<div id="reviewbird-settings-root"></div>
		</div>
		<?php
	}

	/**
	 * Refresh the star color cache if needed.
	 *
	 * Fetches from widget config API when transient is expired or missing.
	 */
	private function maybe_refresh_star_color(): void {
		$cached_color = get_transient( 'reviewbird_star_color' );

		// Only fetch if not cached.
		if ( false === $cached_color ) {
			StarRatingDisplay::fetch_and_cache_star_color();
		}
	}

	/**
	 * Verify AJAX request has valid nonce and user permissions.
	 *
	 * Sends JSON error response and terminates if validation fails.
	 */
	private function verify_ajax_request(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'reviewbird_admin_nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token', 'reviewbird' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'reviewbird' ), 403 );
		}
	}

	/**
	 * Check if a POST parameter is set to '1'.
	 *
	 * @param string $key The POST parameter key.
	 * @return bool True if the parameter equals '1'.
	 */
	private function is_post_param_enabled( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax_request().
		return isset( $_POST[ $key ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	/**
	 * Handle AJAX request to update a setting.
	 */
	public function handle_setting_update(): void {
		$this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax_request() above.
		$setting = isset( $_POST['setting'] ) ? sanitize_key( $_POST['setting'] ) : '';

		if ( ! in_array( $setting, self::ALLOWED_SETTINGS, true ) ) {
			wp_send_json_error( __( 'Invalid setting', 'reviewbird' ), 400 );
		}

		$enabled = $this->is_post_param_enabled( 'value' );
		update_option( 'reviewbird_' . $setting, $enabled ? 'yes' : 'no' );

		wp_send_json_success(
			array(
				'setting' => $setting,
				'value'   => $enabled,
				'message' => __( 'Setting updated successfully', 'reviewbird' ),
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
				'message' => __( 'Health check cache cleared', 'reviewbird' ),
			)
		);
	}
}