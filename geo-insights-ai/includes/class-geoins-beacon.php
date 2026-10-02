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
 * Abuse safeguards, since the endpoint is public by design: a per-client
 * rate limit (hashed IP in a 60-second transient, never stored), paths
 * must resolve to real content on this site, and the same visitor
 * reporting the same page twice within a minute (e.g. beacon next to the
 * PHP tracker on uncached requests) is deduplicated in GEOINS_Tracker.
 *
 * @package GEO_Insights
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
				'permission_callback' => '__return_true', // Public endpoint: logs anonymous stats only, validated below.
				'args'                => array(
					'source' => array(
						'type'     => 'string',
						'required' => true,
					),
					'path'   => array(
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

		// Same-origin check: browsers send Origin on cross-site POSTs.
		$origin = $request->get_header( 'origin' );
		if ( $origin ) {
			$origin_host = (string) wp_parse_url( $origin, PHP_URL_HOST );
			$site_host   = (string) wp_parse_url( home_url(), PHP_URL_HOST );
			if ( 0 !== strcasecmp( $origin_host, $site_host ) ) {
				return new WP_REST_Response( null, 403 );
			}
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

		$path = sanitize_text_field( (string) $request->get_param( 'path' ) );

		GEOINS_Tracker::record_referral( $source, $path );

		return new WP_REST_Response( null, 204 );
	}

	/**
	 * Enqueue the beacon for logged-out visitors.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$settings = geoins()->settings();
		if ( empty( $settings['referral_beacon'] ) || empty( $settings['track_referrals'] ) || is_user_logged_in() ) {
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
				'sources'  => $sources,
			)
		);
	}
}
