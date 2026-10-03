<?php
/**
 * robots.txt control for AI crawlers.
 *
 * Appends per-bot Disallow rules to WordPress' virtual robots.txt.
 * Only works when no physical robots.txt file exists in the web root –
 * but instead of blanket-warning about a physical file, the status
 * panel analyzes it: a physical file that blocks no citation-relevant
 * AI bots is reported as fine.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Robots.txt module.
 */
class GEOINS_Robots {

	/**
	 * Cached physical-file analysis (per request).
	 *
	 * @var array{exists:bool,readable:bool,blocked_all:bool,blocked:string[],has_exceptions:bool}|null
	 */
	protected static $analysis = null;

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'filter_robots' ), 10, 2 );
	}

	/**
	 * Append AI bot rules.
	 *
	 * @param string $output    Current robots.txt output.
	 * @param bool   $is_public Whether the site is public.
	 * @return string
	 */
	public static function filter_robots( $output, $is_public ) {
		if ( ! $is_public ) {
			return $output;
		}

		$settings = geoins()->settings();
		if ( empty( $settings['robots_control'] ) || empty( $settings['blocked_bots'] ) ) {
			return $output;
		}

		$bots  = GEOINS_Bots::bots();
		$rules = '';
		foreach ( (array) $settings['blocked_bots'] as $slug ) {
			if ( isset( $bots[ $slug ] ) ) {
				$rules .= "\nUser-agent: " . $bots[ $slug ]['token'] . "\nDisallow: /\n";
			}
		}

		if ( '' !== $rules ) {
			$output .= "\n# Wille GEO: AI crawler rules" . $rules;
		}

		return $output;
	}

	/**
	 * Path of the robots.txt crawlers actually fetch, or null when it is
	 * outside WordPress' control (subdirectory install).
	 *
	 * Handles "WordPress in its own directory" / Bedrock layouts, where
	 * ABSPATH is the core directory below the served document root.
	 *
	 * @return string|null
	 */
	protected static function robots_path() {
		$home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		if ( '' !== $home_path && '/' !== $home_path ) {
			return null; // Subdirectory install: /robots.txt is outside WP's control anyway.
		}
		$abspath   = untrailingslashit( wp_normalize_path( ABSPATH ) );
		$site_path = trim( (string) wp_parse_url( site_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $site_path && substr( $abspath, -strlen( '/' . $site_path ) ) === '/' . $site_path ) {
			// Core lives in a subdirectory of the document root – strip it.
			$abspath = substr( $abspath, 0, -strlen( '/' . $site_path ) );
		}
		return $abspath . '/robots.txt';
	}

	/**
	 * Whether a physical robots.txt file overrides the virtual one.
	 *
	 * @return bool
	 */
	public static function physical_file_exists() {
		$path = self::robots_path();
		return null !== $path && @file_exists( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Analyze robots.txt content: which known AI bots does it fully block?
	 *
	 * Pragmatic model of the robots exclusion protocol, scoped to the one
	 * decision that matters here – full-site blocks: a bot counts as
	 * blocked when its matching group(s) contain "Disallow: /" and no
	 * "Allow: /". Partial disallows (e.g. /wp-admin/) are not blocks.
	 * Bots without a specific group inherit the wildcard (*) groups.
	 *
	 * @param string $content robots.txt content.
	 * @return array{blocked_all:bool,blocked:string[],has_exceptions:bool} blocked = registry slugs.
	 */
	public static function analyze_content( $content ) {
		// Editors (Windows Notepad!) prepend a UTF-8 BOM that would glue to
		// the first "User-agent" line; real crawlers ignore it, so must we.
		$content = (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $content );

		$groups     = array();
		$current    = null;
		$last_agent = false;

		$lines = preg_split( '/\r\n|\r|\n/', $content );
		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $line || ! preg_match( '/^([a-z\-]+)\s*:\s*(.*)$/i', $line, $m ) ) {
				continue;
			}
			$directive = strtolower( $m[1] );
			$value     = trim( $m[2] );

			if ( 'user-agent' === $directive ) {
				if ( ! $last_agent || null === $current ) {
					if ( null !== $current ) {
						$groups[] = $current;
					}
					$current = array(
						'agents'       => array(),
						'disallow_all' => false,
						'allow_all'    => false,
					);
				}
				// Normalize like the reference REP parser: keep the leading
				// token characters only, so "GPTBot/1.0" matches "GPTBot".
				$agent = strtolower( $value );
				if ( '*' !== $agent ) {
					$agent = (string) preg_replace( '/[^a-z0-9_\-].*$/', '', $agent );
				}
				$current['agents'][] = $agent;
				$last_agent          = true;
				continue;
			}

			if ( null === $current ) {
				continue; // Rules before any User-agent line: ignore.
			}
			$last_agent = false;
			// "/", "/*" and "*" all match every URL under REP wildcard semantics.
			if ( 'disallow' === $directive && in_array( $value, array( '/', '/*', '*' ), true ) ) {
				$current['disallow_all'] = true;
			}
			if ( 'allow' === $directive && in_array( $value, array( '/', '/*', '*' ), true ) ) {
				$current['allow_all'] = true;
			}
		}
		if ( null !== $current ) {
			$groups[] = $current;
		}

		$blocked_for = static function ( $agent ) use ( $groups ) {
			$has_specific = false;
			$disallow     = false;
			$allow        = false;
			foreach ( $groups as $group ) {
				if ( in_array( $agent, $group['agents'], true ) ) {
					$has_specific = true;
					$disallow     = $disallow || $group['disallow_all'];
					$allow        = $allow || $group['allow_all'];
				}
			}
			if ( ! $has_specific ) {
				foreach ( $groups as $group ) {
					if ( in_array( '*', $group['agents'], true ) ) {
						$disallow = $disallow || $group['disallow_all'];
						$allow    = $allow || $group['allow_all'];
					}
				}
			}
			return $disallow && ! $allow;
		};

		$blocked = array();
		foreach ( GEOINS_Bots::bots() as $slug => $bot ) {
			if ( $blocked_for( strtolower( $bot['token'] ) ) ) {
				$blocked[] = $slug;
			}
		}

		// Named groups that are NOT full blocks are exceptions to a wildcard
		// block – "blocks everything" wording must account for them.
		$has_exceptions = false;
		foreach ( $groups as $group ) {
			foreach ( $group['agents'] as $agent ) {
				if ( '*' !== $agent && ( ! $group['disallow_all'] || $group['allow_all'] ) ) {
					$has_exceptions = true;
				}
			}
		}

		return array(
			'blocked_all'    => $blocked_for( '*' ),
			'blocked'        => $blocked,
			'has_exceptions' => $has_exceptions,
		);
	}

	/**
	 * Read + analyze the physical robots.txt (cached per request).
	 *
	 * @return array{exists:bool,readable:bool,blocked_all:bool,blocked:string[],has_exceptions:bool}
	 */
	public static function physical_analysis() {
		if ( null !== self::$analysis ) {
			return self::$analysis;
		}

		self::$analysis = array(
			'exists'         => false,
			'readable'       => false,
			'blocked_all'    => false,
			'blocked'        => array(),
			'has_exceptions' => false,
		);

		$path = self::robots_path();
		if ( null === $path || ! self::physical_file_exists() ) {
			return self::$analysis;
		}
		self::$analysis['exists'] = true;

		// RFC 9309 / Google process robots.txt up to 500 KiB; a file larger
		// than that cannot be analyzed safely (a truncated read could hide
		// AI-block groups appended at the end) -> "check it manually" warn.
		$max_len = 512 * KB_IN_BYTES;
		$content = @file_get_contents( $path, false, null, 0, $max_len + 1 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content || strlen( $content ) > $max_len ) {
			return self::$analysis;
		}
		$parsed         = self::analyze_content( $content );
		self::$analysis = array(
			'exists'         => true,
			'readable'       => true,
			'blocked_all'    => $parsed['blocked_all'],
			'blocked'        => $parsed['blocked'],
			'has_exceptions' => $parsed['has_exceptions'],
		);

		return self::$analysis;
	}

	/**
	 * Status + note for the physical robots.txt, shared by the status
	 * panel and the settings page.
	 *
	 * @return array{status:string,note:string} status: ok|warn|bad.
	 */
	public static function physical_summary() {
		$analysis = self::physical_analysis();
		$settings = geoins()->settings();
		$bots     = GEOINS_Bots::bots();

		if ( ! $analysis['exists'] ) {
			if ( empty( $settings['robots_control'] ) || empty( $settings['blocked_bots'] ) ) {
				return array(
					'status' => 'ok',
					'note'   => __( 'No physical robots.txt file found. No AI crawler blocks are configured in Wille GEO, so the WordPress default robots.txt is served unchanged.', 'wille-geo-ai-visibility' ),
				);
			}
			return array(
				'status' => 'ok',
				'note'   => __( 'No physical robots.txt file found – the AI crawler rules configured in Wille GEO are active.', 'wille-geo-ai-visibility' ),
			);
		}

		if ( ! $analysis['readable'] ) {
			return array(
				'status' => 'warn',
				'note'   => __( 'A physical robots.txt file exists but could not be read for analysis. It overrides the rules configured here – check it manually.', 'wille-geo-ai-visibility' ),
			);
		}

		if ( $analysis['blocked_all'] ) {
			return array(
				'status' => 'bad',
				'note'   => $analysis['has_exceptions']
					? __( 'The physical robots.txt blocks all crawlers by default (User-agent: * / Disallow: /); only crawlers with their own exception rules in the file can read your site. Every AI system without an exception is locked out – edit the file.', 'wille-geo-ai-visibility' )
					: __( 'The physical robots.txt blocks ALL crawlers (User-agent: * / Disallow: /). No search engine and no AI system can read your site – edit the file.', 'wille-geo-ai-visibility' ),
			);
		}

		// Citation-relevant bots blocked by the file?
		$risky = array();
		foreach ( $analysis['blocked'] as $slug ) {
			if ( isset( $bots[ $slug ] ) && in_array( (int) $bots[ $slug ]['category'], array( GEOINS_Bots::CAT_RETRIEVAL, GEOINS_Bots::CAT_AGENT, GEOINS_Bots::CAT_SEARCH ), true ) ) {
				$risky[] = $bots[ $slug ]['label'];
			}
		}
		if ( $risky ) {
			return array(
				'status' => 'warn',
				'note'   => sprintf(
					/* translators: %s: comma-separated bot names. */
					__( 'The physical robots.txt blocks %s. These bots put you into AI answers – edit the file to unblock them (the rules configured here stay inactive while the file exists).', 'wille-geo-ai-visibility' ),
					implode( ', ', $risky )
				),
			);
		}

		// Blocks the user configured here but that are missing from the file?
		$missing = array();
		if ( ! empty( $settings['robots_control'] ) ) {
			foreach ( (array) $settings['blocked_bots'] as $slug ) {
				if ( isset( $bots[ $slug ] ) && ! in_array( $slug, $analysis['blocked'], true ) ) {
					$missing[] = $bots[ $slug ]['label'];
				}
			}
		}
		if ( $missing ) {
			return array(
				'status' => 'warn',
				'note'   => sprintf(
					/* translators: %s: comma-separated bot names. */
					__( 'A physical robots.txt exists and does not contain the blocks you configured here (%s) – they have no effect until you add them to the file or delete it.', 'wille-geo-ai-visibility' ),
					implode( ', ', $missing )
				),
			);
		}

		return array(
			'status' => 'ok',
			'note'   => __( 'A physical robots.txt file exists, but it is currently fine: it blocks no citation-relevant AI bots and matches your configuration. Note: the rules configured here stay inactive while the file exists.', 'wille-geo-ai-visibility' ),
		);
	}
}
