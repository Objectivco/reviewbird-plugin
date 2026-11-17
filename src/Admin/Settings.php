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

use Pressmodo\AdminNotices\Notices;
use reviewbird\Integration\GoogleCustomerReviews;

/**
 * Admin settings page.
 */
class Settings {

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
	 * Review request notices.
	 *
	 * @var Notices|null
	 */
	private $review_notices;

	/**
	 * Initialize admin hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_reviewbird_update_setting', array( $this, 'handle_setting_update' ) );
		add_action( 'wp_ajax_reviewbird_get_health_status', array( $this, 'handle_get_health_status' ) );
		add_action( 'admin_post_' . self::REGISTRATION_INTENT_ACTION, array( $this, 'handle_registration_intent' ) );
		add_action( 'admin_init', array( $this, 'register_review_notice' ) );
	}

	/**
	 * Register a review request after 14 days of connected use.
	 */
	public function register_review_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || ! reviewbird_is_store_connected() ) {
			return;
		}

		$started_at = (int) get_option( 'reviewbird_review_notice_started_at' );
		if ( ! $started_at ) {
			add_option( 'reviewbird_review_notice_started_at', time(), '', false );
			return;
		}

		if ( time() < $started_at + 14 * DAY_IN_SECONDS ) {
			return;
		}

		ob_start();
		?>
		<p><?php esc_html_e( 'Hey, there! It looks like you enjoy collecting product reviews with Reviewbird. Would you do us a favor and take a few seconds to give us a review? We’d love to hear from you.', 'reviewbird' ); ?></p>
		<p>
			<a href="https://wordpress.org/support/plugin/reviewbird/reviews/#new-post" class="reviewbird-notice-dismiss" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ok, you deserve it', 'reviewbird' ); ?></a>
			<br>
			<a href="#" class="reviewbird-notice-dismiss"><?php esc_html_e( 'Nope, maybe later', 'reviewbird' ); ?></a>
			<br>
			<a href="#" class="reviewbird-notice-dismiss"><?php esc_html_e( 'I already did', 'reviewbird' ); ?></a>
		</p>
		<?php
		$this->review_notices = new Notices();
		$this->review_notices->add(
			'reviewbird_review',
			false,
			ob_get_clean(),
			array(
				'capability' => 'manage_options',
				'screens'    => array( 'dashboard', 'plugins', 'toplevel_page_reviewbird-get-started', 'reviewbird_page_reviewbird-settings' ),
				'image'      => false,
			)
		);
		$this->review_notices->boot();
	}

	/**
	 * Restore the review request on the Get Started page.
	 */
	public function display_review_notice(): void {
		if ( $this->review_notices ) {
			$this->review_notices->the_notices();
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		// phpcs:ignore Generic.Files.LineLength.TooLong, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress menu icons accept a base64-encoded SVG.
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52"><path fill="black" fill-rule="evenodd" clip-rule="evenodd" d="M3 33.05c5.53-2.32 11.05-6.74 16.8-12.6 3.65-5.2 8.07-7.69 13.6-8.29 3.87.22 7.74 1.99 11.44 5.58 2.32.48 4.59 1.12 6.8 1.93-1.66 1.4-3.28 2.62-4.87 3.65-.94 2.16-1.77 4.37-2.21 6.36-.94 3.81-5.09 8.23-10.17 10.22-4.86 1.77-9.84 2.05-14.59 1.44C11.85 40.12 5.49 36.81 3 33.05Zm30.4-15.59 1.38 4.24h4.46l-3.61 2.62L37 28.56l-3.6-2.62-3.6 2.62 1.37-4.24-3.61-2.62h4.46l1.38-4.24Z"/></svg>' );

		$get_started_hook = add_menu_page(
			__( 'Reviewbird', 'reviewbird' ),
			__( 'Reviewbird', 'reviewbird' ),
			'manage_options',
			self::GET_STARTED_SLUG,
			array( $this, 'render_page' ),
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
			array( $this, 'render_page' )
		);

		add_submenu_page(
			self::GET_STARTED_SLUG,
			__( 'Reviewbird Settings', 'reviewbird' ),
			__( 'Settings', 'reviewbird' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Suppress unrelated notices on the Get Started page.
	 */
	public function suppress_admin_notices(): void {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		add_action( 'admin_notices', array( $this, 'display_review_notice' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		$review_notice = $this->review_notices ? $this->review_notices->get( 'reviewbird_review' ) : null;
		if ( $review_notice && $review_notice->show() ) {
			wp_enqueue_script( 'jquery' );
			wp_add_inline_script(
				'jquery-core',
				<<<'JS'
jQuery(function($) {
	$(document).on('click', '#pressmodo-notice-reviewbird_review .reviewbird-notice-dismiss', function(event) {
		if (this.getAttribute('href') === '#') {
			event.preventDefault();
		}
		$(this).closest('.notice').find('button.notice-dismiss').trigger('click');
	});
});
JS
			);
		}

		if ( ! str_contains( $hook, self::GET_STARTED_SLUG ) && ! str_contains( $hook, self::SETTINGS_SLUG ) ) {
			return;
		}

		reviewbird_enqueue_build( 'admin' );
		wp_localize_script( 'reviewbird-admin', 'reviewbirdAdmin', $this->get_script_localization_data( $hook ) );

		wp_set_script_translations( 'reviewbird-admin', 'reviewbird', REVIEWBIRD_PLUGIN_DIR . 'languages' );

		wp_add_inline_script(
			'reviewbird-admin',
			<<<'JS'
(function (window, document, beacon) {
	function loadBeaconScript() {
		var script = document.createElement('script');
		script.async = true;
		script.src = 'https://beacon-v2.helpscout.net';
		document.head.appendChild(script);
	}
	window.Beacon = beacon = function (method, options, data) {
		window.Beacon.readyQueue.push({ method: method, options: options, data: data });
	};
	beacon.readyQueue = [];
	if ('complete' === document.readyState) {
		loadBeaconScript();
	} else {
		window.addEventListener('load', loadBeaconScript, false);
	}
}(window, document, window.Beacon || function () {}));
window.Beacon('init', '7486def8-6289-41b2-961f-81c68743d2b5');
JS
		);
	}

	/**
	 * Get localization data for the admin script.
	 *
	 * @param string $hook The current admin page hook.
	 * @return array<string, mixed>
	 */
	private function get_script_localization_data( string $hook ): array {
		return array(
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
		$integrations_url = reviewbird_get_api_url() . ( $store_id && $org_path ? $org_path . '/stores/' . $store_id . '/integrations' : '/dashboard' );

		$feature_status = GoogleCustomerReviews::status();

		return array(
			'enabled'         => 'enabled' === $feature_status,
			'status'          => $feature_status,
			'integrationsUrl' => $integrations_url,
		);
	}

	/**
	 * Render the admin app root. The script picks the screen from pageType.
	 */
	public function render_page(): void {
		echo '<div class="wrap"><div id="reviewbird-settings-root"></div></div>';
	}

	/**
	 * Verify AJAX request has valid nonce and user permissions.
	 *
	 * Sends JSON error response and terminates if validation fails.
	 */
	private function verify_ajax_request(): void {
		if ( ! check_ajax_referer( 'reviewbird_admin_nonce', 'nonce', false ) ) {
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

		if ( ! in_array( $setting, array( 'enable_schema', 'enable_widget', 'force_reviews_open' ), true ) ) {
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
	 * Return the admin connection status with a five-minute cache. A refresh request bypasses it.
	 */
	public function handle_get_health_status(): void {
		$this->verify_ajax_request();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_ajax_request() above.
		$status = empty( $_POST['refresh'] ) ? get_transient( 'reviewbird_admin_health_status' ) : false;
		if ( false === $status ) {
			$status = reviewbird_refresh_health_status();
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
			'/api/registration-intents',
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
