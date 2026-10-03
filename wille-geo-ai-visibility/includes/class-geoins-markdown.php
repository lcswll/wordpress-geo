<?php
/**
 * Markdown for AI agents:
 *  - clean Markdown version of any post/page by appending .md to its URL,
 *  - /llms-full.txt with the full content of your most important pages,
 *  - <link rel="alternate" type="text/markdown"> so agents can discover it.
 *
 * Serving Markdown removes theme markup, navigation and scripts – agents
 * get pure content, which is cheaper to read and more likely to be used.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Markdown module.
 */
class GEOINS_Markdown {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'alternate_link' ), 4 );
		add_action( 'save_post', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Serve /llms-full.txt or a .md variant of a post.
	 *
	 * @return void
	 */
	public static function maybe_serve() {
		$settings = geoins()->settings();

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$request = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$base    = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$path    = untrailingslashit( '/' . ltrim( $request, '/' ) );

		// /llms-full.txt
		if ( ! empty( $settings['llms_full'] ) && $base . '/llms-full.txt' === $path ) {
			$content = get_transient( 'geoins_llms_full_cache' );
			if ( false === $content ) {
				$content = self::build_llms_full();
				set_transient( 'geoins_llms_full_cache', $content, 12 * HOUR_IN_SECONDS );
			}
			GEOINS_Tracker::track_endpoint_hit(); // We exit before the normal tracker runs.
			self::send_text( $content );
		}

		// {permalink}.md
		if ( ! empty( $settings['md_endpoints'] ) && '.md' === substr( $path, -3 ) ) {
			$clean = substr( $path, 0, -3 );
			// Subdirectory install: the request path already contains the base
			// (/blog/my-post), home_url() would double it – strip it first.
			if ( '' !== $base && ( $clean === $base || 0 === strpos( $clean, $base . '/' ) ) ) {
				$clean = substr( $clean, strlen( $base ) );
			}
			$post_id = url_to_postid( home_url( $clean . '/' ) );
			if ( ! $post_id ) {
				$post_id = url_to_postid( home_url( $clean ) );
			}
			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post && 'publish' === $post->post_status && empty( $post->post_password ) && is_post_publicly_viewable( $post ) ) {
					GEOINS_Tracker::track_endpoint_hit( $post_id ); // We exit before the normal tracker runs.

					// Conditional GET support: crawlers re-checking an
					// unchanged page get a cheap 304 instead of the body.
					$modified_gmt = get_post_modified_time( 'D, d M Y H:i:s', true, $post ) . ' GMT';
					if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
						$since = strtotime( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) );
						if ( false !== $since && $since >= (int) get_post_modified_time( 'U', true, $post ) ) {
							status_header( 304 );
							header( 'Last-Modified: ' . $modified_gmt );
							exit;
						}
					}

					self::send_text( self::post_markdown( $post ), $modified_gmt );
				}
			}
		}
	}

	/**
	 * Output plain text and exit.
	 *
	 * @param string $content       Text content.
	 * @param string $last_modified Optional Last-Modified header value. Must
	 *                              be sent AFTER nocache_headers(), which
	 *                              removes any earlier Last-Modified header.
	 * @return void
	 */
	protected static function send_text( $content, $last_modified = '' ) {
		status_header( 200 );
		nocache_headers();
		if ( '' !== $last_modified ) {
			header( 'Last-Modified: ' . $last_modified );
		}
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text markdown, tags stripped during conversion.
		exit;
	}

	/**
	 * Advertise the Markdown variant on singular views.
	 *
	 * @return void
	 */
	public static function alternate_link() {
		$settings = geoins()->settings();
		if ( empty( $settings['md_endpoints'] ) || ! is_singular() ) {
			return;
		}
		$url = self::md_url( get_queried_object_id() );
		if ( $url ) {
			echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $url ) . '" />' . "\n";
		}
	}

	/**
	 * Markdown URL for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function md_url( $post_id ) {
		$permalink = get_permalink( $post_id );
		if ( ! $permalink || false !== strpos( $permalink, '?' ) ) {
			return ''; // Plain permalinks have no path to suffix.
		}
		return untrailingslashit( $permalink ) . '.md';
	}

	/**
	 * Full Markdown document for one post.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function post_markdown( $post ) {
		$lines   = array();
		$lines[] = '# ' . wp_strip_all_tags( get_the_title( $post ) );
		$lines[] = '';
		$lines[] = sprintf(
			'%s | %s: %s | %s: %s',
			esc_url_raw( get_permalink( $post ) ),
			'Published',
			get_the_date( 'Y-m-d', $post ),
			'Updated',
			get_the_modified_date( 'Y-m-d', $post )
		);
		$author  = get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( $author ) {
			$lines[] = 'Author: ' . wp_strip_all_tags( $author );
		}
		$lines[] = '';

		$html = function_exists( 'do_blocks' ) ? do_blocks( $post->post_content ) : $post->post_content;
		$html = strip_shortcodes( $html );

		$lines[] = self::html_to_markdown( $html );

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * llms-full.txt: site header + full content of the included items.
	 *
	 * @return string
	 */
	public static function build_llms_full() {
		$settings = geoins()->settings();

		$out   = array();
		$out[] = '# ' . wp_strip_all_tags( get_bloginfo( 'name' ) );
		$tag   = get_bloginfo( 'description' );
		if ( '' !== $tag ) {
			$out[] = '';
			$out[] = '> ' . wp_strip_all_tags( $tag );
		}
		if ( ! empty( $settings['llms_intro'] ) ) {
			$out[] = '';
			$out[] = wp_strip_all_tags( $settings['llms_intro'] );
		}

		$max   = max( 1, absint( $settings['llms_max_items'] ) );
		$items = GEOINS_Llms_Txt::pinned_items();
		if ( ! empty( $settings['llms_include_pages'] ) ) {
			$pages = get_pages(
				array(
					'sort_column' => 'menu_order,post_title',
					'number'      => $max,
				)
			);
			if ( is_array( $pages ) ) { // get_pages() returns false for a non-hierarchical post type.
				$items = array_merge( $items, $pages );
			}
		}
		if ( ! empty( $settings['llms_include_posts'] ) ) {
			$items = array_merge(
				$items,
				get_posts(
					array(
						'numberposts' => $max,
						'post_status' => 'publish',
					)
				)
			);
		}

		// Pinned first, no duplicates.
		$seen = array();
		foreach ( $items as $index => $item ) {
			if ( isset( $seen[ $item->ID ] ) ) {
				unset( $items[ $index ] );
			}
			$seen[ $item->ID ] = true;
		}

		foreach ( $items as $item ) {
			$out[] = '';
			$out[] = '---';
			$out[] = '';
			$out[] = self::post_markdown( $item );
		}

		$content = implode( "\n", $out ) . "\n";

		/**
		 * Filters the generated llms-full.txt content.
		 *
		 * @param string $content Markdown content.
		 */
		return apply_filters( 'geoins_llms_full', $content );
	}

	/**
	 * Convert HTML to Markdown (dependency-free, DOM-based).
	 *
	 * @param string $html HTML fragment.
	 * @return string
	 */
	public static function html_to_markdown( $html ) {
		if ( '' === trim( $html ) ) {
			return '';
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return wp_strip_all_tags( $html );
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $doc->loadHTML(
			'<?xml encoding="utf-8"?><body>' . $html . '</body>',
			LIBXML_NOERROR | LIBXML_NOWARNING
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return wp_strip_all_tags( $html );
		}

		$body = $doc->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return wp_strip_all_tags( $html );
		}

		$markdown = self::walk( $body, 0 );
		// Collapse 3+ blank lines.
		$markdown = (string) preg_replace( "/\n{3,}/", "\n\n", $markdown );
		return trim( $markdown ) . "\n";
	}

	/**
	 * Recursive DOM walker.
	 *
	 * @param DOMNode $node       Node.
	 * @param int     $list_depth Current list nesting depth.
	 * @return string
	 */
	protected static function walk( $node, $list_depth ) {
		// Text nodes (XML_TEXT_NODE). CDATA sections subclass DOMText and stay skipped.
		if ( $node instanceof DOMText && ! $node instanceof DOMCdataSection ) {
			return (string) preg_replace( '/\s+/', ' ', $node->data );
		}
		// Anything but an element (XML_ELEMENT_NODE): comments, PIs, ...
		if ( ! $node instanceof DOMElement ) {
			return '';
		}

		$tag      = strtolower( $node->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$children = '';
		foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			$children .= self::walk( $child, in_array( $tag, array( 'ul', 'ol' ), true ) ? $list_depth + 1 : $list_depth );
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = (int) substr( $tag, 1 );
				return "\n\n" . str_repeat( '#', $level ) . ' ' . trim( $children ) . "\n\n";
			case 'p':
				return "\n\n" . trim( $children ) . "\n\n";
			case 'br':
				return "  \n";
			case 'hr':
				return "\n\n---\n\n";
			case 'strong':
			case 'b':
				return '' !== trim( $children ) ? '**' . trim( $children ) . '**' : '';
			case 'em':
			case 'i':
				return '' !== trim( $children ) ? '*' . trim( $children ) . '*' : '';
			case 'code':
				if ( $node->parentNode && 'pre' === strtolower( $node->parentNode->nodeName ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
					return $children;
				}
				return '`' . trim( $children ) . '`';
			case 'pre':
				return "\n\n```\n" . trim( $node->textContent ) . "\n```\n\n"; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
			case 'blockquote':
				$quoted = array();
				foreach ( explode( "\n", trim( $children ) ) as $line ) {
					$quoted[] = '> ' . trim( $line );
				}
				return "\n\n" . implode( "\n", $quoted ) . "\n\n";
			case 'ul':
			case 'ol':
				return "\n" . $children . ( $list_depth <= 0 ? "\n" : '' );
			case 'li':
				$indent = str_repeat( '  ', max( 0, $list_depth - 1 ) );
				$marker = ( $node->parentNode && 'ol' === strtolower( $node->parentNode->nodeName ) ) ? '1.' : '-'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				return $indent . $marker . ' ' . trim( $children ) . "\n";
			case 'a':
				$href = $node->getAttribute( 'href' );
				$text = trim( $children );
				if ( '' === $text ) {
					return '';
				}
				return $href ? '[' . $text . '](' . esc_url_raw( $href ) . ')' : $text;
			case 'img':
				$alt = $node->getAttribute( 'alt' );
				$src = $node->getAttribute( 'src' );
				return $src ? '![' . $alt . '](' . esc_url_raw( $src ) . ')' : '';
			case 'table':
				return self::table_to_markdown( $node );
			case 'script':
			case 'style':
			case 'noscript':
			case 'iframe':
			case 'form':
			case 'button':
				return '';
			default:
				return $children;
		}
	}

	/**
	 * Convert a table element to a Markdown pipe table.
	 *
	 * @param DOMElement $table Table element.
	 * @return string
	 */
	protected static function table_to_markdown( $table ) {
		$rows = array();
		foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
			$cells = array();
			foreach ( $tr->childNodes as $cell ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				$cell_tag = strtolower( $cell->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				if ( 'td' === $cell_tag || 'th' === $cell_tag ) {
					$cells[] = trim( (string) preg_replace( '/\s+/', ' ', $cell->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
				}
			}
			if ( $cells ) {
				$rows[] = $cells;
			}
		}
		if ( empty( $rows ) ) {
			return '';
		}

		$out   = array();
		$out[] = '| ' . implode( ' | ', $rows[0] ) . ' |';
		$out[] = '|' . str_repeat( ' --- |', count( $rows[0] ) );
		foreach ( array_slice( $rows, 1 ) as $row ) {
			$out[] = '| ' . implode( ' | ', $row ) . ' |';
		}
		return "\n\n" . implode( "\n", $out ) . "\n\n";
	}

	/**
	 * Flush caches when content changes.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		delete_transient( 'geoins_llms_full_cache' );
	}
}
