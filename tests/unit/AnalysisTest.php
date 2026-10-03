<?php
/**
 * GEO checks per post (meta box, editor panel, audit page).
 *
 * @package Wille_GEO
 */

namespace GEOINS\Tests;

use Brain\Monkey\Functions;
use GEOINS_Analysis;
use WP_Post;

final class AnalysisTest extends TestCase {

	/**
	 * @return array<string,bool> check id => pass
	 */
	private function checks( WP_Post $post ): array {
		$result = GEOINS_Analysis::analyze( $post );
		$this->assertSame( count( GEOINS_Analysis::definitions() ), $result['total'] );
		$this->assertSame( count( array_filter( array_column( $result['checks'], 'pass' ) ) ), $result['score'] );
		return array_column( $result['checks'], 'pass', 'id' );
	}

	public function test_count_words_is_unicode_aware(): void {
		$this->assertSame( 0, GEOINS_Analysis::count_words( '' ) );
		$this->assertSame( 3, GEOINS_Analysis::count_words( 'one two three' ) );
		$this->assertSame( 3, GEOINS_Analysis::count_words( 'größer schön fünf' ) );
		$this->assertSame( 2, GEOINS_Analysis::count_words( 'state-of-the-art test' ) );
		$this->assertSame( 2, GEOINS_Analysis::count_words( "don't panic" ) );
	}

	public function test_a_well_structured_post_passes_every_check(): void {
		Functions\when( 'get_the_author_meta' )->justReturn( 'Writes about WordPress since 2019.' );
		$this->meta['7:_geoins_keyword'] = 'WordPress backup';

		$answer = 'A WordPress backup plugin copies your database and files to a safe place so you can restore the site after a crash, a hack or a bad update in 10 minutes.';
		$body   = str_repeat( '<p>' . str_repeat( 'Backups protect sites from data loss every single day. ', 10 ) . '</p>', 6 );
		$post   = new WP_Post(
			array(
				'ID'           => 7,
				'post_title'   => 'The WordPress Backup Guide',
				'post_content' => "<p>{$answer}</p><h2>Why do you need a backup?</h2>{$body}" .
					'<ul><li>UpdraftPlus – 3 million installs</li><li>BlogVault – 89 €</li></ul>' .
					'<p>See <a href="https://wordpress.org/plugins/updraftplus/">the plugin page</a> and <a href="/seo-guide/">our SEO guide</a>.</p>' .
					'<img src="/a.png" alt="Backup settings">',
			)
		);

		$this->assertSame( array_fill_keys( array_keys( GEOINS_Analysis::definitions() ), true ), $this->checks( $post ) );
	}

	public function test_thin_post_fails_the_content_checks(): void {
		$post   = new WP_Post(
			array(
				'post_title'   => 'Hello',
				'post_content' => '<p>Short.</p><!-- <ul><li>commented out</li></ul> --><img src="/x.png"><img src="/y.png" alt=" ">',
			)
		);
		$checks = $this->checks( $post );

		$this->assertFalse( $checks['keyword_set'], 'title fallback is not an explicit focus term' );
		$this->assertTrue( $checks['keyword_in_title'], 'falls back to the title' );
		$this->assertFalse( $checks['answer_first'] );
		$this->assertFalse( $checks['question_headings'] );
		$this->assertFalse( $checks['lists_tables'], 'HTML comments do not count' );
		$this->assertFalse( $checks['numbers'] );
		$this->assertFalse( $checks['external_links'] );
		$this->assertFalse( $checks['internal_links'] );
		$this->assertFalse( $checks['image_alt'], 'missing and blank alt texts fail' );
		$this->assertFalse( $checks['word_count'] );
		$this->assertTrue( $checks['freshness'] );
		$this->assertFalse( $checks['author_bio'] );
	}

	public function test_links_to_the_own_host_are_internal(): void {
		$post   = new WP_Post( array( 'post_content' => '<p><a href="https://site.example/about/">About</a> <a href="//cdn.example/x">CDN</a></p>' ) );
		$checks = $this->checks( $post );

		$this->assertTrue( $checks['internal_links'] );
		$this->assertFalse( $checks['external_links'], 'protocol-relative links are neither' );
	}

	public function test_stale_posts_fail_freshness(): void {
		Functions\when( 'get_post_modified_time' )->justReturn( time() - 400 * DAY_IN_SECONDS );
		$this->assertFalse( $this->checks( new WP_Post( array( 'post_content' => '<p>x</p>' ) ) )['freshness'] );
	}
}
