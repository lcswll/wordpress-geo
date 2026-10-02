<?php
/**
 * Content transformations: HTML → Markdown (.md endpoints, llms-full.txt), heading anchors + TOC, FAQ schema.
 *
 * @package GEO_Insights
 */

namespace GEOINS\Tests;

use Brain\Monkey\Functions;
use GEOINS_Content;
use GEOINS_Markdown;
use GEOINS_Schema;
use WP_Post;

final class ContentTest extends TestCase {

	public function test_markdown_covers_the_common_blocks(): void {
		$md = GEOINS_Markdown::html_to_markdown(
			'<h2>Title</h2><p>Text with <strong>bold</strong>, <em>italic</em> and a <a href="https://example.org/x">link</a>.</p>' .
			'<ul><li>One</li><li>Two</li></ul><ol><li>First</li></ol>' .
			'<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>'
		);

		$this->assertStringContainsString( '## Title', $md );
		$this->assertStringContainsString( '**bold**', $md );
		$this->assertStringContainsString( '[link](https://example.org/x)', $md );
		$this->assertStringContainsString( '- One', $md );
		$this->assertStringContainsString( '1. First', $md );
		$this->assertStringContainsString( '| A | B |', $md );
		$this->assertStringContainsString( '| 1 | 2 |', $md );
		$this->assertStringNotContainsString( "\n\n\n", $md, 'blank lines are collapsed' );
	}

	public function test_markdown_drops_scripts_and_keeps_umlauts(): void {
		$md = GEOINS_Markdown::html_to_markdown( '<p>Größe: 5 €</p><script>alert(1)</script><style>p{}</style>' );
		$this->assertStringContainsString( 'Größe: 5 €', $md );
		$this->assertStringNotContainsString( 'alert', $md );
		$this->assertSame( '', GEOINS_Markdown::html_to_markdown( '   ' ) );
	}

	public function test_toc_adds_unique_anchors_and_respects_existing_ids(): void {
		$html = GEOINS_Content::add_heading_anchors_and_toc(
			'<p>Intro</p><h2 class="wp-block-heading">Was ist GEO?</h2><p>a</p><h3>Details &amp; <strong>mehr</strong></h3><p>b</p>' .
			'<h2>Was ist GEO?</h2><p>c</p><h2 id="custom-id">Fazit</h2><p id="was-ist-geo-3">manual anchor</p>'
		);

		$this->assertStringContainsString( 'class="geoins-toc"', $html );
		$this->assertStringContainsString( '<h2 class="wp-block-heading" id="was-ist-geo">', $html );
		$this->assertStringContainsString( 'id="was-ist-geo-2"', $html, 'duplicate headings get a suffix' );
		$this->assertStringContainsString( 'href="#custom-id"', $html, 'existing ids are linked, not replaced' );
		$this->assertSame( 1, substr_count( $html, 'id="was-ist-geo-3"' ), 'no collision with a manual anchor' );
		$this->assertLessThan( strpos( $html, '<h2' ), strpos( $html, 'geoins-toc' ), 'TOC sits before the first H2' );
		$this->assertGreaterThan( strpos( $html, 'Intro' ), strpos( $html, 'geoins-toc' ) );
	}

	public function test_toc_needs_three_headings(): void {
		$html = '<h2>A</h2><p>x</p><h2>B</h2>';
		$this->assertStringNotContainsString( 'geoins-toc', GEOINS_Content::add_heading_anchors_and_toc( $html ) );
	}

	public function test_faq_schema_from_question_headings(): void {
		$post = new WP_Post(
			array(
				'post_content' => '<h2>What is GEO?</h2><p>Generative engine optimization makes content citable by AI assistants.</p>' .
					'<h3>Does it replace SEO?</h3><p>No – it builds on classic SEO and adds the AI layer on top of it.</p>' .
					'<h2>Too short?</h2><p>Yes.</p><h2>Not a question</h2><p>Some longer text that is not part of the FAQ at all.</p>',
			)
		);

		Functions\when( 'get_permalink' )->justReturn( 'https://site.example/geo/' );
		$faq = GEOINS_Schema::faq_from_content( $post );

		$this->assertIsArray( $faq );
		$this->assertSame( 'FAQPage', $faq['@type'] );
		$this->assertSame( 'https://site.example/geo/#faq', $faq['@id'] );
		$this->assertSame( array( 'What is GEO?', 'Does it replace SEO?' ), array_column( $faq['mainEntity'], 'name' ) );
		$this->assertSame( 'Answer', $faq['mainEntity'][0]['acceptedAnswer']['@type'] );
	}

	public function test_faq_schema_needs_two_questions(): void {
		$post = new WP_Post( array( 'post_content' => '<h2>What is GEO?</h2><p>Generative engine optimization makes content citable.</p>' ) );
		$this->assertNull( GEOINS_Schema::faq_from_content( $post ) );
	}
}
