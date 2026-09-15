<?php
/**
 * Google Customer Reviews prompts and order links.
 *
 * @package reviewbird
 */

namespace reviewbird\Integration;

use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Show Google's opt-in after the customer selects Yes.
 */
class GoogleCustomerReviews {
	const YES_META = '_reviewbird_gcr_prompt_yes_at';

	/**
	 * Whether this request already contains a prompt.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Register the common WooCommerce hook and the order link handlers.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_thankyou', array( $this, 'render_prompt' ), 5, 1 );
		add_action( 'cfw_thank_you_content', array( $this, 'render_checkoutwc_prompt' ), 55, 1 );
		add_action( 'template_redirect', array( $this, 'render_link_page' ), 1, 0 );
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 10, 0 );
		add_filter( 'woocommerce_rest_prepare_shop_order_object', array( $this, 'add_order_response' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'render_admin_order' ), 10, 1 );
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
	 * Check the local thank-you prompt switch.
	 *
	 * @return bool Whether the custom prompt is enabled.
	 */
	public static function is_prompt_enabled(): bool {
		return 'yes' === get_option( 'reviewbird_enable_gcr_prompt', 'yes' );
	}

	/**
	 * Default text for the prompt and settings preview.
	 *
	 * @return array Text by field name.
	 */
	public static function prompt_defaults(): array {
		return array(
			'heading'   => __( 'Rate your purchase experience', 'reviewbird' ),
			'message'   => __( 'We use Google Customer Reviews to collect feedback about your purchase. Would you like to receive a short survey after your order arrives?', 'reviewbird' ),
			'yes_label' => __( 'Yes', 'reviewbird' ),
			'no_label'  => __( 'No', 'reviewbird' ),
		);
	}

