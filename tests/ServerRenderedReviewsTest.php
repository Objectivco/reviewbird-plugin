<?php
/**
 * Server-rendered review tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- This isolated test defines small WordPress function stubs.

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ );
	}

	function wp_strip_all_tags( $text ) {
		return strip_tags( $text );
	}

	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	require_once dirname( __DIR__ ) . '/src/functions.php';
}

namespace reviewbird\Tests {

	use PHPUnit\Framework\TestCase;

	final class ServerRenderedReviewsTest extends TestCase {

		public function test_reviews_remain_in_html_without_painting(): void {
			$html = \reviewbird_render_ssr_reviews(
				array(
					'reviews' => array(
						array(
							'rating' => 5,
							'author' => array( 'name' => 'Ada' ),
							'title'  => 'Excellent',
							'body'   => 'Fast and useful.',
						),
					),
				)
			);

			self::assertStringStartsWith(
				'<div class="reviewbird-ssr-reviews" hidden style="display:none">',
				$html
			);
			self::assertStringContainsString( 'Ada', $html );
			self::assertStringContainsString( 'Fast and useful.', $html );
		}
	}
}
