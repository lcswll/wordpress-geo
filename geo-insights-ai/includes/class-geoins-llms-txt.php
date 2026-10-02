<?php
/**
 * Serves a generated /llms.txt file.
 *
 * llms.txt is a young, optional standard: a Markdown site summary for
 * language models and AI agents. Big crawlers rarely fetch it yet, but
 * coding/IDE agents do, it costs nothing, and it sharpens your own
 * information architecture. Cheap forward investment, not a magic bullet.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * llms.txt endpoint.
 */
class GEOINS_Llms_Txt {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
		add_action( 'save_post', array( __CLASS__, 'flush_cache' ) );
	}

	/**
	 * Serve /llms.txt when requested.
	 */
	public static function maybe_serve() {
		$settings = geoins()->settings();
		if ( empty( $settings['llms_txt'] ) ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$base    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( untrailingslashit( $base ) . '/llms.txt' !== untrailingslashit( '/' . ltrim( $request, '/' ) ) ) {
			return;
		}

		$content = get_transient( 'geoins_llms_txt_cache' );
		if ( false === $content ) {
			$content = self::build();
			set_transient( 'geoins_llms_txt_cache', $content, 12 * HOUR_IN_SECONDS );
		}

		GEOINS_Tracker::track_endpoint_hit(); // We exit before the normal tracker runs.

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text markdown built from sanitized parts below.
		exit;
	}

	/**
	 * Build the llms.txt markdown.
	 *
	 * @return string
	 */
	public static function build() {
		$settings = geoins()->settings();

		$lines   = array();
		$lines[] = '# ' . self::txt( get_bloginfo( 'name' ) );

		$tagline = get_bloginfo( 'description' );
		if ( '' !== $tagline ) {
			$lines[] = '';
			$lines[] = '> ' . self::txt( $tagline );
		}

		if ( ! empty( $settings['llms_intro'] ) ) {
			$lines[] = '';
			$lines[] = self::txt( $settings['llms_intro'] );
		}

		$max = max( 1, absint( $settings['llms_max_items'] ) );

		$pinned     = self::pinned_items();
		$pinned_ids = wp_list_pluck( $pinned, 'ID' );
		if ( $pinned ) {
			$lines[] = '';
			$lines[] = '## ' . self::txt( __( 'Key content', 'geo-insights-ai' ) );
			$lines[] = '';
			foreach ( $pinned as $item ) {
				$lines[] = self::item_line( $item );
			}
		}

		if ( ! empty( $settings['llms_include_pages'] ) ) {
			$pages = get_pages(
				array(
					'sort_column' => 'menu_order,post_title',
					'number'      => $max,
				)
			);
			if ( $pages ) {
				$page_lines = array();
				foreach ( $pages as $page ) {
					if ( ! in_array( $page->ID, $pinned_ids, true ) ) {
						$page_lines[] = self::item_line( $page );
					}
				}
				if ( $page_lines ) {
					$lines[] = '';
					$lines[] = '## ' . self::txt( __( 'Pages', 'geo-insights-ai' ) );
					$lines[] = '';
					$lines   = array_merge( $lines, $page_lines );
				}
			}
		}

		if ( ! empty( $settings['llms_include_posts'] ) ) {
			$posts = get_posts(
				array(
					'numberposts' => $max,
					'post_status' => 'publish',
				)
			);
			if ( $posts ) {
				$post_lines = array();
				foreach ( $posts as $post ) {
					if ( ! in_array( $post->ID, $pinned_ids, true ) ) {
						$post_lines[] = self::item_line( $post );
					}
				}
				if ( $post_lines ) {
					$lines[] = '';
					$lines[] = '## ' . self::txt( __( 'Posts', 'geo-insights-ai' ) );
					$lines[] = '';
					$lines   = array_merge( $lines, $post_lines );
				}
			}
		}

		$lines[] = '';
		$lines[] = '## ' . self::txt( __( 'Feeds', 'geo-insights-ai' ) );
		$lines[] = '';
		$lines[] = '- [RSS](' . esc_url_raw( get_feed_link() ) . ')';
		if ( function_exists( 'get_sitemap_url' ) ) {
			$sitemap = get_sitemap_url( 'index' );
			if ( $sitemap ) {
				$lines[] = '- [Sitemap](' . esc_url_raw( $sitemap ) . ')';
			}
		}
		if ( ! empty( $settings['llms_full'] ) ) {
			$lines[] = '- [llms-full.txt](' . esc_url_raw( home_url( '/llms-full.txt' ) ) . ')';
		}
		if ( ! empty( $settings['md_endpoints'] ) && '' !== get_option( 'permalink_structure' ) ) {
			$lines[] = '';
			$lines[] = self::txt( __( 'A clean Markdown version of every page is available by appending .md to its URL.', 'geo-insights-ai' ) );
		}

		$content = implode( "\n", $lines ) . "\n";

		/**
		 * Filters the generated llms.txt content.
		 *
		 * @param string $content Markdown content.
		 */
		return apply_filters( 'geoins_llms_txt', $content );
	}

	/**
	 * Posts/pages pinned as key content (edit screen checkbox).
	 *
	 * @return WP_Post[]
	 */
	public static function pinned_items() {
		return get_posts(
			array(
				'post_type'   => array( 'post', 'page' ),
				'post_status' => 'publish',
				'numberposts' => 50,
				'orderby'     => 'menu_order title',
				'order'       => 'ASC',
				'meta_key'    => '_geoins_llms_pin', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * One markdown list item for a post object.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	protected static function item_line( $post ) {
		$title   = self::txt( get_the_title( $post ) );
		$url     = esc_url_raw( get_permalink( $post ) );
		$excerpt = self::txt( wp_trim_words( get_the_excerpt( $post ), 25, '…' ) );
		$line    = '- [' . $title . '](' . $url . ')';
		if ( '' !== $excerpt ) {
			$line .= ': ' . $excerpt;
		}
		return $line;
	}

	/**
	 * Plain-text sanitizer for markdown output.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	protected static function txt( $text ) {
		$text = wp_strip_all_tags( (string) $text, true );
		return str_replace( array( '[', ']' ), array( '(', ')' ), $text );
	}

	/**
	 * Flush the cache when content changes.
	 */
	public static function flush_cache() {
		delete_transient( 'geoins_llms_txt_cache' );
	}
}
