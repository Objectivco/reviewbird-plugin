<?php
/**
 * Google Customer Reviews consent and order links.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

use WC_Order;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show Google's opt-in on order confirmations and signed landing pages.
 */
class GoogleCustomerReviews {
	// Keep historical prompt data available to order sync.
	const YES_META = '_reviewbird_gcr_prompt_yes_at';
	const NO_META  = '_reviewbird_gcr_prompt_no_click_ids';

	/**
	 * Whether this request already contains the Google consent module.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Register the common WooCommerce hook and the order link handlers.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_thankyou', array( $this, 'render_consent' ), 5, 1 );
		add_action( 'cfw_thank_you_content', array( $this, 'render_checkoutwc_consent' ), 55, 1 );
		add_action( 'template_redirect', array( $this, 'render_link_page' ), 1, 0 );
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_order_response' ), 10, 2 );
	}

	/**
	 * Read valid settings from the last background health response.
	 *
	 * @return array|null Settings, or null when unavailable.
	 */
	public static function configuration(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview requests must not contact Google.
		if ( isset( $_GET['reviewbird_setup'] ) ) {
			return null;
		}

		$status   = reviewbird_get_store_status();
		$store_id = (int) get_option( 'reviewbird_store_id' );
		$config   = $status['google_customer_reviews'] ?? null;

		if ( ! is_array( $status ) || ! is_array( $config ) || $store_id < 1
			|| (int) ( $status['store_id'] ?? 0 ) !== $store_id
			|| ! in_array( $status['status'] ?? '', array( 'healthy', 'syncing' ), true )
			|| true !== ( $status['has_active_subscription'] ?? false )
			|| true !== ( $config['enabled'] ?? false )
			|| ! is_int( $config['expires_at'] ?? null ) || $config['expires_at'] <= time()
			|| ! is_string( $config['merchant_id'] ?? null )
			|| ! preg_match( '/\A[1-9][0-9]{0,15}\z/', $config['merchant_id'] )
			|| (float) $config['merchant_id'] > 9007199254740991
			|| ! is_int( $config['estimated_delivery_days'] ?? null )
			|| $config['estimated_delivery_days'] < 0 || $config['estimated_delivery_days'] > 365
		) {
			return null;
		}

		return $config;
	}

	/**
	 * Get the current feature state from the cached health response.
	 *
	 * @return string Enabled, disabled, or unknown.
	 */
	public static function status(): string {
		$status = reviewbird_get_store_status();
		if ( 'not_connected' === ( $status['status'] ?? null ) ) {
			return 'disabled';
		}
		$config = $status['google_customer_reviews'] ?? null;
		if ( ! is_array( $config ) || ! is_bool( $config['enabled'] ?? null )
			|| ! is_int( $config['expires_at'] ?? null ) || $config['expires_at'] <= time()
		) {
			return 'unknown';
		}

		return null !== self::configuration() ? 'enabled' : 'disabled';
	}

	/**
	 * Build Google's order data without changing the order.
	 *
	 * @param WC_Order $order Order.
	 * @return array|null Google data, or null for an ineligible order.
	 */
	private static function order_data( WC_Order $order ): ?array {
		$config  = self::configuration();
		$created = $order->get_date_created();
		$country = $order->get_shipping_country();
		if ( ! $country ) {
			$country = $order->get_billing_country();
		}
		$countries = WC()->countries->get_countries();

		if ( ! $config || ! $created || ! $order->get_id() || ! $order->get_order_key()
			|| 'shop_order' !== $order->get_type()
			|| $order->has_status( array( 'failed', 'cancelled', 'refunded', 'draft', 'auto-draft', 'checkout-draft', 'trash' ) )
			|| ! is_email( $order->get_billing_email() ) || ! preg_match( '/\A[A-Z]{2}\z/', $country ) || ! isset( $countries[ $country ] )
		) {
			return null;
		}

		$delivery_date = clone $created;
		$delivery_date->setTimezone( wp_timezone() );
		$delivery_date->modify( '+' . $config['estimated_delivery_days'] . ' days' );

		return array(
			'merchant_id'             => $config['merchant_id'],
			'order_id'                => (string) $order->get_id(),
			'email'                   => $order->get_billing_email(),
			'delivery_country'        => $country,
			'estimated_delivery_date' => $delivery_date->format( 'Y-m-d' ),
		);
	}

	/**
	 * Sign only GCR access, without exposing WooCommerce's order key.
	 *
	 * @param WC_Order $order Order.
	 * @return string Scoped signature.
	 */
	private static function token( WC_Order $order ): string {
		return hash_hmac( 'sha256', 'reviewbird:gcr:' . $order->get_id() . ':' . $order->get_order_key(), wp_salt( 'auth' ) );
	}