	/**
	 * Get saved text with safe defaults for missing fields.
	 *
	 * @return array Text by field name.
	 */
	public static function prompt_settings(): array {
		$prompt = self::prompt_defaults();
		$saved  = get_option( 'reviewbird_google_customer_reviews_prompt', array() );

		foreach ( $prompt as $key => $default ) {
			if ( is_array( $saved ) && isset( $saved[ $key ] ) && is_string( $saved[ $key ] ) ) {
				$value          = 'message' === $key ? sanitize_textarea_field( $saved[ $key ] ) : sanitize_text_field( $saved[ $key ] );
				$prompt[ $key ] = '' !== trim( $value ) ? $value : $default;
			}
		}

		return $prompt;
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
	 * Get the guest link. This also works after the customer selected Yes.
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
	 * Show the prompt only inside the customer's order confirmation.
	 *
	 * @param int $order_id Order ID.
	 */
	public function render_prompt( $order_id ): void {
		// CheckoutWC also runs the native hook before its main layout.
		if ( doing_action( 'cfw_thank_you_main_container_start' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		// The core thank-you hook runs after WooCommerce's order access checks.
		// Also verify the URL and owner before adding any customer data.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WooCommerce order key authorizes this read.
		$key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		if ( ! self::is_prompt_enabled() || $this->rendered || ! $order instanceof WC_Order || ! is_order_received_page()
			|| (int) get_query_var( 'order-received' ) !== $order->get_id()
			|| ! hash_equals( $order->get_order_key(), $key )
			|| ( $order->get_customer_id() && $order->get_customer_id() !== get_current_user_id() && ! current_user_can( 'manage_woocommerce' ) )
			|| $order->get_meta( self::YES_META ) || ! self::order_data( $order )
		) {
			return;
		}

		$this->rendered = true;
		$this->enqueue_assets();
		$this->render_card( $order, 'prompt' );
	}

	/**
	 * Show the widget after CheckoutWC's order status section.
	 *
	 * @param WC_Order $order Order.
	 */
	public function render_checkoutwc_prompt( WC_Order $order ): void {
		$this->render_prompt( $order->get_id() );
	}

	/**
	 * Register the guest POST route. The scoped token is the credential.
	 */
	public function register_routes(): void {
		register_rest_route(
			'reviewbird/v1',
			'/google-customer-reviews/(?P<id>\d+)/prompt-yes',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'authorize_prompt_request' ),
				'callback'            => array( $this, 'record_prompt_yes' ),
			)
		);
	}

	/**
	 * Check the token for every Yes request, including repeat requests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error Access result.
	 */
	public function authorize_prompt_request( WP_REST_Request $request ) {
		return self::is_prompt_enabled() && 'POST' === sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && self::authorized_order( $request->get_param( 'id' ), $request->get_param( 'token' ) )
			? true
			: new WP_Error( 'reviewbird_gcr_unavailable', __( 'This review request is not available.', 'reviewbird' ), array( 'status' => 403 ) );
	}

	/**
	 * Record the first Yes to our prompt, not Google's response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error Result.
	 * @throws \RuntimeException On save failure; caught below and returned as WP_Error.
	 */
	public function record_prompt_yes( WP_REST_Request $request ) {
		$order = self::authorized_order( $request->get_param( 'id' ), $request->get_param( 'token' ) );
		if ( ! self::is_prompt_enabled() || 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || ! $order ) {
			return new WP_Error( 'reviewbird_gcr_unavailable', __( 'This review request is not available.', 'reviewbird' ), array( 'status' => 403 ) );
		}

		try {
			$timestamp = $order->get_meta( self::YES_META );
			if ( ! $timestamp ) {
				$timestamp = gmdate( 'c' );
				$order->update_meta_data( self::YES_META, $timestamp );
				// Metadata alone does not advance the legacy order storage sync date.
				$order->set_date_modified( time() );
				if ( ! $order->save() ) {
					throw new \RuntimeException( 'Order save failed.' );
				}
				// WooCommerce can catch save errors internally and still return an ID.
				$order->read_meta_data( true );
				if ( $timestamp !== $order->get_meta( self::YES_META ) ) {
					throw new \RuntimeException( 'Order choice was not saved.' );
				}
			}
		} catch ( \Exception $exception ) {
			return new WP_Error( 'reviewbird_gcr_save_failed', __( 'Your choice could not be saved. Please try again.', 'reviewbird' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'prompt_yes_at' => $timestamp ), 200 );
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
	 * Enqueue the small shared prompt and copy-link bundle.
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
	 * @param string   $mode Prompt or direct.
	 */
	private function render_card( WC_Order $order, string $mode ): void {
		$prompt = self::prompt_settings();
		$config = array(
			'mode'      => $mode,
			'choiceUrl' => rest_url( 'reviewbird/v1/google-customer-reviews/' . $order->get_id() . '/prompt-yes' ),
			'token'     => self::token( $order ),
			'orderId'   => $order->get_id(),
			'google'    => self::order_data( $order ),
			'text'      => array(
				'loading' => __( 'Loading Google Customer Reviews…', 'reviewbird' ),
				'error'   => __( 'Google Customer Reviews could not load. Please try again.', 'reviewbird' ),
				'retry'   => __( 'Try again', 'reviewbird' ),
				'opened'  => __( 'You can close this page when you finish.', 'reviewbird' ),
			),
		);
		?>
		<section class="reviewbird-gcr" data-reviewbird-gcr="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" aria-label="<?php esc_attr_e( 'Google Customer Reviews', 'reviewbird' ); ?>">
			<h2 class="reviewbird-gcr__heading"><?php echo esc_html( 'direct' === $mode ? __( 'Google Customer Reviews', 'reviewbird' ) : $prompt['heading'] ); ?></h2>
			<?php if ( 'prompt' === $mode ) : ?>
				<p class="reviewbird-gcr__message"><?php echo esc_html( $prompt['message'] ); ?></p>
				<div class="reviewbird-gcr__actions">
					<button type="button" data-gcr-yes><?php echo esc_html( $prompt['yes_label'] ); ?></button>
					<button type="button" data-gcr-no><?php echo esc_html( $prompt['no_label'] ); ?></button>
				</div>
			<?php endif; ?>
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
	 * @return WP_REST_Response Response with the link and our prompt timestamp.
	 */
	public function add_order_response( $response, $order ) {
		$timestamp = $order->get_meta( self::YES_META );
		$response->data['reviewbird_google_customer_reviews'] = array(
			'opt_in_url'    => self::opt_in_url( $order ),
			'prompt_yes_at' => $timestamp ? $timestamp : null,
		);
		return $response;
	}

	/**
	 * Show the prompt status and a copyable link on the order edit screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public function render_admin_order( $order ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$url       = self::opt_in_url( $order );
		$timestamp = $order->get_meta( self::YES_META );
		if ( ! $url && ! $timestamp ) {
			return;
		}
		$this->enqueue_assets();
		?>
		<div class="reviewbird-gcr-order">
			<h3><?php esc_html_e( 'Google Customer Reviews', 'reviewbird' ); ?></h3>
			<p>
				<?php
				if ( $timestamp ) {
					// translators: %s: Date when the customer selected Yes on the Reviewbird prompt.
					echo esc_html( sprintf( __( 'Selected Yes on the Reviewbird prompt: %s', 'reviewbird' ), $timestamp ) );
				} else {
					esc_html_e( 'The customer has not selected Yes on the Reviewbird prompt.', 'reviewbird' );
				}
				?>
			</p>
			<?php if ( $url ) : ?>
				<label for="reviewbird-gcr-link"><?php esc_html_e( 'Google opt-in link', 'reviewbird' ); ?></label>
				<input id="reviewbird-gcr-link" type="text" value="<?php echo esc_attr( $url ); ?>" readonly class="widefat" />
				<button type="button" class="button" data-gcr-copy data-gcr-url="<?php echo esc_attr( $url ); ?>" data-gcr-copied="<?php esc_attr_e( 'Link copied.', 'reviewbird' ); ?>" data-gcr-copy-error="<?php esc_attr_e( 'Select and copy the link.', 'reviewbird' ); ?>"><?php esc_html_e( 'Copy link', 'reviewbird' ); ?></button>
				<span data-gcr-copy-status role="status"></span>
			<?php endif; ?>
		</div>
		<?php
	}
}
