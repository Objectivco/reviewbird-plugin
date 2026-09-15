<?php
/**
 * Signed Google Customer Reviews page.
 *
 * @package reviewbird
 * @var \WC_Order $order Authorized order.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php esc_html_e( 'Google Customer Reviews', 'reviewbird' ); ?></title>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'reviewbird-gcr-page' ); ?>>
	<?php wp_body_open(); ?>
	<main>
		<?php $this->render_card( $order, 'direct' ); ?>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
