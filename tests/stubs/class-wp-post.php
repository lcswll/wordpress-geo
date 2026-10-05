<?php
/**
 * Minimal WP_Post: the plugin only reads public properties.
 *
 * @package Wille_GEO
 */

final class WP_Post {
	/** @var int */
	public $ID = 1;
	/** @var string */
	public $post_title = '';
	/** @var string */
	public $post_content = '';
	/** @var string */
	public $post_author = '1';
	/** @var string */
	public $post_type = 'post';
	/** @var string */
	public $post_status = 'publish';
	/** @var string */
	public $post_modified_gmt = '2026-10-01 12:00:00';

	/** @param array<string,mixed> $props */
	public function __construct( array $props = array() ) {
		foreach ( $props as $key => $value ) {
			$this->$key = $value;
		}
	}
}
