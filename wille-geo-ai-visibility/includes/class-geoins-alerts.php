<?php
/**
 * Citation alerts: the moments worth celebrating (or investigating).
 *
 *  - A NEW AI source appears for the first time: a bot that never crawled
 *    you before, or first human visitors from an AI assistant.
 *  - A referral spike: yesterday's AI visitors far above the recent norm.
 *
 * Alerts show up in the dashboard; an opt-in email (bundled, max one per
 * day) can notify the site owner. On first run the "seen" list is seeded
 * from existing data, so installing or upgrading never floods alerts.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alerts module.
 */
class GEOINS_Alerts {

	const MAX_ALERTS = 20;

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'geoins_hit_logged', array( __CLASS__, 'on_hit' ), 10, 2 );
		// Piggybacks on the existing daily cron event (no extra schedule).
		add_action( 'geoins_daily_cleanup', array( __CLASS__, 'check_referral_spike' ), 20 );
	}

	/**
	 * First-contact detection on every logged hit.
	 *
	 * @param int    $type    Hit type (1 bot, 2 referral).
	 * @param string $source  Source slug.
	 * @return void
	 */
	public static function on_hit( $type, $source ) {
		$seen = get_option( 'geoins_seen_sources', null );

		// Fallback seeding (normally done eagerly on activate/upgrade):
		// seed silently so historic sources never fire an alert burst.
		if ( ! is_array( $seen ) ) {
			self::maybe_seed();
			$seen = get_option( 'geoins_seen_sources', array() );
		}

		if ( isset( $seen[ $source ] ) ) {
			return;
		}

		// Referrals are unauthenticated and trivially spoofable (bare
		// utm_source), so a single hit must neither fire nor permanently
		// consume the one-shot "first visitors" alert: require a few
		// (dedupe-separated) hits before promoting.
		if ( 2 === (int) $type ) {
			$pending = get_option( 'geoins_pending_ref', array() );
			if ( ! is_array( $pending ) ) {
				$pending = array();
			}
			$pending[ $source ] = isset( $pending[ $source ] ) ? (int) $pending[ $source ] + 1 : 1;
			update_option( 'geoins_pending_ref', $pending, false );
			if ( $pending[ $source ] < 3 ) {
				return;
			}
		}

		// Atomic first-contact claim: add_option() maps to INSERT with a
		// unique key on option_name, so exactly ONE concurrent request wins
		// even when a crawler's first visit arrives as a parallel burst.
		if ( false === add_option( 'geoins_seen_src_' . sanitize_key( $source ), (string) time(), '', false ) ) {
			return;
		}

		$seen[ $source ] = time();
		update_option( 'geoins_seen_sources', $seen, false );

		if ( 2 === (int) $type ) {
			self::add_alert(
				'new_referral',
				array(
					'label' => GEOINS_Bots::label( $source ),
					'slug'  => $source,
				)
			);
			return;
		}

		$bots     = GEOINS_Bots::bots();
		$category = isset( $bots[ $source ] ) ? (int) $bots[ $source ]['category'] : 0;
		self::add_alert(
			'new_bot',
			array(
				'label'    => GEOINS_Bots::label( $source ),
				'slug'     => $source,
				'category' => $category,
				'relevant' => in_array( $category, array( GEOINS_Bots::CAT_AGENT, GEOINS_Bots::CAT_RETRIEVAL, GEOINS_Bots::CAT_SEARCH ), true ) ? 1 : 0,
			)
		);
	}

	/**
	 * Seed the seen-sources map from existing stats data (idempotent).
	 * Called eagerly on activation and on version upgrades, so historic
	 * sources never alert while a fresh install alerts from source #1.
	 *
	 * @return void
	 */
	public static function maybe_seed() {
		if ( ! is_array( get_option( 'geoins_seen_sources', null ) ) ) {
			update_option( 'geoins_seen_sources', self::seed_seen_sources(), false );
		}
	}

	/**
	 * All source slugs already present in the stats tables.
	 *
	 * @return array<string,int> slug => timestamp.
	 */
	protected static function seed_seen_sources() {
		global $wpdb;
		$now  = time();
		$seen = array();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom stats tables, static SQL.
		$slugs = $wpdb->get_col(
			"SELECT DISTINCT source FROM {$wpdb->prefix}geoins_hits UNION SELECT DISTINCT source FROM {$wpdb->prefix}geoins_daily"
		);
		// phpcs:enable
		foreach ( (array) $slugs as $slug ) {
			if ( '' !== $slug ) {
				$seen[ $slug ] = $now;
			}
		}
		return $seen;
	}

	/**
	 * Daily: did yesterday's AI referrals spike vs. the prior week?
	 *
	 * @return void
	 */
	public static function check_referral_spike() {
		$day_start = (int) strtotime( gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) );
		$day_end   = $day_start + DAY_IN_SECONDS;

		$yesterday = 0;
		foreach ( GEOINS_Stats::merged_group( array( 'hit_type' ), $day_start, $day_end, 2 ) as $row ) {
			$yesterday += (int) $row['n'];
		}

		$prior = 0;
		foreach ( GEOINS_Stats::merged_group( array( 'hit_type' ), $day_start - ( 7 * DAY_IN_SECONDS ), $day_start, 2 ) as $row ) {
			$prior += (int) $row['n'];
		}
		$avg = $prior / 7;

		// Spike: at least 10 visitors AND at least 3x the weekly average.
		if ( $yesterday >= 10 && ( $avg < 1 || $yesterday >= 3 * $avg ) ) {
			// One spike alert per day at most (cron can be re-run manually).
			if ( get_transient( 'geoins_spike_alerted' ) ) {
				return;
			}
			set_transient( 'geoins_spike_alerted', 1, DAY_IN_SECONDS );
			self::add_alert(
				'spike',
				array(
					'count' => $yesterday,
					'avg'   => round( $avg, 1 ),
				)
			);
		}
	}

	/**
	 * Store one alert (newest first, capped) and maybe send the email.
	 *
	 * @param string $type Alert type: new_bot | new_referral | spike.
	 * @param array<string,mixed> $data Type-specific payload.
	 * @return void
	 */
	public static function add_alert( $type, $data ) {
		$alerts = get_option( 'geoins_alerts', array() );
		if ( ! is_array( $alerts ) ) {
			$alerts = array();
		}
		array_unshift(
			$alerts,
			array(
				'id'   => substr( md5( $type . wp_json_encode( $data ) . microtime() ), 0, 12 ),
				'time' => time(),
				'type' => $type,
				'data' => $data,
				'read' => 0,
			)
		);
		$alerts = array_slice( $alerts, 0, self::MAX_ALERTS );
		update_option( 'geoins_alerts', $alerts, false );

		self::maybe_email();
	}

	/**
	 * Opt-in email: bundle unread alerts, at most one mail per day.
	 *
	 * @return void
	 */
	protected static function maybe_email() {
		$settings = geoins()->settings();
		if ( empty( $settings['alerts_email'] ) ) {
			return;
		}
		if ( get_transient( 'geoins_alert_mail_lock' ) ) {
			return;
		}

		$alerts = get_option( 'geoins_alerts', array() );
		$unread = array_filter(
			(array) $alerts,
			static function ( $alert ) {
				return empty( $alert['read'] ) && empty( $alert['mailed'] );
			}
		);
		if ( empty( $unread ) ) {
			return;
		}

		$recipient = ! empty( $settings['report_recipient'] ) ? $settings['report_recipient'] : get_option( 'admin_email' );
		if ( ! is_email( $recipient ) ) {
			return;
		}

		// Claim the daily lock BEFORE the (slow) mail call – otherwise every
		// concurrent request in the send window mails its own copy. Released
		// again if the send fails.
		set_transient( 'geoins_alert_mail_lock', 1, DAY_IN_SECONDS );

		$ids   = array();
		$lines = array();
		foreach ( $unread as $alert ) {
			$ids[]   = $alert['id'];
			$lines[] = '• ' . self::alert_text( $alert );
		}

		$body = sprintf(
			/* translators: %s: site name. */
			__( 'Wille GEO on %s noticed:', 'wille-geo-ai-visibility' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		) . "\n\n" . implode( "\n", $lines ) . "\n\n" .
			__( 'Details in the dashboard:', 'wille-geo-ai-visibility' ) . ' ' . admin_url( 'admin.php?page=wille-geo' ) . "\n\n" .
			__( 'You get this because citation alerts by email are enabled under Wille GEO → Settings.', 'wille-geo-ai-visibility' );

		$sent = wp_mail(
			$recipient,
			sprintf(
				/* translators: %s: site name. */
				__( '[%s] New AI visibility signal', 'wille-geo-ai-visibility' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
			),
			$body
		);

		if ( ! $sent ) {
			delete_transient( 'geoins_alert_mail_lock' );
			return;
		}

		// Mark exactly the alerts this mail carried (fresh read; concurrent
		// writes like mark_all_read or new alerts stay intact).
		wp_cache_delete( 'geoins_alerts', 'options' );
		$alerts = get_option( 'geoins_alerts', array() );
		foreach ( (array) $alerts as $i => $alert ) {
			if ( isset( $alert['id'] ) && in_array( $alert['id'], $ids, true ) ) {
				$alerts[ $i ]['mailed'] = 1;
			}
		}
		update_option( 'geoins_alerts', $alerts, false );
	}

	/**
	 * Plain-text rendering of one alert (for the email).
	 *
	 * @param array<string,mixed> $alert Alert row.
	 * @return string
	 */
	public static function alert_text( $alert ) {
		$data = isset( $alert['data'] ) ? (array) $alert['data'] : array();
		switch ( $alert['type'] ) {
			case 'new_referral':
				return sprintf(
					/* translators: %s: AI assistant name. */
					__( 'First human visitors from %s! Your content is being cited there.', 'wille-geo-ai-visibility' ),
					isset( $data['label'] ) ? $data['label'] : '?'
				);
			case 'new_bot':
				if ( ! empty( $data['relevant'] ) ) {
					return sprintf(
						/* translators: %s: bot name. */
						__( '%s crawled your site for the first time – a citation-relevant AI is now reading you.', 'wille-geo-ai-visibility' ),
						isset( $data['label'] ) ? $data['label'] : '?'
					);
				}
				return sprintf(
					/* translators: %s: bot name. */
					__( '%s (training crawler) visited your site for the first time.', 'wille-geo-ai-visibility' ),
					isset( $data['label'] ) ? $data['label'] : '?'
				);
			case 'spike':
				return sprintf(
					/* translators: 1: visitor count, 2: average. */
					__( 'AI visitor spike: %1$d visitors from AI answers yesterday (recent average: %2$s/day).', 'wille-geo-ai-visibility' ),
					isset( $data['count'] ) ? (int) $data['count'] : 0,
					isset( $data['avg'] ) ? $data['avg'] : '0'
				);
		}
		return '';
	}

	/**
	 * Alerts for the dashboard payload.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_alerts() {
		$alerts = get_option( 'geoins_alerts', array() );
		return is_array( $alerts ) ? array_values( $alerts ) : array();
	}

	/**
	 * Mark all alerts as read.
	 *
	 * @return void
	 */
	public static function mark_all_read() {
		$alerts = get_option( 'geoins_alerts', array() );
		if ( ! is_array( $alerts ) ) {
			return;
		}
		foreach ( $alerts as $i => $alert ) {
			$alerts[ $i ]['read'] = 1;
		}
		update_option( 'geoins_alerts', $alerts, false );
	}
}
