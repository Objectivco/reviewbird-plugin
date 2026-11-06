<?php
/**
 * Run: php tests/ReviewNoticeCheck.php
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- Small WordPress stubs for this standalone check.

namespace reviewbird\Admin {
	function time() { return $GLOBALS['notice_time']; }
}

namespace {
	define( 'ABSPATH', __DIR__ );
	define( 'DAY_IN_SECONDS', 86400 );

	$options = array();
	$actions = array();
	$admin = true;
	$network = false;
	$connected = false;
	$notice_time = 1000000;
	$screen = (object) array( 'id' => 'dashboard' );
	$inline_scripts = array();

	function add_action( $hook, $callback ) { $GLOBALS['actions'][ $hook ][] = $callback; }
	function remove_all_actions( $hook ) { unset( $GLOBALS['actions'][ $hook ] ); }
	function do_action( $hook ) {
		foreach ( $GLOBALS['actions'][ $hook ] ?? array() as $callback ) { $callback(); }
	}
	function current_user_can( $capability ) { return $GLOBALS['admin'] && 'manage_options' === $capability; }
	function is_network_admin() { return $GLOBALS['network']; }
	function reviewbird_is_store_connected() { return $GLOBALS['connected']; }
	function get_option( $key ) { return $GLOBALS['options'][ $key ] ?? false; }
	function get_transient( $key ) { return false; }
	function update_option( $key, $value ) { $GLOBALS['options'][ $key ] = $value; return true; }
	function add_option( $key, $value ) { return ! isset( $GLOBALS['options'][ $key ] ) && update_option( $key, $value ); }
	function get_current_screen() { return $GLOBALS['screen']; }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $value ) ); }
	function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
	function esc_html_e( $text ) { echo htmlspecialchars( $text, ENT_QUOTES ); }
	function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
	function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
	function esc_url( $url ) { return htmlspecialchars( $url, ENT_QUOTES ); }
	function wp_kses_post( $html ) { return $html; }
	function wpautop( $html ) { return $html; }
	function wp_create_nonce( $action ) { return 'valid-' . $action; }
	function check_ajax_referer( $action, $field ) {
		if ( ( $_POST[ $field ] ?? null ) !== wp_create_nonce( $action ) ) { throw new \RuntimeException( 'Invalid nonce' ); }
	}
	function admin_url( $path ) { return 'https://store.test/wp-admin/' . $path; }
	function wp_enqueue_script( $handle ) { $GLOBALS['scripts'][] = $handle; }
	function wp_add_inline_script( $handle, $script ) { $GLOBALS['inline_scripts'][ $handle ] = $script; }

	require dirname( __DIR__ ) . '/vendor/autoload.php';

	function check( $condition, $message ) {
		if ( ! $condition ) { throw new \RuntimeException( $message ); }
	}
	function boot_notice() {
		$GLOBALS['actions'] = array();
		$settings = new \reviewbird\Admin\Settings();
		do_action( 'admin_init' );
		return $settings;
	}
	function render_notice() {
		ob_start();
		do_action( 'admin_notices' );
		return ob_get_clean();
	}

	// The library must not start the timer until the store is connected.
	boot_notice();
	check( array() === $options, 'The timer started before connection.' );
	$connected = true;
	boot_notice();
	check( 1000000 === $options['reviewbird_review_notice_started_at'], 'The connection timer was not saved.' );
	check( '' === render_notice(), 'A new store received a review request.' );
	$notice_time += 14 * DAY_IN_SECONDS - 1;
	boot_notice();
	check( '' === render_notice(), 'The notice appeared before 14 days.' );
	++$notice_time;
	$settings = boot_notice();
	foreach ( array( 'dashboard', 'plugins', 'toplevel_page_reviewbird-get-started', 'reviewbird_page_reviewbird-settings' ) as $id ) {
		$screen->id = $id;
		$html = render_notice();
		check( false !== strpos( $html, 'Hey, there! It looks like you enjoy collecting product reviews with Reviewbird.' ), 'The sample wording was not used.' );
		check( 3 === substr_count( $html, 'class="reviewbird-notice-dismiss"' ), 'All three choices must dismiss the notice.' );
		check( false !== strpos( $html, 'Ok, you deserve it' ) && false !== strpos( $html, 'Nope, maybe later' ) && false !== strpos( $html, 'I already did' ), 'A sample choice is missing.' );
		check( false !== strpos( $html, 'is-dismissible' ), 'The native close button is missing.' );
		check( false !== strpos( $html, 'target="_blank" rel="noopener noreferrer"' ), 'The review must open safely in a new tab.' );
		check( false === strpos( $html, '5-star' ), 'The notice must not request a rating.' );
	}
	$screen->id = 'edit-product';
	check( '' === render_notice(), 'The notice appeared on an unrelated screen.' );
	$screen->id = 'toplevel_page_reviewbird-get-started';
	$settings->suppress_admin_notices();
	check( false !== strpos( render_notice(), 'reviewbird-notice-dismiss' ), 'Get Started suppressed the review notice.' );

	$screen->id = 'dashboard';
	$settings->enqueue_scripts( 'index.php' );
	check( in_array( 'jquery', $GLOBALS['scripts'], true ), 'The library requires jQuery.' );
	check( isset( $inline_scripts['jquery-core'] ), 'The review links have no dismissal handler.' );
	ob_start();
	do_action( 'admin_footer' );
	$footer = ob_get_clean();
	check( false !== strpos( $footer, 'pressmodo_dismiss_notice' ), 'The library dismissal script is missing.' );

	// AJAX runs without a screen. The dismissal handler must still be registered.
	$screen = null;
	boot_notice();
	$_POST = array( 'action' => 'pressmodo_dismiss_notice', 'id' => 'reviewbird_review', 'nonce' => 'invalid' );
	try {
		do_action( 'wp_ajax_pressmodo_dismiss_notice' );
		throw new \RuntimeException( 'The invalid nonce was accepted.' );
	} catch ( \RuntimeException $error ) {
		check( 'Invalid nonce' === $error->getMessage(), 'Nonce validation failed.' );
	}
	$key = 'pressmodo_notice_dismissed_reviewbird_review';
	check( ! get_option( $key ), 'An invalid request dismissed the notice.' );
	$_POST['nonce'] = wp_create_nonce( 'pressmodo_dismiss_notice_reviewbird_review' );
	$admin = false;
	boot_notice();
	do_action( 'wp_ajax_pressmodo_dismiss_notice' );
	check( ! get_option( $key ), 'A user without permission dismissed the notice.' );
	$admin = true;
	boot_notice();
	do_action( 'wp_ajax_pressmodo_dismiss_notice' );
	check( true === get_option( $key ), 'The library did not save dismissal for the site.' );
	$_POST = array();
	$screen = (object) array( 'id' => 'dashboard' );
	\reviewbird\Core\Activator::activate();
	$notice_time += 30 * DAY_IN_SECONDS;
	boot_notice();
	check( '' === render_notice(), 'The dismissed notice returned after reactivation.' );
	check( 1000000 === $options['reviewbird_review_notice_started_at'], 'Reactivation reset the timer.' );

	echo "Review notice checks passed.\n";
}
