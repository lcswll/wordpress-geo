<?php
/**
 * Base test case: Brain Monkey + the WordPress helpers the plugin's pure functions use.
 *
 * @package Wille_GEO
 */

namespace GEOINS\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use GEOINS_Test_Plugin;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	/** @var array<string,mixed> Post meta by "<post id>:<key>". */
	protected $meta = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		GEOINS_Test_Plugin::$settings = array();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\stubs(
			array(
				'wp_json_encode'         => static function ( $data, $flags = 0 ) {
					return json_encode( $data, $flags );
				},
				'wp_parse_url'           => static function ( $url, $component = -1 ) {
					return parse_url( $url, $component );
				},
				'wp_strip_all_tags'      => static function ( $text ) {
					$text = preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $text );
					return trim( strip_tags( $text ) );
				},
				'sanitize_key'           => static function ( $key ) {
					return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
				},
				'sanitize_title'         => static function ( $title ) {
					$title = strtolower(
						strtr(
							(string) $title,
							array(
								'ä' => 'ae',
								'ö' => 'oe',
								'ü' => 'ue',
								'ß' => 'ss',
							)
						)
					);
					$title = preg_replace( '/[^a-z0-9\s-]/', '', $title );
					return trim( preg_replace( '/[\s-]+/', '-', $title ), '-' );
				},
				'strip_shortcodes'       => static function ( $content ) {
					return $content;
				},
				'home_url'               => 'https://site.example',
				'site_url'               => 'https://site.example',
				'get_post_meta'          => function ( $post_id, $key = '' ) {
					return $this->meta[ $post_id . ':' . $key ] ?? '';
				},
				'get_the_title'          => static function ( $post ) {
					return $post instanceof \WP_Post ? $post->post_title : '';
				},
				'get_the_author_meta'    => '',
				'get_post_modified_time' => static function () {
					return time();
				},
			)
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
