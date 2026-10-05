<?php
/**
 * Optional bot IP verification.
 *
 * AI companies publish the IP ranges their crawlers use. When enabled,
 * this module fetches those lists once a day and marks every logged bot
 * hit as verified (IP inside the official range), failed (claimed
 * identity from a foreign IP = likely impostor) or unchecked (no list
 * available for that bot).
 *
 * The visitor IP is compared in memory only and never stored.
 * This is the only feature that makes external requests – strictly
 * opt-in, one fetch per source per day, of public JSON files.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * IP verification module.
 */
class GEOINS_Verify {

	const UNCHECKED = 2;
	const VERIFIED  = 1;
	const FAILED    = 0;

	/**
	 * Published range files per verification group.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function sources() {
		$sources = array(
			'openai'     => array(
				'bots' => array( 'gptbot', 'oai-searchbot', 'chatgpt-user' ),
				'urls' => array(
					'https://openai.com/gptbot.json',
					'https://openai.com/searchbot.json',
					'https://openai.com/chatgpt-user.json',
				),
			),
			'anthropic'  => array(
				'bots' => array( 'claudebot', 'claude-searchbot', 'claude-user' ),
				'urls' => array( 'https://claude.com/crawling/bots.json' ),
			),
			'perplexity' => array(
				'bots' => array( 'perplexitybot', 'perplexity-user' ),
				'urls' => array(
					'https://www.perplexity.ai/perplexitybot.json',
					'https://www.perplexity.ai/perplexity-user.json',
				),
			),
			'google'     => array(
				'bots' => array( 'googleother' ),
				'urls' => array( 'https://developers.google.com/static/search/apis/ipranges/special-crawlers.json' ),
			),
			'microsoft'  => array(
				'bots' => array( 'bingbot' ),
				'urls' => array( 'https://www.bing.com/toolbox/bingbot.json' ),
			),
		);

		/**
		 * Filters the IP range sources used for bot verification.
		 *
		 * @param array $sources Verification sources.
		 */
		return apply_filters( 'geoins_verify_sources', $sources );
	}

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'geoins_refresh_ip_ranges', array( __CLASS__, 'refresh_ranges' ) );
	}

	/**
	 * Verify the current request for a given bot slug.
	 *
	 * @param string $bot_slug Bot slug.
	 * @return int self::VERIFIED | self::FAILED | self::UNCHECKED
	 */
	public static function verify_current_request( $bot_slug ) {
		$settings = geoins()->settings();
		if ( empty( $settings['verify_bots'] ) ) {
			return self::UNCHECKED;
		}

		$group = null;
		foreach ( self::sources() as $key => $source ) {
			if ( in_array( $bot_slug, $source['bots'], true ) ) {
				$group = $key;
				break;
			}
		}
		if ( null === $group ) {
			return self::UNCHECKED;
		}

		$ranges = get_option( 'geoins_ip_ranges', array() );
		if ( empty( $ranges['groups'][ $group ] ) ) {
			return self::UNCHECKED; // Ranges not fetched (yet).
		}

		$ip = self::client_ip();
		if ( '' === $ip ) {
			return self::UNCHECKED;
		}

		foreach ( $ranges['groups'][ $group ] as $cidr ) {
			if ( self::ip_in_cidr( $ip, $cidr ) ) {
				return self::VERIFIED;
			}
		}
		return self::FAILED;
	}

	/**
	 * The connecting client IP, honoring the proxy-header setting.
	 *
	 * Behind a CDN or reverse proxy, REMOTE_ADDR is the proxy – every
	 * genuine bot would look like an impostor. The site owner declares
	 * their setup via the proxy_header setting; only then is the
	 * respective header trusted (it is spoofable when no proxy sets it).
	 *
	 * @return string Valid IP or empty string.
	 */
	public static function client_ip() {
		$settings = geoins()->settings();
		$mode     = isset( $settings['proxy_header'] ) ? $settings['proxy_header'] : '';
		$ip       = '';

		if ( 'cf' === $mode && isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		} elseif ( 'xff' === $mode && isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// First entry = original client (set by the trusted proxy).
			$parts = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip    = trim( $parts[0] );
		}

		if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return ( '' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) ? $ip : '';
	}

	/**
	 * Cron: fetch and store all published ranges.
	 *
	 * @return void
	 */
	public static function refresh_ranges() {
		$settings = geoins()->settings();
		if ( empty( $settings['verify_bots'] ) ) {
			return;
		}

		$stored = array(
			'fetched' => time(),
			'groups'  => array(),
		);

		foreach ( self::sources() as $group => $source ) {
			$cidrs = array();
			foreach ( $source['urls'] as $url ) {
				$response = wp_remote_get(
					$url,
					array(
						'timeout'    => 10,
						'user-agent' => 'Wille-GEO-WordPress-Plugin/' . GEOINS_VERSION,
					)
				);
				if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
					continue;
				}
				$cidrs = array_merge( $cidrs, self::extract_cidrs( wp_remote_retrieve_body( $response ) ) );
			}
			if ( ! empty( $cidrs ) ) {
				$stored['groups'][ $group ] = array_values( array_unique( $cidrs ) );
			}
		}

		// Keep previous ranges for groups whose fetch failed entirely.
		$previous = get_option( 'geoins_ip_ranges', array() );
		if ( ! empty( $previous['groups'] ) ) {
			foreach ( $previous['groups'] as $group => $cidrs ) {
				if ( empty( $stored['groups'][ $group ] ) ) {
					$stored['groups'][ $group ] = $cidrs;
				}
			}
		}

		update_option( 'geoins_ip_ranges', $stored, false );
	}

	/**
	 * Pull every IPv4/IPv6 CIDR (or bare IP) out of a JSON body.
	 *
	 * Format-agnostic on purpose: the vendors use slightly different JSON
	 * shapes, and this survives future format changes.
	 *
	 * @param string $body Raw response body.
	 * @return string[]
	 */
	public static function extract_cidrs( $body ) {
		$out = array();
		// IPv4 with optional prefix.
		if ( preg_match_all( '/\b(?:\d{1,3}\.){3}\d{1,3}(?:\/\d{1,2})?\b/', $body, $m ) ) {
			$out = $m[0];
		}
		// IPv6 with optional prefix (require "::" or multiple groups to avoid false positives).
		if ( preg_match_all( '/\b(?:[0-9a-fA-F]{1,4}:){2,}[0-9a-fA-F:]*(?:\/\d{1,3})?/', $body, $m6 ) ) {
			$out = array_merge( $out, $m6[0] );
		}

		$valid = array();
		foreach ( $out as $candidate ) {
			$ip_part = $candidate;
			$bits    = null;
			if ( false !== strpos( $candidate, '/' ) ) {
				list( $ip_part, $bits ) = explode( '/', $candidate, 2 );
			}
			if ( false === filter_var( $ip_part, FILTER_VALIDATE_IP ) ) {
				continue;
			}
			if ( null === $bits ) {
				$candidate = $ip_part . ( false !== strpos( $ip_part, ':' ) ? '/128' : '/32' );
			}
			$valid[] = $candidate;
		}
		return $valid;
	}

	/**
	 * Check whether an IP is inside a CIDR range (IPv4 + IPv6).
	 *
	 * @param string $ip   IP address.
	 * @param string $cidr CIDR notation range.
	 * @return bool
	 */
	public static function ip_in_cidr( $ip, $cidr ) {
		if ( false === strpos( $cidr, '/' ) ) {
			return $ip === $cidr;
		}
		list( $subnet, $bits ) = explode( '/', $cidr, 2 );

		// inet_pton() warns on malformed input – validate first instead of silencing it.
		if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) || false === filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		$ip_bin     = inet_pton( $ip );
		$subnet_bin = inet_pton( $subnet );
		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}

		$bits = (int) $bits;
		$max  = strlen( $ip_bin ) * 8;
		if ( $bits < 0 || $bits > $max ) {
			return false;
		}

		$full_bytes = intdiv( $bits, 8 );
		$rest_bits  = $bits % 8;

		if ( $full_bytes > 0 && 0 !== substr_compare( $ip_bin, substr( $subnet_bin, 0, $full_bytes ), 0, $full_bytes ) ) {
			return false;
		}
		if ( 0 === $rest_bits ) {
			return true;
		}
		$mask = 0xFF << ( 8 - $rest_bits ) & 0xFF;
		return ( ord( $ip_bin[ $full_bytes ] ) & $mask ) === ( ord( $subnet_bin[ $full_bytes ] ) & $mask );
	}

	/**
	 * Human-readable info about the stored ranges (for the settings page).
	 *
	 * @return string
	 */
	public static function ranges_info() {
		$ranges = get_option( 'geoins_ip_ranges', array() );
		if ( empty( $ranges['groups'] ) ) {
			return __( 'No IP ranges fetched yet – the first download runs within the next hour (or when the daily cron fires).', 'wille-geo-ai-visibility' );
		}
		$total = 0;
		foreach ( $ranges['groups'] as $cidrs ) {
			$total += count( $cidrs );
		}
		return sprintf(
			/* translators: 1: number of CIDR ranges, 2: number of vendors, 3: date. */
			__( '%1$d IP ranges from %2$d vendors, last updated %3$s.', 'wille-geo-ai-visibility' ),
			$total,
			count( $ranges['groups'] ),
			wp_date( get_option( 'date_format' ) . ' H:i', (int) $ranges['fetched'] )
		);
	}
}
