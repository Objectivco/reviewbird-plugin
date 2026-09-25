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
use reviewbird\Integration\GoogleCustomerReviews;
use reviewbird\Integration\HealthScheduler;

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
	 * Admin action that starts Reviewbird registration.
	 */
	private const REGISTRATION_INTENT_ACTION = 'reviewbird_create_registration_intent';

	/**
	 * Reviewbird endpoint that creates a short-lived registration intent.
	 */
	private const REGISTRATION_INTENT_ENDPOINT = '/api/registration-intents';

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
		add_action( 'wp_ajax_reviewbird_get_health_status', array( $this, 'handle_get_health_status' ) );
		add_action( 'wp_ajax_reviewbird_clear_health_cache', array( $this, 'handle_clear_health_cache' ) );
		add_action( 'admin_post_' . self::REGISTRATION_INTENT_ACTION, array( $this, 'handle_registration_intent' ) );
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
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><path fill="black" fill-rule="evenodd" clip-rule="evenodd" d="M3 33.05c5.53-2.32 11.05-6.74 16.8-12.6 3.65-5.2 8.07-7.69 13.6-8.29 3.87.22 7.74 1.99 11.44 5.58 2.32.48 4.59 1.12 6.8 1.93-1.66 1.4-3.28 2.62-4.87 3.65-.94 2.16-1.77 4.37-2.21 6.36-.94 3.81-5.09 8.23-10.17 10.22-4.86 1.77-9.84 2.05-14.59 1.44C11.85 40.12 5.49 36.81 3 33.05Zm30.4-15.59 1.38 4.24h4.46l-3.61 2.62L37 28.56l-3.6-2.62-3.6 2.62 1.37-4.24-3.61-2.62h4.46l1.38-4.24Z"/></svg>' );

		$get_started_hook = add_menu_page(
			__( 'Reviewbird', 'reviewbird' ),
			__( 'Reviewbird', 'reviewbird' ),
			'manage_options',
			self::GET_STARTED_SLUG,
			array( $this, 'render_get_started_page' ),
			$icon_svg,
			58
		);

		add_action( 'load-' . $get_started_hook, array( $this, 'suppress_admin_notices' ) );

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
	 * Suppress unrelated notices on the Get Started page.
	 */
	public function suppress_admin_notices(): void {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		add_action( 'admin_notices', array( $this, 'display_oauth_notices' ) );
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

		$asset_file = REVIEWBIRD_PLUGIN_DIR . 'assets/build/admin.asset.php';
		$asset_data = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => array(),
			'version'      => REVIEWBIRD_VERSION,
		);

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
		wp_style_add_data( 'reviewbird-admin', 'rtl', 'replace' );
	}

	/**
	 * Get localization data for the admin script.
	 *
	 * @param string $hook The current admin page hook.
	 * @return array<string, mixed>
	 */
	private function get_script_localization_data( string $hook ): array {
		return array(
			'restUrl'               => rest_url( 'reviewbird/v1' ),
			'nonce'                 => wp_create_nonce( 'reviewbird_admin_nonce' ),
			'apiUrl'                => reviewbird_get_api_url(),
			'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
			'pageType'              => false !== strpos( $hook, self::SETTINGS_SLUG ) ? 'settings' : 'get_started',
			'registerUrl'           => add_query_arg(
				array(
					'action'   => self::REGISTRATION_INTENT_ACTION,
					'_wpnonce' => wp_create_nonce( self::REGISTRATION_INTENT_ACTION ),
				),
				admin_url( 'admin-post.php' )
			),
			'dashboardUrl'          => reviewbird_get_api_url() . '/dashboard',
			'settingsUrl'           => admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ),
			'siteDomain'            => wp_parse_url( home_url(), PHP_URL_HOST ),
			'locale'                => str_replace( '_', '-', implode( '_', array_slice( explode( '_', get_user_locale() ), 0, 2 ) ) ),
			'enableSchema'          => reviewbird_is_schema_enabled(),
			'enableWidget'          => reviewbird_is_widget_enabled(),
			'forceReviewsOpen'      => reviewbird_is_force_reviews_open(),
			'googleCustomerReviews' => $this->get_gcr_settings_data(),
		);
	}

	/**
	 * Get the same GCR settings snapshot for page loads and manual refreshes.
	 *
	 * @return array GCR integration state.
	 */
	private function get_gcr_settings_data(): array {
		$status           = reviewbird_get_store_status() ?? array();
		$store_id         = reviewbird_get_store_id();
		$org_path         = empty( $status['org_slug'] ) ? '' : '/' . rawurlencode( $status['org_slug'] );
		$integrations_url = reviewbird_get_api_url() . $org_path . '/stores' . ( $store_id ? '/' . $store_id . '/integrations' : '' );

		$feature_status = GoogleCustomerReviews::status();

		return array(
			'enabled'         => 'enabled' === $feature_status,
			'status'          => $feature_status,
			'integrationsUrl' => $integrations_url,
		);
	}

	/**
	 * Load the Help Scout beacon on Reviewbird admin screens.
	 */
	private function enqueue_help_scout_beacon(): void {
		$beacon_id = wp_json_encode( self::HELP_SCOUT_BEACON_ID );
		$script    = implode(
			"\n",
			array(
				'(function(window, document, beacon) {',
				"\tfunction loadBeaconScript() {",
				"\t\tvar firstScript = document.getElementsByTagName('script')[0];",
				"\t\tvar script = document.createElement('script');",
				'',
				"\t\tscript.type = 'text/javascript';",
				"\t\tscript.async = true;",
				"\t\tscript.src = 'https://beacon-v2.helpscout.net';",
				'',
				"\t\tfirstScript.parentNode.insertBefore(script, firstScript);",
				"\t}",
				'',
				"\twindow.Beacon = beacon = function(method, options, data) {",
				"\t\twindow.Beacon.readyQueue.push({",
				"\t\t\tmethod: method,",
				"\t\t\toptions: options,",
				"\t\t\tdata: data",
				"\t\t});",
				"\t};",
				'',
				"\tbeacon.readyQueue = [];",
				'',
				"\tif ('complete' === document.readyState) {",
				"\t\tloadBeaconScript();",
				"\t\treturn;",
				"\t}",
				'',
				"\tif (window.attachEvent) {",
				"\t\twindow.attachEvent('onload', loadBeaconScript);",
				"\t\treturn;",
				"\t}",
				'',
				"\twindow.addEventListener('load', loadBeaconScript, false);",
				'}(window, document, window.Beacon || function() {}));',
				'',
				"window.Beacon('init', {$beacon_id});",
			)
		);

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
		if ( false === get_transient( 'reviewbird_star_color' ) ) {
			StarRatingDisplay::fetch_and_cache_star_color();
		}

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
	 * Verify AJAX request has valid nonce and user permissions.
	 *
	 * Sends JSON error response and terminates if validation fails.
	 */
	private function verify_ajax_request(): void {
		if ( ! isset( $_POST['nonce'] ) || ! is_string( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'reviewbird_admin_nonce' ) ) {
			wp_send_json_error( __( 'Invalid security token', 'reviewbird' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'reviewbird' ), 403 );
		}
	}

	/**
	 * Handle AJAX request to update a setting.
	 */
	public function handle_setting_update(): void {
		$this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax_request() above.
		$setting = isset( $_POST['setting'] ) && is_string( $_POST['setting'] ) ? sanitize_key( wp_unslash( $_POST['setting'] ) ) : '';

		if ( ! in_array( $setting, self::ALLOWED_SETTINGS, true ) ) {
			wp_send_json_error( __( 'Invalid setting', 'reviewbird' ), 400 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$value = isset( $_POST['value'] ) && is_string( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : null;
		if ( ! in_array( $value, array( '0', '1' ), true ) ) {
			wp_send_json_error( __( 'Invalid setting value', 'reviewbird' ), 400 );
		}

		$enabled = '1' === $value;
		$stored  = $enabled ? 'yes' : 'no';
		$saved   = update_option( 'reviewbird_' . $setting, $stored );
		if ( ! $saved && get_option( 'reviewbird_' . $setting ) !== $stored ) {
			wp_send_json_error( __( 'The setting could not be saved. Please try again.', 'reviewbird' ), 500 );
		}

		wp_send_json_success(
			array(
				'setting' => $setting,
				'value'   => $enabled,
				'message' => __( 'Setting updated successfully', 'reviewbird' ),
			)
		);
	}

	/**
	 * Refresh the saved connection status without discarding the last good value.
	 */
	public function handle_clear_health_cache(): void {
		$this->handle_get_health_status( true );
	}

	/**
	 * Return the admin connection status with a five-minute cache.
	 *
	 * @param bool $force_refresh Whether to bypass the transient cache.
	 */
	public function handle_get_health_status( bool $force_refresh = false ): void {
		$this->verify_ajax_request();

		$status = $force_refresh ? false : get_transient( 'reviewbird_admin_health_status' );
		if ( false === $status ) {
			$status = ( new HealthScheduler() )->refresh_health_status();
		}
		if ( is_wp_error( $status ) ) {
			wp_send_json_error( __( 'The connection status could not be refreshed. Please try again.', 'reviewbird' ), 502 );
		}

		wp_send_json_success(
			array(
				'status'                => $status,
				'googleCustomerReviews' => $this->get_gcr_settings_data(),
				'message'               => __( 'Connection status refreshed', 'reviewbird' ),
			)
		);
	}

	/**
	 * Create a registration intent and send the current user to Reviewbird.
	 */
	public function handle_registration_intent(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'reviewbird' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::REGISTRATION_INTENT_ACTION );

		$current_user = wp_get_current_user();
		$response     = reviewbird_api_request(
			self::REGISTRATION_INTENT_ENDPOINT,
			array(
				'email'      => sanitize_email( $current_user->user_email ),
				'name'       => sanitize_text_field( $current_user->display_name ),
				'store_name' => sanitize_text_field( get_bloginfo( 'name' ) ),
				'store_url'  => esc_url_raw( home_url( '/' ) ),
				'source'     => 'woocommerce_plugin',
			),
			'POST'
		);

		$token            = is_array( $response ) && isset( $response['token'] ) ? (string) $response['token'] : '';
		$registration_url = reviewbird_get_api_url() . '/register';

		if ( preg_match( '/\A[A-Za-z0-9_-]{32,128}\z/', $token ) ) {
			$registration_url = reviewbird_get_api_url() . '/register/woocommerce/' . $token;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Target is the configured Reviewbird URL.
		wp_redirect( $registration_url );
		exit;
	}
}