	/**
	 * Get the guest link, including after Google was shown at checkout.
	 *
	 * @param WC_Order $order Order.
	 * @return string|null Link, or null when the integration is unavailable.
	 */
	public static function opt_in_url( WC_Order $order ): ?string {
		if ( ! self::order_data( $order ) ) {
			return null;
		}

		return add_query_arg(
			array(
				'reviewbird_gcr_order' => $order->get_id(),
				'token'                => self::token( $order ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Check a guest token and the current order eligibility.
	 *
	 * @param mixed $order_id Order ID.
	 * @param mixed $token Scoped signature.
	 * @return WC_Order|null Authorized order.
	 */
	private static function authorized_order( $order_id, $token ): ?WC_Order {
		if ( ! is_scalar( $order_id ) || ! preg_match( '/\A[1-9][0-9]*\z/', (string) $order_id )
			|| ! is_string( $token ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $token )
		) {
			return null;
		}

		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof WC_Order || ! hash_equals( self::token( $order ), $token ) || ! self::order_data( $order ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Show Google consent only inside the customer's order confirmation.
	 *
	 * @param int $order_id Order ID.
	 */
	public function render_consent( $order_id ): void {
		// CheckoutWC also runs the native hook before its main layout.
		if ( doing_action( 'cfw_thank_you_main_container_start' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		// The core thank-you hook runs after WooCommerce's order access checks.
		// Also verify the URL and owner before adding any customer data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce order key authorizes this read.
		$key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		if ( $this->rendered || ! $order instanceof WC_Order || ! is_order_received_page()
			|| (int) get_query_var( 'order-received' ) !== $order->get_id()
			|| ! hash_equals( $order->get_order_key(), $key )
			|| ( $order->get_customer_id() && $order->get_customer_id() !== get_current_user_id() && ! current_user_can( 'manage_woocommerce' ) )
			|| ! self::order_data( $order )
		) {
			return;
		}

		$this->rendered = true;
		$this->enqueue_assets();
		$this->render_card( $order, 'standard' );
	}

	/**
	 * Load Google consent after CheckoutWC's order status section.
	 *
	 * @param WC_Order $order Order.
	 */
	public function render_checkoutwc_consent( WC_Order $order ): void {
		$this->render_consent( $order->get_id() );
	}

	/**
	 * Render the dedicated signed-link page without recording a choice.
	 */
	public function render_link_page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The scoped signature authorizes this read.
		if ( ! isset( $_GET['reviewbird_gcr_order'] ) ) {
			return;
		}
		$order_id = is_string( $_GET['reviewbird_gcr_order'] ) ? sanitize_text_field( wp_unslash( $_GET['reviewbird_gcr_order'] ) ) : '';
		$token    = isset( $_GET['token'] ) && is_string( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$order    = self::authorized_order( $order_id, $token );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( ! $order ) {
			wp_die( esc_html__( 'This review request is not available.', 'reviewbird' ), esc_html__( 'Google Customer Reviews', 'reviewbird' ), array( 'response' => 404 ) );
		}

		status_header( 200 );
		$this->enqueue_assets();
		require REVIEWBIRD_PLUGIN_DIR . 'templates/google-customer-reviews.php';
		exit;
	}

	/**
	 * Enqueue assets for checkout and the signed-link page.
	 */
	private function enqueue_assets(): void {
		$asset_file = REVIEWBIRD_PLUGIN_DIR . 'assets/build/google-customer-reviews.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : array(
			'dependencies' => array(),
			'version'      => REVIEWBIRD_VERSION,
		);
		wp_enqueue_script( 'reviewbird-google-customer-reviews', REVIEWBIRD_PLUGIN_URL . 'assets/build/google-customer-reviews.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'reviewbird-google-customer-reviews', REVIEWBIRD_PLUGIN_URL . 'assets/build/google-customer-reviews.css', array(), $asset['version'] );
	}

	/**
	 * Render the same card for checkout and the dedicated page.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $mode Standard checkout modal or direct link.
	 */
	private function render_card( WC_Order $order, string $mode ): void {
		$config = array(
			'mode'   => $mode,
			'google' => self::order_data( $order ),
			'text'   => array(
				'loading' => __( 'Loading Google Customer Reviews…', 'reviewbird' ),
				'error'   => __( 'Google Customer Reviews could not load. Please try again.', 'reviewbird' ),
				'retry'   => __( 'Try again', 'reviewbird' ),
				'opened'  => __( 'You can close this page when you finish.', 'reviewbird' ),
			),
		);
		?>
		<section class="reviewbird-gcr" data-reviewbird-gcr="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" aria-label="<?php esc_attr_e( 'Google Customer Reviews', 'reviewbird' ); ?>" <?php echo 'direct' === $mode ? '' : 'hidden'; ?>>
			<h2 class="reviewbird-gcr__heading"><?php esc_html_e( 'Google Customer Reviews', 'reviewbird' ); ?></h2>
			<p data-gcr-status role="status" aria-live="polite"></p>
			<button type="button" data-gcr-retry hidden><?php echo esc_html( $config['text']['retry'] ); ?></button>
			<noscript><p><?php esc_html_e( 'Enable JavaScript to use Google Customer Reviews.', 'reviewbird' ); ?></p></noscript>
		</section>
		<?php
	}

	/**
	 * Add read-only fields to the authenticated WooCommerce order response.
	 *
	 * @param WP_REST_Response $response Order response.
	 * @param WC_Order         $order Order.
	 * @return WP_REST_Response Response with the link and historical prompt data.
	 */
	public function add_order_response( $response, $order ) {
		$timestamp = $order->get_meta( self::YES_META );
		$no_clicks = $order->get_meta( self::NO_META );
		$response->data['reviewbird_google_customer_reviews'] = array(
			'opt_in_url'     => self::opt_in_url( $order ),
			'prompt_yes_at'  => $timestamp ? $timestamp : null,
			'no_click_count' => $no_clicks ? 1 : 0,
		);
		return $response;
	}
}
