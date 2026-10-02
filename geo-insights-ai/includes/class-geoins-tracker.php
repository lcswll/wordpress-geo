<?php
/**
 * Frontend tracker: logs AI bot hits and human AI-referral visits.
 *
 * Privacy by design: no IP addresses, no cookies, no user agents stored –
 * only source, category, page and timestamp. No personal data.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracker.
 */
class GEOINS_Tracker {

	const TYPE_BOT      = 1;
	const TYPE_REFERRAL = 2;

	/**
	 * Hook up.
	 *
	 * Priority 20: after redirect_canonical (10), so a visit that is about
	 * to be 301-redirected to the canonical URL is not logged twice under
	 * two different paths.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_track' ), 20 );
	}

	/**
	 * Decide whether the current request should be logged.
	 *
	 * @return void
	 */
	public static function maybe_track() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_preview() || is_customize_preview() ) {
			return;
		}
		if ( function_exists( 'is_favicon' ) && is_favicon() ) {
			return;
		}
		if ( is_robots() || is_feed() || is_trackback() || is_404() ) {
			return;
		}

		$settings = geoins()->settings();

		// 1) AI bot crawl?
		if ( ! empty( $settings['track_bots'] ) ) {
			$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$match = GEOINS_Bots::match_user_agent( $ua );
			if ( null !== $match ) {
				list( $slug, $bot ) = $match;
				self::log( self::TYPE_BOT, $slug, (int) $bot['category'], GEOINS_Verify::verify_current_request( $slug ) );
				return;
			}

			// 1b) AI radar: an AI-sounding crawler that is NOT in the
			// registry yet? Collect it for the dashboard, so new crawlers
			// surface instead of staying invisible.
			if ( '' !== $ua && self::maybe_record_unknown( $ua ) ) {
				return;
			}
		}

		// 2) Human visitor arriving from an AI answer?
		if ( ! empty( $settings['track_referrals'] ) && ! is_user_logged_in() ) {
			$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only marketing parameter on a public page view.
			$utm = isset( $_GET['utm_source'] ) ? sanitize_text_field( wp_unslash( $_GET['utm_source'] ) ) : '';

			// Ignore internal navigation.
			if ( '' !== $referrer ) {
				$ref_host  = (string) wp_parse_url( $referrer, PHP_URL_HOST );
				$site_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
				if ( 0 === strcasecmp( $ref_host, $site_host ) ) {
					$referrer = '';
				}
			}

			$source = GEOINS_Bots::match_referral( $referrer, $utm );
			if ( null !== $source ) {
				self::log( self::TYPE_REFERRAL, $source, 0 );
			}
		}
	}

	/**
	 * AI radar: record a self-identifying AI-like crawler that is not in
	 * the registry. Conservative on purpose – it only reacts to explicit
	 * AI vocabulary in the user agent, so ordinary SEO/monitoring bots and
	 * browsers never land here. Nothing is stored per visitor: just the
	 * user-agent string with a counter, capped at 50 distinct entries.
	 *
	 * @param string $ua Sanitized User-Agent header.
	 * @return bool Whether the request was recorded as an unknown AI bot.
	 */
	public static function maybe_record_unknown( $ua ) {
		if ( ! preg_match( '/\b(?:gpt|claude|gemini|mistral|llm|assistant|openai|anthropic|perplexity|deepseek|cohere|qwen|grok|ai)\b/i', $ua ) ) {
			return false;
		}

		$key = substr( md5( strtolower( substr( $ua, 0, 160 ) ) ), 0, 12 );

		$dismissed = get_option( 'geoins_unknown_dismissed', array() );
		if ( is_array( $dismissed ) && in_array( $key, $dismissed, true ) ) {
			return true; // Known noise: swallow silently.
		}

		// Site-wide budget: max 30 recordings/minute regardless of UA
		// cardinality – a flood of randomized AI-sounding UAs must not be
		// able to create unbounded transients or option rewrites.
		$budget = (int) get_transient( 'geoins_ub_budget' );
		if ( $budget >= 30 ) {
			return true;
		}

		// Throttle: count each distinct UA at most once per minute.
		if ( get_transient( 'geoins_ub_' . $key ) ) {
			return true;
		}
		set_transient( 'geoins_ub_' . $key, 1, MINUTE_IN_SECONDS );
		set_transient( 'geoins_ub_budget', $budget + 1, MINUTE_IN_SECONDS );

		$unknown = get_option( 'geoins_unknown_bots', array() );
		if ( ! is_array( $unknown ) ) {
			$unknown = array();
		}

		if ( isset( $unknown[ $key ] ) ) {
			++$unknown[ $key ]['count'];
			$unknown[ $key ]['last'] = time();
		} else {
			if ( count( $unknown ) >= 50 ) {
				// Drop the least-seen entry to make room (keys must survive).
				uasort(
					$unknown,
					static function ( $a, $b ) {
						return $a['count'] - $b['count'];
					}
				);
				reset( $unknown );
				unset( $unknown[ key( $unknown ) ] );
			}
			$unknown[ $key ] = array(
				'ua'    => substr( $ua, 0, 160 ),
				'count' => 1,
				'first' => time(),
				'last'  => time(),
			);
		}
		update_option( 'geoins_unknown_bots', $unknown, false );
		return true;
	}

	/**
	 * Unknown AI-like crawlers for the dashboard, most active first.
	 *
	 * @return array<int,array<string,mixed>> Each: { key, ua, count, first, last }.
	 */
	public static function unknown_bots() {
		$unknown = get_option( 'geoins_unknown_bots', array() );
		if ( ! is_array( $unknown ) ) {
			return array();
		}
		$out = array();
		foreach ( $unknown as $key => $entry ) {
			$out[] = array(
				'key'   => (string) $key,
				'ua'    => isset( $entry['ua'] ) ? (string) $entry['ua'] : '',
				'count' => isset( $entry['count'] ) ? (int) $entry['count'] : 0,
				'first' => isset( $entry['first'] ) ? (int) $entry['first'] : 0,
				'last'  => isset( $entry['last'] ) ? (int) $entry['last'] : 0,
			);
		}
		usort(
			$out,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);
		return $out;
	}

	/**
	 * Dismiss one unknown-bot entry (stops recording it again).
	 *
	 * @param string $key Entry key.
	 * @return bool
	 */
	public static function dismiss_unknown( $key ) {
		$key     = sanitize_key( $key );
		$unknown = get_option( 'geoins_unknown_bots', array() );
		if ( is_array( $unknown ) && isset( $unknown[ $key ] ) ) {
			unset( $unknown[ $key ] );
			update_option( 'geoins_unknown_bots', $unknown, false );
		}
		$dismissed = get_option( 'geoins_unknown_dismissed', array() );
		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}
		if ( ! in_array( $key, $dismissed, true ) ) {
			$dismissed[] = $key;
			update_option( 'geoins_unknown_dismissed', array_slice( $dismissed, -100 ), false );
		}
		return true;
	}

	/**
	 * Record an AI referral reported by the cache-proof beacon.
	 *
	 * Path and post ID cannot come from the main query here (REST context),
	 * so the path is passed in explicitly and resolved to a post. Paths
	 * that resolve to nothing on this site are dropped – that bounds the
	 * path cardinality an unauthenticated client can create (the endpoint
	 * is public by design) at the cost of not counting archive pages.
	 *
	 * @param string $source Referral source slug (validated by the caller).
	 * @param string $path   Request path as seen by the visitor's browser.
	 * @return bool Whether the referral was accepted.
	 */
	public static function record_referral( $source, $path ) {
		$path = wp_parse_url( $path, PHP_URL_PATH );
		$path = is_string( $path ) ? substr( $path, 0, 191 ) : '';
		if ( '' === $path || '/' !== $path[0] ) {
			return false;
		}

		$post_id   = url_to_postid( GEOINS_Stats::path_url( $path ) );
		$home_path = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
		$is_home   = trailingslashit( $path ) === $home_path;
		if ( 0 === $post_id && ! $is_home ) {
			return false;
		}

		self::log( self::TYPE_REFERRAL, $source, 0, 2, $path, $post_id );
		return true;
	}

	/**
	 * Log an AI bot hit for the current request outside the main query –
	 * used by the plugin's own AI endpoints (.md, llms.txt, llms-full.txt),
	 * which serve and exit at template_redirect priority 0, before the
	 * regular tracker runs.
	 *
	 * @param int $post_id Post the endpoint content belongs to (0 = none).
	 * @return void
	 */
	public static function track_endpoint_hit( $post_id = 0 ) {
		$settings = geoins()->settings();
		if ( empty( $settings['track_bots'] ) ) {
			return;
		}
		$ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$match = GEOINS_Bots::match_user_agent( $ua );
		if ( null !== $match ) {
			list( $slug, $bot ) = $match;
			self::log( self::TYPE_BOT, $slug, (int) $bot['category'], GEOINS_Verify::verify_current_request( $slug ), null, (int) $post_id );
		}
	}

	/**
	 * Write one row to the hits table.
	 *
	 * @param int         $type     TYPE_BOT or TYPE_REFERRAL.
	 * @param string      $source   Source slug.
	 * @param int         $category Bot category (0 for referrals).
	 * @param int         $verified Verification state (GEOINS_Verify constant).
	 * @param string|null $path     Override path (default: current REQUEST_URI).
	 * @param int|null    $post_id  Override post ID (default: current queried object).
	 * @return void
	 */
	protected static function log( $type, $source, $category, $verified = 2, $path = null, $post_id = null ) {
		global $wpdb;

		if ( null === $post_id ) {
			$post_id = is_singular() ? (int) get_queried_object_id() : 0;
		}

		if ( null === $path ) {
			$path = '';
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$path = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );
				$path = is_string( $path ) ? substr( $path, 0, 191 ) : '';
			}
		}

		// Referral dedupe: ignore the same visitor reporting the same
		// (source, path) again within 60 seconds – catches reloads,
		// back-navigation and the beacon firing next to the PHP tracker on
		// uncached requests, while distinct concurrent visitors still count.
		// The visitor is identified by a salted, expiring hash of IP + user
		// agent; neither value is ever stored – the plugin's no-PII design
		// stays intact (same pattern privacy-first analytics tools use).
		// client_ip() honors the proxy_header setting, so behind a declared
		// CDN/proxy the real visitor IP is used, not the shared proxy IP.
		if ( self::TYPE_REFERRAL === $type ) {
			$ip         = GEOINS_Verify::client_ip();
			$ua         = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
			$dedupe_key = 'geoins_r_' . hash_hmac( 'sha256', $source . '|' . $path . '|' . $ip . '|' . $ua, wp_salt() );
			if ( get_transient( $dedupe_key ) ) {
				return;
			}
			set_transient( $dedupe_key, 1, MINUTE_IN_SECONDS );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom stats table.
		$wpdb->insert(
			$wpdb->prefix . 'geoins_hits',
			array(
				'hit_time' => current_time( 'mysql', true ),
				'hit_type' => (int) $type,
				'source'   => substr( $source, 0, 40 ),
				'category' => (int) $category,
				'post_id'  => (int) $post_id,
				'path'     => $path,
				'verified' => (int) $verified,
			),
			array( '%s', '%d', '%s', '%d', '%d', '%s', '%d' )
		);

		/**
		 * Fires after a hit has been logged.
		 *
		 * @param int    $type    Hit type (1 bot, 2 referral).
		 * @param string $source  Source slug.
		 * @param int    $post_id Post ID or 0.
		 */
		do_action( 'geoins_hit_logged', $type, $source, $post_id );
	}
}
