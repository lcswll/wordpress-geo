<?php
/**
 * REST API for the React dashboard.
 *
 * GET /wp-json/geoins/v1/dashboard?days=30 – everything the dashboard
 * renders, admin-only (cookie auth + X-WP-Nonce, the standard WordPress
 * REST authentication for logged-in users).
 *
 * Lives outside the admin-only includes because REST requests do not run
 * with is_admin() true.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST module.
 */
class GEOINS_Rest {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		$admin_only = static function () {
			return current_user_can( 'manage_options' );
		};
		$editor_up  = static function () {
			return current_user_can( 'edit_others_posts' );
		};

		register_rest_route(
			'geoins/v1',
			'/dashboard',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'dashboard' ),
				'permission_callback' => $admin_only,
				'args'                => array(
					'days'    => array(
						'type'    => 'integer',
						'default' => 30,
					),
					'sources' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			'geoins/v1',
			'/selftest',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return new WP_REST_Response( GEOINS_Selftest::run(), 200 );
				},
				'permission_callback' => $admin_only,
			)
		);

		register_rest_route(
			'geoins/v1',
			'/alerts/read',
			array(
				'methods'             => 'POST',
				'callback'            => static function () {
					GEOINS_Alerts::mark_all_read();
					return new WP_REST_Response( array( 'ok' => true ), 200 );
				},
				'permission_callback' => $admin_only,
			)
		);

		register_rest_route(
			'geoins/v1',
			'/unknown-bots/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $request ) {
					GEOINS_Tracker::dismiss_unknown( (string) $request->get_param( 'key' ) );
					return new WP_REST_Response( array( 'ok' => true ), 200 );
				},
				'permission_callback' => $admin_only,
				'args'                => array(
					'key' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			'geoins/v1',
			'/audit/run',
			array(
				'methods'             => 'POST',
				'callback'            => static function ( $request ) {
					if ( $request->get_param( 'force' ) ) {
						GEOINS_Audit::bump_generation();
					}
					$batch = absint( $request->get_param( 'batch' ) );
					return new WP_REST_Response( GEOINS_Audit::run_batch( $batch ? $batch : 25 ), 200 );
				},
				'permission_callback' => $editor_up,
				'args'                => array(
					'batch' => array(
						'type'    => 'integer',
						'default' => 25,
					),
					'force' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			'geoins/v1',
			'/audit/summary',
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return new WP_REST_Response( GEOINS_Audit::summary(), 200 );
				},
				'permission_callback' => $editor_up,
			)
		);

		register_rest_route(
			'geoins/v1',
			'/audit',
			array(
				'methods'             => 'GET',
				'callback'            => static function ( $request ) {
					$result = GEOINS_Audit::get_rows(
						array(
							'orderby'  => sanitize_key( (string) $request->get_param( 'orderby' ) ),
							'order'    => sanitize_key( (string) $request->get_param( 'order' ) ),
							'type'     => sanitize_key( (string) $request->get_param( 'type' ) ),
							'page'     => absint( $request->get_param( 'page' ) ),
							'per_page' => absint( $request->get_param( 'per_page' ) ),
						)
					);
					$result['cached'] = GEOINS_Audit::cached_posts();
					$result['total']  = GEOINS_Audit::total_posts();
					return new WP_REST_Response( $result, 200 );
				},
				'permission_callback' => $editor_up,
				'args'                => array(
					'orderby'  => array(
						'type'    => 'string',
						'default' => 'score',
					),
					'order'    => array(
						'type'    => 'string',
						'default' => 'asc',
					),
					'type'     => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'     => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'default' => 50,
					),
				),
			)
		);
	}

	/**
	 * Dashboard data for one range.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function dashboard( $request ) {
		$days = absint( $request->get_param( 'days' ) );
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}

		// Optional per-AI filter: comma-separated source slugs, validated
		// against the registries; unknown slugs are dropped.
		$sources = GEOINS_Bots::valid_sources( explode( ',', (string) $request->get_param( 'sources' ) ) );
		if ( empty( $sources ) ) {
			$sources = null;
		}

		$payload                = GEOINS_Stats::collect( $days, $sources );
		$payload['alerts']      = GEOINS_Alerts::get_alerts();
		$payload['unknownBots'] = GEOINS_Tracker::unknown_bots();

		return new WP_REST_Response( $payload, 200 );
	}
}
