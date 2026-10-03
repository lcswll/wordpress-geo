<?php
/**
 * Weekly email report (opt-in).
 *
 * Once a week a compact summary of the site's AI visibility is mailed to
 * the configured recipient: totals with trend, top bots, AI referral
 * sources, most-read pages and any status warnings. Sent only when there
 * was AI activity, so it never spams quiet sites.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly report module.
 */
class GEOINS_Report {

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'geoins_weekly_report', array( __CLASS__, 'cron_send' ) );
	}

	/**
	 * Cron callback: actions discard return values, so wrap maybe_send().
	 *
	 * @return void
	 */
	public static function cron_send() {
		self::maybe_send();
	}

	/**
	 * Cron callback: send the report if enabled and there is data.
	 *
	 * @return bool Whether a mail was sent.
	 */
	public static function maybe_send() {
		$settings = geoins()->settings();
		if ( empty( $settings['weekly_report'] ) ) {
			return false;
		}
		return self::send();
	}

	/**
	 * Build and send the report mail.
	 *
	 * @param bool $force Send even when there was no AI activity.
	 * @return bool Whether a mail was sent.
	 */
	public static function send( $force = false ) {
		$settings = geoins()->settings();
		$data     = GEOINS_Stats::collect( 7 );

		if ( ! $force && 0 === (int) $data['totals']['bots'] && 0 === (int) $data['totals']['referrals'] ) {
			return false; // Nothing happened – skip instead of spamming.
		}

		$recipient = ! empty( $settings['report_recipient'] ) ? $settings['report_recipient'] : get_option( 'admin_email' );
		if ( ! is_email( $recipient ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: number of AI accesses, 3: number of AI visitors. */
			__( '[%1$s] AI visibility this week: %2$d AI accesses, %3$d AI visitors', 'wille-geo-ai-visibility' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			(int) $data['totals']['bots'],
			(int) $data['totals']['referrals']
		);

		$sent = wp_mail(
			$recipient,
			$subject,
			self::render( $data ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		if ( $sent ) {
			update_option( 'geoins_report_last', time(), false );
		}
		return $sent;
	}

	/**
	 * Render the HTML mail body.
	 *
	 * @param array<string,mixed> $data Data from GEOINS_Stats::collect( 7 ).
	 * @return string
	 */
	protected static function render( $data ) {
		$totals = $data['totals'];
		$prev   = $data['totalsPrev'];

		$rows = array(
			array( __( 'Agent (live user request)', 'wille-geo-ai-visibility' ), 'agent' ),
			array( __( 'Retrieval (citation index)', 'wille-geo-ai-visibility' ), 'retrieval' ),
			array( __( 'Search (feeds AI answers)', 'wille-geo-ai-visibility' ), 'search' ),
			array( __( 'Training', 'wille-geo-ai-visibility' ), 'training' ),
			array( __( 'AI visitors', 'wille-geo-ai-visibility' ), 'referrals' ),
		);

		$h  = '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:640px;margin:0 auto;color:#1d2327">';
		$h .= '<h1 style="font-size:20px;margin:24px 0 4px">' . esc_html( get_bloginfo( 'name' ) ) . ' – ' . esc_html__( 'AI visibility, last 7 days', 'wille-geo-ai-visibility' ) . '</h1>';
		$h .= '<p style="color:#646970;margin:0 0 16px">' . esc_html__( 'Which AI read your pages, and which AI answers sent you human visitors.', 'wille-geo-ai-visibility' ) . '</p>';

		// Totals table with trend.
		$h .= '<table style="border-collapse:collapse;width:100%;margin-bottom:20px">';
		foreach ( $rows as $row ) {
			list( $label, $key ) = $row;
			$now_n               = (int) $totals[ $key ];
			$prev_n              = isset( $prev[ $key ] ) ? (int) $prev[ $key ] : 0;
			$h                  .= '<tr>' .
				'<td style="padding:6px 8px;border-bottom:1px solid #f0f0f1">' . esc_html( $label ) . '</td>' .
				'<td style="padding:6px 8px;border-bottom:1px solid #f0f0f1;text-align:right"><strong>' . $now_n . '</strong></td>' .
				'<td style="padding:6px 8px;border-bottom:1px solid #f0f0f1;text-align:right;color:#646970">' . esc_html( self::trend( $now_n, $prev_n ) ) . '</td>' .
				'</tr>';
		}
		$h .= '</table>';

		// Top bots.
		if ( ! empty( $data['topBots'] ) ) {
			$h .= '<h2 style="font-size:15px;margin:20px 0 6px">' . esc_html__( 'Most active AI bots', 'wille-geo-ai-visibility' ) . '</h2><ul style="margin:0;padding-left:18px">';
			foreach ( array_slice( $data['topBots'], 0, 5 ) as $bot ) {
				$h .= '<li style="margin:2px 0">' . esc_html( $bot['label'] ) . ' – <strong>' . (int) $bot['count'] . '</strong>' .
					( ! empty( $bot['failed'] ) ? ' <span style="color:#d63638">(' . (int) $bot['failed'] . ' ' . esc_html__( 'impostors', 'wille-geo-ai-visibility' ) . ')</span>' : '' ) .
					'</li>';
			}
			$h .= '</ul>';
		}

		// Referral sources.
		if ( ! empty( $data['referrals'] ) ) {
			$h .= '<h2 style="font-size:15px;margin:20px 0 6px">' . esc_html__( 'Human visitors from AI answers', 'wille-geo-ai-visibility' ) . '</h2><ul style="margin:0;padding-left:18px">';
			foreach ( array_slice( $data['referrals'], 0, 5 ) as $ref ) {
				$h .= '<li style="margin:2px 0">' . esc_html( $ref['label'] ) . ' – <strong>' . (int) $ref['count'] . '</strong></li>';
			}
			$h .= '</ul>';
		}

		// Most-read pages.
		if ( ! empty( $data['matrix'] ) ) {
			$h .= '<h2 style="font-size:15px;margin:20px 0 6px">' . esc_html__( 'Pages AI read most', 'wille-geo-ai-visibility' ) . '</h2><ul style="margin:0;padding-left:18px">';
			foreach ( array_slice( $data['matrix'], 0, 5 ) as $page ) {
				$h .= '<li style="margin:2px 0"><a href="' . esc_url( $page['url'] ) . '" style="color:#2271b1">' . esc_html( $page['title'] ) . '</a> – <strong>' . (int) $page['count'] . '</strong></li>';
			}
			$h .= '</ul>';
		}

		// Status warnings only.
		$warnings = array();
		foreach ( (array) $data['status'] as $check ) {
			if ( 'ok' !== $check['status'] ) {
				$warnings[] = $check;
			}
		}
		if ( $warnings ) {
			$h .= '<h2 style="font-size:15px;margin:20px 0 6px;color:#996800">' . esc_html__( 'Needs attention', 'wille-geo-ai-visibility' ) . '</h2><ul style="margin:0;padding-left:18px">';
			foreach ( $warnings as $check ) {
				$h .= '<li style="margin:2px 0"><strong>' . esc_html( $check['label'] ) . ':</strong> ' . esc_html( $check['note'] ) . '</li>';
			}
			$h .= '</ul>';
		}

		$h .= '<p style="margin:24px 0"><a href="' . esc_url( admin_url( 'admin.php?page=wille-geo' ) ) . '" style="color:#2271b1">' . esc_html__( 'Open the full dashboard', 'wille-geo-ai-visibility' ) . '</a></p>';
		$h .= '<p style="color:#a7aaad;font-size:12px">' . esc_html__( 'Sent by the Wille GEO plugin. Turn this report off any time under Wille GEO → Settings.', 'wille-geo-ai-visibility' ) . '</p>';
		$h .= '</div>';

		return $h;
	}

	/**
	 * Human trend string vs. the previous period.
	 *
	 * @param int $now  Current count.
	 * @param int $prev Previous count.
	 * @return string
	 */
	protected static function trend( $now, $prev ) {
		if ( $prev <= 0 ) {
			return $now > 0 ? __( 'new', 'wille-geo-ai-visibility' ) : '';
		}
		$pct = (int) round( ( ( $now - $prev ) / $prev ) * 100 );
		if ( 0 === $pct ) {
			return '±0%';
		}
		return ( $pct > 0 ? '+' : '' ) . $pct . '%';
	}
}
