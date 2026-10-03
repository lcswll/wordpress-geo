<?php
/**
 * Cache-proof AI referral counting (opt-in).
 *
 * Full-page caches (server caches, static HTML, some CDN setups) serve
 * pages without running PHP, so the PHP tracker never sees those visits.
 * This module adds a tiny JavaScript beacon (< 1 kB, no cookies, no
 * personal data) that runs in the visitor's browser, checks whether the
 * visit came from a known AI answer (referrer domain or utm_source) and,
 * only then, reports source + page path to a REST endpoint.
 *
 * The endpoint has to be public: it is called by anonymous visitors from
 * cached HTML, so neither a login nor a nonce (which would go stale in
 * the cache) can work. Safeguards against forged statistics instead:
 *  - a same-origin Origin/Referer header is required,
 *  - every page carries a token signed with the site's secret salt for
 *    exactly that post (stable, so it survives full-page caches); a ping
 *    for a page whose token it does not carry is rejected,
 *  - the source must be one of the known AI referral sources,
 *  - per-client rate limit (hashed IP in a 60-second transient, never
 *    stored), and paths must resolve to real content on this site,
 *  - the same visitor reporting the same page twice within a minute is
 *    deduplicated in GEOINS_Tracker.
 * The endpoint only ever increments anonymous counters of this site and
 * is disabled unless the site owner opts in.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beacon module.
 */
class GEOINS_Beacon {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * REST route the beacon posts to.
	 *
	 * @return void
	 */
	public static function register_route() {
		register_rest_route(
			'geoins/v1',
			'/beacon',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => '__return_true', // Intentionally public (anonymous visitors, cached pages); see the safeguards above and in handle().
				'args'                => array(
					'source' => array(
						'type'     => 'string',
						'required' => true,
					),
					'path'   => array(
						'type'     => 'string',
						'required' => true,
					),
					'token'  => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Handle one beacon ping.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle( $request ) {
		$settings = geoins()->settings();
		if ( empty( $settings['referral_beacon'] ) || empty( $settings['track_referrals'] ) ) {
			return new WP_REST_Response( null, 404 );
		}

		// Same-origin check: browsers send Origin (and Referer) with the
		// beacon's POST; a request without either did not come from a page
		// of this site.
		$origin = (string) $request->get_header( 'origin' );
		if ( '' === $origin ) {
			$origin = (string) $request->get_header( 'referer' );
		}
		$origin_host = (string) wp_parse_url( $origin, PHP_URL_HOST );
		$site_host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $origin_host || 0 !== strcasecmp( $origin_host, $site_host ) ) {
			return new WP_REST_Response( null, 403 );
		}

		$source  = sanitize_key( (string) $request->get_param( 'source' ) );
		$sources = GEOINS_Bots::referral_sources();
		if ( ! isset( $sources[ $source ] ) ) {
			return new WP_REST_Response( null, 400 );
		}

		// Rate limit: max 20 accepted pings per client per minute. The IP is
		// salted-hashed into a transient key and never stored anywhere.
		// client_ip() honors the proxy_header setting – behind a CDN/proxy,
		// REMOTE_ADDR would be the proxy and this would become a site-wide cap.
		$ip        = GEOINS_Verify::client_ip();
		$limit_key = 'geoins_beacon_' . md5( wp_salt() . '|' . $ip );
		$hits      = (int) get_transient( $limit_key );
		if ( $hits >= 20 ) {
			return new WP_REST_Response( null, 429 );
		}
		set_transient( $limit_key, $hits + 1, MINUTE_IN_SECONDS );

		// The page token must belong to the reported page.
		$path      = sanitize_text_field( (string) $request->get_param( 'path' ) );
		$path_only = (string) wp_parse_url( $path, PHP_URL_PATH );
		$post_id   = '' !== $path_only ? url_to_postid( GEOINS_Stats::path_url( $path_only ) ) : 0;
		if ( ! hash_equals( self::token( $post_id ), (string) $request->get_param( 'token' ) ) ) {
			return new WP_REST_Response( null, 403 );
		}

		GEOINS_Tracker::record_referral( $source, $path );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Page token: an HMAC of the post ID (0 = blog home) with the site's
	 * secret salt. Stable over time, so pages from a full-page cache keep
	 * working, but it cannot be derived without the salt.
	 *
	 * @param int $post_id Post ID of the page.
	 * @return string
	 */
	public static function token( $post_id ) {
		return substr( hash_hmac( 'sha256', 'geoins-beacon|' . (int) $post_id, wp_salt( 'nonce' ) ), 0, 20 );
	}

	/**
	 * Enqueue the beacon for logged-out visitors on content pages (the only
	 * pages a referral can be counted for).
	 *
	 * @return void
	 */
	public static function enqueue() {
		$settings = geoins()->settings();
		if ( empty( $settings['referral_beacon'] ) || empty( $settings['track_referrals'] ) || is_user_logged_in() ) {
			return;
		}
		if ( ! is_singular() && ! is_front_page() && ! is_home() ) {
			return;
		}

		wp_enqueue_script( 'geoins-beacon', GEOINS_URL . 'assets/js/geoins-beacon.js', array(), GEOINS_VERSION, true );

		// Minimal matcher data: slug => { d: domains, u: utm values }.
		$sources = array();
		foreach ( GEOINS_Bots::referral_sources() as $slug => $source ) {
			$sources[ $slug ] = array(
				'd' => array_values( (array) $source['domains'] ),
				'u' => array_values( (array) $source['utm'] ),
			);
		}

		wp_localize_script(
			'geoins-beacon',
			'geoinsBeacon',
			array(
				'endpoint' => esc_url_raw( rest_url( 'geoins/v1/beacon' ) ),
				'host'     => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'token'    => self::token( (int) get_queried_object_id() ),
				'sources'  => $sources,
			)
		);
	}
}
