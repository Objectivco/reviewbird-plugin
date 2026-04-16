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
	 * Help Scout beacon ID for Reviewbird admin screens.
	 */
	private const HELP_SCOUT_BEACON_ID = '7486def8-6289-41b2-961f-81c68743d2b5';

	/**
	 * Menu slug for the Reviewbird get started page.
	 */
	private const GET_STARTED_SLUG = 'reviewbird-get-started';

	/**
	 * Menu slug for the Reviewbird settings page.
	 */
	private const SETTINGS_SLUG = 'reviewbird-settings';

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
		if ( ! $screen || ! $this->is_reviewbird_screen( $screen->id ) ) {
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
				<strong><?php esc_html_e( 'Reviewbird Connection Error:', 'reviewbird' ); ?></strong>
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
			<p><?php esc_html_e( 'Successfully connected to Reviewbird!', 'reviewbird' ); ?></p>
		</div>
		<?php
		delete_transient( 'reviewbird_oauth_success' );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		// phpcs:ignore Generic.Files.LineLength.TooLong -- Base64-encoded SVG icon.
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill="black" d="M30.095,61.352c0,0-5.532-4.574-8.192-5.532c0,0-1.384-0.266-2.075-1.276c0,0-0.479-0.532-0.958-0.691c0,0-0.958-0.426-1.117-0.745c0,0-0.266-0.213-0.691-0.479c0,0-1.383-0.798-1.968-2.182c0,0-0.213-0.851-1.011-1.011c0,0-1.01-0.372-1.436-0.904c0,0-0.691-0.744-1.117-1.063c0,0-3.99-4.043-5.16-11.81c0,0-0.531-4.096-0.319-5.372c0,0-0.106-1.011,0.745,0c0,0,1.968,2.873,2.5,4.042c0,0-1.436-8.192-1.223-10.213c0,0,0.106-1.117,1.276,0.16c0,0,2.128,1.968,3.671,7.607c0,0-1.064-6.756-0.852-8.564c0,0,0.212-1.596,1.596,0c0,0,1.755,1.809,2.606,4.416c0,0,1.331,4.042,1.596,6.437c0,0-0.106-6.49,0.532-6.863c0,0,1.277-0.319,2.606,1.596c0,0,1.862,2.766,1.383,9.469c0,0,1.648-5.107,1.862-7.075c0,0,0.692-2.021,2.288-0.585c0,0,1.702,1.543,1.542,4.31c0,0-0.372,3.138-0.639,3.883c0,0,2.341-2.66,3.086-4.043c0,0,1.489-1.33,2.447-0.638c0,0,1.542,0.585,0.957,2.926l-0.532,2.075c0,0,2.234-1.543,3.352-0.691c0,0,1.862,0.958,1.702,3.032c0,0,3.086,1.063,3.246,3.032c0,0,2.34,0.638,2.18,2.34l0.426-0.053c0,0,0.426-3.192,2.447-4.15c0,0,2.872-2.553,3.937-3.67c0,0,2.234-2.394,2.819-1.862c0,0,3.139-4.042,4.788-11.862c0,0,0.851-4.096,1.543-3.777c0,0,0.372-0.054,0.531,1.383c0,0,0.319,6.064-3.351,11.118c0,0,5.266-5,8.139-15.161c0,0,1.17-2.873,1.542-1.33c0,0,1.438,5.213-5.053,13.406c0,0,5.372-4.841,8.511-11.118c0,0,2.128-3.458,2.554-2.447c0,0,0.904,4.468-7.501,12.5c0,0,6.49-4.469,10.161-9.309c0,0,1.914-2.128,1.329,0.212c0,0-2.767,5.001-7.234,7.927c0,0-0.638,0.479-0.638,1.223c0,0,8.884-5.639,11.809-7.979c0,0,2.182-1.596,0.958,0.373c0,0-3.937,4.468-6.224,5.692c0,0-1.649,1.118-1.597,1.968c0,0,8.405-3.99,9.575-4.415c0,0,0.958-0.319,0.213,0.745c0,0-3.671,3.138-5.479,3.458c0,0-1.648,0.638-0.798,0.691c0,0,2.394-0.16,3.778-0.905c0,0,2.499-1.383,2.394,0.16c0,0-0.639,1.649-2.022,2.341c0,0,1.968,1.755-0.105,3.138c0,0,1.276,1.49-0.479,2.234c0,0,1.703,1.17,0.585,1.595c0,0-0.638,0.585-1.596,0.532c0,0,0.852,0.852,0.16,1.064c0,0,1.276,0.639,0.105,1.33c0,0,1.862,1.277-0.319,2.075c0,0,1.171,1.065-0.744,1.968c0,0,1.17,1.171-0.426,2.021c0,0,0.373,1.437-0.372,1.702c0,0,0.372,1.489-0.479,1.596c0,0,0.319,1.544-0.851,1.49c0,0-0.214,0.904-0.692,0.904c0,0,0.426,1.542-0.957,1.596c0,0-0.479,1.649-1.224,1.756c0,0-0.106,0.904-0.799,1.011c0,0-0.053,1.542-1.329,1.755c0,0-0.266,1.117-0.851,1.171c0,0,0.053,0.584-0.426,0.903c0,0,1.809,2.128-0.799,2.714v0.372c0,0,3.511,1.276,4.628,1.276c0,0,8.564,0.373,10.64-0.266c0,0,5.16-1.064,3.725,0.798c0,0,3.67,0.745,2.233,1.861c0,0,3.191,0.905,1.543,1.756l-0.106,0.32c0,0,4.043,1.595,1.861,2.073c0,0,1.489,0.852,0.267,1.224c0,0,0.373,1.383-1.915,1.33c0,0-2.182,0.213-5.32-0.585c0,0-1.648,0.213-3.511-0.531c0,0-1.383,0.318-3.457-0.426c0,0-1.81-0.373-3.086-0.213c0,0-1.809,1.276-2.447,1.808c0,0-1.437,0.958-3.457,0.532c0,0-1.862-0.213-2.606-0.904c0,0-1.117-0.69-1.703-0.797l0.426,0.638c0,0-1.117-0.266-2.341-0.054l0.267,0.426c0,0-1.012-0.16-2.713,0.106c0,0-3.618,0.319-5.16,0.531c0,0-8.618,0.692-16.065-2.073c0,0-5.32-2.448-8.831-2.979c0,0-8.618-0.106-12.661-2.606c0,0-3.032,0.159-3.617,0.106c0,0-0.106,0.425,0.16,0.744c0,0-1.809-1.117-0.106-3.511c0,0,0.638-0.851,2.713-1.116c0,0,1.543-1.863,4.416-1.97C23.711,62.521,28.872,62.416,30.095,61.352z"/></svg>' );

		add_menu_page(
			__( 'Reviewbird', 'reviewbird' ),
			__( 'Reviewbird', 'reviewbird' ),
			'manage_options',
			self::GET_STARTED_SLUG,
			array( $this, 'render_get_started_page' ),
			$icon_svg,
			58
		);

		add_submenu_page(
			self::GET_STARTED_SLUG,
			__( 'Get Started', 'reviewbird' ),
			__( 'Get Started', 'reviewbird' ),
			'manage_options',
			self::GET_STARTED_SLUG,
			array( $this, 'render_get_started_page' )
		);

		add_submenu_page(
			self::GET_STARTED_SLUG,
			__( 'Reviewbird Settings', 'reviewbird' ),
			__( 'Settings', 'reviewbird' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( ! $this->is_reviewbird_screen( $hook ) ) {
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

		wp_localize_script( 'reviewbird-admin', 'reviewbirdAdmin', $this->get_script_localization_data( $hook ) );

		wp_set_script_translations(
			'reviewbird-admin',
			'reviewbird',
			REVIEWBIRD_PLUGIN_DIR . 'languages'
		);

		$this->enqueue_help_scout_beacon();

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
	 * @param string $hook The current admin page hook.
	 * @return array<string, mixed>
	 */
	private function get_script_localization_data( string $hook ): array {
		return array(
			'restUrl'          => rest_url( 'reviewbird/v1' ),
			'nonce'            => wp_create_nonce( 'reviewbird_admin_nonce' ),
			'apiUrl'           => reviewbird_get_api_url(),
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'pageType'         => false !== strpos( $hook, self::SETTINGS_SLUG ) ? 'settings' : 'get_started',
			'registerUrl'      => reviewbird_get_api_url() . '/register',
			'dashboardUrl'     => 'https://app.reviewbird.com/dashboard',
			'settingsUrl'      => admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ),
			'onboardingVideo'  => 'https://customer-4hlalxgkzo6kqqua.cloudflarestream.com/3bfe46e1308b101d1dc94982920a7b1b/iframe?autoplay=true&muted=true&loop=true',
			'enableSchema'     => reviewbird_is_schema_enabled(),
			'enableWidget'     => reviewbird_is_widget_enabled(),
			'forceReviewsOpen' => reviewbird_is_force_reviews_open(),
		);
	}

	/**
	 * Load the Help Scout beacon on Reviewbird admin screens.
	 */
	private function enqueue_help_scout_beacon(): void {
		$beacon_id = wp_json_encode( self::HELP_SCOUT_BEACON_ID );
		$script    = <<<JS
(function(window, document, beacon) {
	function loadBeaconScript() {
		var firstScript = document.getElementsByTagName('script')[0];
		var script = document.createElement('script');

		script.type = 'text/javascript';
		script.async = true;
		script.src = 'https://beacon-v2.helpscout.net';

		firstScript.parentNode.insertBefore(script, firstScript);
	}

	window.Beacon = beacon = function(method, options, data) {
		window.Beacon.readyQueue.push({
			method: method,
			options: options,
			data: data
		});
	};

	beacon.readyQueue = [];

	if ('complete' === document.readyState) {
		loadBeaconScript();
		return;
	}

	if (window.attachEvent) {
		window.attachEvent('onload', loadBeaconScript);
		return;
	}

	window.addEventListener('load', loadBeaconScript, false);
}(window, document, window.Beacon || function() {}));

window.Beacon('init', {$beacon_id});
JS;

		wp_add_inline_script( 'reviewbird-admin', $script, 'after' );
	}

	/**
	 * Render the get started page.
	 */
	public function render_get_started_page(): void {
		?>
		<div class="wrap">
			<div id="reviewbird-settings-root"></div>
		</div>
		<?php
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
	 * Determine if the given hook suffix belongs to a Reviewbird admin screen.
	 *
	 * @param string $hook_suffix Screen hook suffix.
	 * @return bool
	 */
	private function is_reviewbird_screen( string $hook_suffix ): bool {
		return false !== strpos( $hook_suffix, self::GET_STARTED_SLUG )
			|| false !== strpos( $hook_suffix, self::SETTINGS_SLUG );
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
