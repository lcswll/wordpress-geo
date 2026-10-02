<?php
/**
 * Content signals: proven on-page GEO/SEO measures.
 *
 *  - lastmod in the core XML sitemap (crawl prioritization: engines
 *    recrawl faster when the sitemap carries reliable update times),
 *  - visible "Updated on" line (freshness is a ranking and citation
 *    signal; the visible date is what snippets and AI answers pick up),
 *  - automatic table of contents + anchor IDs on headings (precise
 *    deep links for citations, better chunk retrieval).
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content signals module.
 */
class GEOINS_Content {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		$settings = geoins()->settings();

		if ( ! empty( $settings['sitemap_lastmod'] ) ) {
			add_filter( 'wp_sitemaps_posts_entry', array( __CLASS__, 'sitemap_lastmod' ), 10, 2 );
		}

		if ( ! empty( $settings['toc'] ) || ! empty( $settings['show_modified'] ) ) {
			// Priority 12: AFTER do_blocks (9), wpautop (10) and do_shortcode
			// (11), so headings from dynamic blocks, synced patterns and
			// shortcodes are visible to the anchor/TOC pass.
			add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 12 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_styles' ) );
		}
	}

	/**
	 * Add the post's modification time to core sitemap entries.
	 *
	 * @param array<string,mixed> $entry Sitemap entry.
	 * @param WP_Post             $post  Post object.
	 * @return array<string,mixed>
	 */
	public static function sitemap_lastmod( $entry, $post ) {
		$entry['lastmod'] = get_post_modified_time( 'c', true, $post );
		return $entry;
	}

	/**
	 * Minimal, theme-neutral frontend styles for the TOC / updated line.
	 *
	 * @return void
	 */
	public static function front_styles() {
		if ( ! is_singular() ) {
			return;
		}
		wp_register_style( 'geoins-front', false, array(), GEOINS_VERSION );
		wp_enqueue_style( 'geoins-front' );
		wp_add_inline_style(
			'geoins-front',
			'.geoins-updated{font-size:.85em;opacity:.75;margin-bottom:1em}' .
			'.geoins-toc{border:1px solid rgba(128,128,128,.25);border-radius:8px;padding:.9em 1.2em;margin:0 0 1.5em;font-size:.92em}' .
			'.geoins-toc-title{font-weight:600;margin:0 0 .4em;display:block}' .
			'.geoins-toc ol{margin:0 0 0 1.1em;padding:0}' .
			'.geoins-toc li{margin:.15em 0}' .
			'.geoins-toc .geoins-toc-h3{margin-left:1.1em}'
		);
	}

	/**
	 * Add anchors + TOC + updated line to singular main content.
	 *
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public static function filter_content( $content ) {
		// Never inject into auto-generated excerpts or oEmbed cards – the
		// nested the_content pass inside wp_trim_excerpt() would flatten the
		// updated line + TOC link texts into the excerpt.
		if ( is_embed() || doing_filter( 'get_the_excerpt' ) || doing_filter( 'the_excerpt' ) ) {
			return $content;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || post_password_required( $post ) ) {
			return $content;
		}

		$settings = geoins()->settings();

		if ( ! empty( $settings['toc'] ) ) {
			$content = self::add_heading_anchors_and_toc( $content );
		}

		if ( ! empty( $settings['show_modified'] ) ) {
			$published = (int) get_post_time( 'U', true, $post );
			$modified  = (int) get_post_modified_time( 'U', true, $post );
			// Show only when the update is a real revision, not the initial save.
			if ( $modified - $published > 2 * DAY_IN_SECONDS ) {
				$line = sprintf(
					/* translators: %s: formatted date. */
					esc_html__( 'Updated on %s', 'geo-insights-ai' ),
					esc_html( (string) wp_date( get_option( 'date_format' ), $modified ) )
				);
				$content = '<p class="geoins-updated">' . $line . '</p>' . $content;
			}
		}

		return $content;
	}

	/**
	 * Give every H2/H3 an anchor id and, when there are at least three,
	 * prepend a linked table of contents. Existing ids are respected.
	 *
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public static function add_heading_anchors_and_toc( $content ) {
		$items = array();
		$used  = array();

		// Seed with every id already in the document, so a generated slug
		// can never collide with a manual anchor further down the page.
		if ( preg_match_all( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $content, $pre ) ) {
			foreach ( $pre[1] as $existing ) {
				$used[ $existing ] = true;
			}
		}

		$replaced = preg_replace_callback(
			'/<h([23])((?:[^>]*)?)>(.*?)<\/h\1>/is',
			static function ( $m ) use ( &$items, &$used ) {
				$level = (int) $m[1];
				$attrs = $m[2];
				$inner = $m[3];
				$text  = trim( wp_strip_all_tags( $inner ) );
				if ( '' === $text ) {
					return $m[0];
				}

				if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $id_m ) ) {
					$id = $id_m[1];
				} else {
					$id = sanitize_title( $text );
					if ( '' === $id ) {
						$id = 'section';
					}
					$base = $id;
					$n    = 2;
					while ( isset( $used[ $id ] ) ) {
						$id = $base . '-' . $n;
						$n++;
					}
					$attrs .= ' id="' . esc_attr( $id ) . '"';
				}
				$used[ $id ] = true;

				$items[] = array(
					'level' => $level,
					'id'    => $id,
					'text'  => $text,
				);

				return '<h' . $level . $attrs . '>' . $inner . '</h' . $level . '>';
			},
			$content
		);
		if ( null === $replaced ) {
			return $content; // PCRE failure (e.g. backtrack limit): leave the content untouched instead of blanking it.
		}
		$content = $replaced;

		if ( count( $items ) < 3 ) {
			return $content;
		}

		$list = '';
		foreach ( $items as $item ) {
			$list .= '<li class="geoins-toc-h' . (int) $item['level'] . '"><a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a></li>';
		}
		$toc = '<nav class="geoins-toc" aria-label="' . esc_attr__( 'Table of contents', 'geo-insights-ai' ) . '">' .
			'<span class="geoins-toc-title">' . esc_html__( 'Contents', 'geo-insights-ai' ) . '</span>' .
			'<ol>' . $list . '</ol></nav>';

		// Insert before the first H2 (fallback: prepend).
		$pos = stripos( $content, '<h2' );
		if ( false !== $pos ) {
			return substr( $content, 0, $pos ) . $toc . substr( $content, $pos );
		}
		return $toc . $content;
	}
}
