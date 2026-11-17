<?php
/**
 * Server-rendered review tests.
 *
 * @package reviewbird
 */

// phpcs:ignoreFile -- PHPUnit tests use the fake WordPress in bootstrap.php.

use PHPUnit\Framework\TestCase;

final class ServerRenderedReviewsTest extends TestCase {

	public function test_reviews_are_visible_before_the_widget_loads(): void {
		$html = reviewbird_render_ssr_reviews(
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
			'<div class="reviewbird-ssr-reviews">',
			$html
		);
		self::assertStringContainsString( 'Ada', $html );
		self::assertStringContainsString( 'Fast and useful.', $html );
	}
}
