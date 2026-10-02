<?php
/**
 * IndexNow integration (opt-in).
 *
 * IndexNow is an open protocol (Bing, Seznam, Naver, Yandex …): when you
 * publish or update content, the plugin pings the IndexNow API so search
 * engines fetch the new version within minutes instead of days. That
 * matters for GEO because Bing's index feeds Microsoft Copilot and parts
 * of ChatGPT search – faster indexing means faster AI citations.
 *
 * The only data transmitted is the public URL of the published content
 * plus the site's IndexNow key. Strictly opt-in.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IndexNow module.
 */
class GEOINS_IndexNow {

	const API_ENDPOINT = 'https://api.indexnow.org/indexnow';

	/**
	 * Public permalinks captured before a status change writes to the DB,
	 * keyed by post ID – used to submit the URL that actually was public
	 * when a post is unpublished (afterwards the permalink is already the
	 * "__trashed" slug or the ?p= fallback).
	 *
	 * @var array<int,string>
	 */
	protected static $old_urls = array();

	/**
	 * Hook up.
	 */
	public static function init() {
		// Registered regardless of the setting: the check event is scheduled
		// by the settings save and must be able to fire on the next request.
		add_action( 'geoins_indexnow_check', array( __CLASS__, 'verify_key_reachable' ) );

		$settings = geoins()->settings();
		if ( empty( $settings['indexnow'] ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_key' ), 0 );
		// wp_trash_post fires BEFORE core renames the slug to "{slug}__trashed";
		// pre_post_update covers the other unpublish paths (draft/pending) but
		// runs AFTER that rename, so for trash it would capture the wrong URL.
		add_action( 'wp_trash_post', array( __CLASS__, 'remember_url' ) );
		add_action( 'pre_post_update', array( __CLASS__, 'remember_url' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
	}

	/**
	 * Cron: check that the key file is reachable from the outside. With
	 * plain permalinks on Apache the request never reaches WordPress, and
	 * every IndexNow ping would be silently discarded by the engines – so
	 * submissions pause while this flag is set, and the settings page
	 * explains how to fix it.
	 */
	public static function verify_key_reachable() {
		$settings = geoins()->settings();
		if ( empty( $settings['indexnow'] ) ) {
			return;
		}
		$check = wp_remote_get(
			self::key_location(),
			array(
				'timeout'    => 5,
				'user-agent' => 'GEO-Insights-WordPress-Plugin/' . GEOINS_VERSION,
			)
		);
		$ok    = ! is_wp_error( $check )
			&& 200 === wp_remote_retrieve_response_code( $check )
			&& trim( wp_remote_retrieve_body( $check ) ) === self::key();
		update_option( 'geoins_indexnow_unreachable', $ok ? 0 : 1, false );
	}

	/**
	 * Capture the live permalink before an update is written. First capture
	 * wins: for a trash operation, wp_trash_post stores the original pretty
	 * URL, and the later pre_post_update call (which runs after core already
	 * renamed the slug) must not overwrite it.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function remember_url( $post_id ) {
		$post_id = (int) $post_id;
		if ( isset( self::$old_urls[ $post_id ] ) ) {
			return;
		}
		if ( 'publish' === get_post_status( $post_id ) ) {
			$url = get_permalink( $post_id );
			if ( $url ) {
				self::$old_urls[ $post_id ] = $url;
			}
		}
	}

	/**
	 * The site's IndexNow key (created on demand).
	 *
	 * @return string 32-char hex key.
	 */
	public static function key() {
		$key = get_option( 'geoins_indexnow_key', '' );
		if ( ! is_string( $key ) || ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) {
			$key = md5( wp_generate_uuid4() . wp_rand() );
			update_option( 'geoins_indexnow_key', $key, false );
		}
		return $key;
	}

	/**
	 * URL of the key file the search engines use to verify ownership.
	 *
	 * @return string
	 */
	public static function key_location() {
		return home_url( '/' . self::key() . '.txt' );
	}

	/**
	 * Serve the key file at /{key}.txt.
	 */
	public static function maybe_serve_key() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$request = (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
		$base    = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$path    = untrailingslashit( '/' . ltrim( $request, '/' ) );

		if ( $base . '/' . self::key() . '.txt' !== $path ) {
			return;
		}

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo esc_html( self::key() );
		exit;
	}

	/**
	 * Submit on publish, update and unpublish of public content.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( ! is_post_type_viewable( $post->post_type ) || ! empty( $post->post_password ) ) {
			return;
		}

		// Throttle rapid saves: at most one ping per post every 2 minutes.
		// Only publish-state saves are throttled, so an update followed by
		// an immediate unpublish still sends the removal ping.
		if ( 'publish' === $new_status ) {
			if ( get_transient( 'geoins_indexnow_' . $post->ID ) ) {
				return;
			}
			set_transient( 'geoins_indexnow_' . $post->ID, 1, 2 * MINUTE_IN_SECONDS );
		}

		// Unpublish: submit the URL that WAS public (captured before the
		// save), not the post-save permalink ("__trashed" slug / ?p= form).
		if ( 'publish' !== $new_status && isset( self::$old_urls[ $post->ID ] ) ) {
			$url = self::$old_urls[ $post->ID ];
		} else {
			$url = get_permalink( $post );
		}
		if ( ! $url ) {
			return;
		}
		self::submit( array( $url ) );
	}

	/**
	 * Fire-and-forget submission to the IndexNow API.
	 *
	 * @param string[] $urls Absolute URLs on this site.
	 * @return bool Whether the request was dispatched.
	 */
	public static function submit( $urls ) {
		// Key file unreachable (e.g. plain permalinks on Apache): every ping
		// would be discarded by the receiving engines – skip instead of
		// pretending success. The settings page explains how to fix it.
		if ( get_option( 'geoins_indexnow_unreachable' ) ) {
			return false;
		}

		$urls = array_values( array_filter( array_map( 'esc_url_raw', (array) $urls ) ) );
		if ( empty( $urls ) ) {
			return false;
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout'    => 3,
				'blocking'   => false,
				'user-agent' => 'GEO-Insights-WordPress-Plugin/' . GEOINS_VERSION,
				'headers'    => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'       => wp_json_encode(
					array(
						'host'        => $host,
						'key'         => self::key(),
						'keyLocation' => self::key_location(),
						'urlList'     => array_slice( $urls, 0, 100 ),
					)
				),
			)
		);

		update_option(
			'geoins_indexnow_last',
			array(
				'time' => time(),
				'url'  => $urls[0],
			),
			false
		);
		return true;
	}

	/**
	 * Human-readable info about the last submission (settings page).
	 *
	 * @return string
	 */
	public static function last_info() {
		$last = get_option( 'geoins_indexnow_last', array() );
		if ( empty( $last['time'] ) ) {
			return __( 'No URL submitted yet – the next publish or update will trigger the first ping.', 'geo-insights-ai' );
		}
		return sprintf(
			/* translators: 1: URL, 2: date. */
			__( 'Last submitted: %1$s on %2$s.', 'geo-insights-ai' ),
			$last['url'],
			wp_date( get_option( 'date_format' ) . ' H:i', (int) $last['time'] )
		);
	}
}
