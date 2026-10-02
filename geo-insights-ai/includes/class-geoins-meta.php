<?php
/**
 * Basic meta tags: description + Open Graph.
 *
 * Only active when no full SEO plugin is running, so nothing is
 * ever duplicated.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta tags module.
 */
class GEOINS_Meta {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 4 );
	}

	/**
	 * Print meta tags.
	 */
	public static function output() {
		$settings = geoins()->settings();
		if ( empty( $settings['meta_tags'] ) || geoins()->seo_plugin_active() ) {
			return;
		}

		$description = self::description();
		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		// Open Graph / Twitter card.
		$title = is_singular() ? get_the_title() : get_bloginfo( 'name' );
		$url   = is_singular() ? get_permalink() : home_url( '/' );

		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
		echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		if ( '' !== $description ) {
			echo '<meta property="og:description" content="' . esc_attr( $description ) . '" />' . "\n";
		}

		$image = '';
		if ( is_singular() ) {
			$image = (string) get_the_post_thumbnail_url( null, 'full' );
		}
		if ( '' === $image && get_option( 'site_icon' ) ) {
			$image = (string) wp_get_attachment_image_url( get_option( 'site_icon' ), 'full' );
		}
		if ( '' !== $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
		}
	}

	/**
	 * Best available description for the current view.
	 *
	 * @return string
	 */
	protected static function description() {
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				if ( '' !== $post->post_excerpt ) {
					return wp_trim_words( wp_strip_all_tags( $post->post_excerpt ), 30, '…' );
				}
				return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );
			}
		}
		if ( is_front_page() || is_home() ) {
			return get_bloginfo( 'description' );
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$desc = term_description();
			if ( $desc ) {
				return wp_trim_words( wp_strip_all_tags( $desc ), 30, '…' );
			}
		}
		return '';
	}
}
