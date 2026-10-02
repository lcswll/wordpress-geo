<?php
/**
 * Shared statistics query layer.
 *
 * Statistics live in two tables: raw hits (last ~7 days) and a daily
 * aggregate (older, compressed). Every read merges both. Used by the
 * dashboard (AJAX), the weekly email report (cron) and WP-CLI – that is
 * why this lives outside the admin-only code.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Statistics provider.
 */
class GEOINS_Stats {

	/**
	 * Columns merged_group() may group by (present in both tables; 'day' is
	 * DATE(hit_time) on the raw table).
	 */
	const GROUP_COLUMNS = array( 'day', 'hit_type', 'source', 'category', 'post_id', 'path', 'verified' );

	/**
	 * Run the same GROUP BY over the raw and the aggregate table and merge
	 * the results by composite key, summing the counts.
	 *
	 * @param string[]      $keys     Column names to group by (subset of GROUP_COLUMNS; others are ignored).
	 * @param int           $from_ts  Range start (unix, UTC).
	 * @param int           $to_ts    Range end (unix, UTC).
	 * @param int           $hit_type Optional hit-type filter (1 bot, 2 referral; 0 = all).
	 * @param string[]|null $sources  Optional source-slug filter (validated by the caller).
	 * @return array<int,array<string,mixed>> Rows as associative arrays with the keys + 'n' (int).
	 */
	public static function merged_group( $keys, $from_ts, $to_ts, $hit_type = 0, $sources = null ) {
		global $wpdb;

		// Identifiers come from a fixed allow-list and go through %i.
		$keys = array_values( array_intersect( array_values( array_unique( $keys ) ), self::GROUP_COLUMNS ) );
		if ( empty( $keys ) ) {
			return array();
		}
		$by_day = in_array( 'day', $keys, true );
		$cols   = array_values( array_diff( $keys, array( 'day' ) ) );

		$hits     = $wpdb->prefix . 'geoins_hits';
		$daily    = $wpdb->prefix . 'geoins_daily';
		$hit_type = (int) $hit_type;

		// Optional per-AI filter as prepared IN(...) placeholders. Without a
		// filter the "0 = 0 OR ..." branch is constant-folded by MySQL, so the
		// dummy list entry never matters.
		$filter_sources = is_array( $sources ) && ! empty( $sources );
		$source_list    = $filter_sources ? array_values( array_map( 'strval', $sources ) ) : array( '' );

		$raw_range   = array( gmdate( 'Y-m-d H:i:s', $from_ts ), gmdate( 'Y-m-d H:i:s', $to_ts ) );
		$filter_args = array_merge( array( $hit_type, $hit_type, (int) $filter_sources ), $source_list );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom stats tables; live dashboard data.
		if ( $by_day ) {
			$raw_rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT DATE(hit_time) AS day, ' . implode( '', array_fill( 0, count( $cols ), '%i, ' ) ) . 'COUNT(*) AS n FROM %i WHERE hit_time >= %s AND hit_time < %s AND ( 0 = %d OR hit_type = %d ) AND ( 0 = %d OR source IN (' . implode( ',', array_fill( 0, count( $source_list ), '%s' ) ) . ') ) GROUP BY DATE(hit_time)' . implode( '', array_fill( 0, count( $cols ), ', %i' ) ),
					array_merge( $cols, array( $hits ), $raw_range, $filter_args, $cols )
				),
				ARRAY_A
			);
		} else {
			$raw_rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT ' . implode( '', array_fill( 0, count( $cols ), '%i, ' ) ) . 'COUNT(*) AS n FROM %i WHERE hit_time >= %s AND hit_time < %s AND ( 0 = %d OR hit_type = %d ) AND ( 0 = %d OR source IN (' . implode( ',', array_fill( 0, count( $source_list ), '%s' ) ) . ') ) GROUP BY ' . implode( ', ', array_fill( 0, count( $cols ), '%i' ) ),
					array_merge( $cols, array( $hits ), $raw_range, $filter_args, $cols )
				),
				ARRAY_A
			);
		}
		// Daily rows carry whole calendar days, so the lower bound moves to
		// the first FULL day inside the window: consecutive windows (current
		// vs. previous period) then partition the days instead of both
		// counting the boundary day, and the totals match the daily series.
		// The "-1" keeps a midnight-aligned from_ts on its own (fully
		// covered) day instead of skipping it – mid-day values are unchanged.
		$daily_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT ' . implode( '', array_fill( 0, count( $keys ), '%i, ' ) ) . 'SUM(n) AS n FROM %i WHERE day >= %s AND day < %s AND ( 0 = %d OR hit_type = %d ) AND ( 0 = %d OR source IN (' . implode( ',', array_fill( 0, count( $source_list ), '%s' ) ) . ') ) GROUP BY ' . implode( ', ', array_fill( 0, count( $keys ), '%i' ) ),
				array_merge(
					$keys,
					array( $daily, gmdate( 'Y-m-d', $from_ts + DAY_IN_SECONDS - 1 ), gmdate( 'Y-m-d', $to_ts + DAY_IN_SECONDS ) ),
					$filter_args,
					$keys
				)
			),
			ARRAY_A
		);
		// phpcs:enable

		$merged = array();
		foreach ( array_merge( (array) $raw_rows, (array) $daily_rows ) as $row ) {
			$id_parts = array();
			foreach ( $keys as $key ) {
				$id_parts[] = (string) $row[ $key ];
			}
			$id = implode( '|', $id_parts );
			if ( ! isset( $merged[ $id ] ) ) {
				$merged[ $id ]      = $row;
				$merged[ $id ]['n'] = 0;
			}
			$merged[ $id ]['n'] += (int) $row['n'];
		}
		return array_values( $merged );
	}

	/**
	 * Totals keyed by category (+ referrals) for a time window.
	 *
	 * @param int           $from_ts Start.
	 * @param int           $to_ts   End.
	 * @param string[]|null $sources Optional source filter.
	 * @return array<string,int>
	 */
	public static function totals( $from_ts, $to_ts, $sources = null ) {
		$cat_keys = self::category_keys();
		$totals   = array(
			'bots'      => 0,
			'referrals' => 0,
			'training'  => 0,
			'retrieval' => 0,
			'agent'     => 0,
			'search'    => 0,
		);
		foreach ( self::merged_group( array( 'hit_type', 'category' ), $from_ts, $to_ts, 0, $sources ) as $row ) {
			if ( 2 === (int) $row['hit_type'] ) {
				$totals['referrals'] += (int) $row['n'];
			} else {
				$totals['bots'] += (int) $row['n'];
				$key             = isset( $cat_keys[ (int) $row['category'] ] ) ? $cat_keys[ (int) $row['category'] ] : null;
				if ( $key ) {
					$totals[ $key ] += (int) $row['n'];
				}
			}
		}
		return $totals;
	}

	/**
	 * Category id => dashboard key map.
	 *
	 * @return array<int,'training'|'retrieval'|'agent'|'search'>
	 */
	public static function category_keys() {
		return array(
			GEOINS_Bots::CAT_TRAINING  => 'training',
			GEOINS_Bots::CAT_RETRIEVAL => 'retrieval',
			GEOINS_Bots::CAT_AGENT     => 'agent',
			GEOINS_Bots::CAT_SEARCH    => 'search',
		);
	}

	/**
	 * Absolute URL for a stored request path.
	 *
	 * Stored paths come from REQUEST_URI and already include a subdirectory
	 * base (e.g. /blog/my-post/), so home_url( $path ) would double the
	 * prefix on subdirectory installs. This prepends scheme + host only.
	 *
	 * @param string $path Stored request path.
	 * @return string
	 */
	public static function path_url( $path ) {
		$home = wp_parse_url( home_url() );
		$url  = ( isset( $home['scheme'] ) ? $home['scheme'] : 'https' ) . '://' . ( isset( $home['host'] ) ? $home['host'] : '' );
		if ( ! empty( $home['port'] ) ) {
			$url .= ':' . $home['port'];
		}
		return $url . ( '' !== $path ? $path : '/' );
	}

	/**
	 * Collect all dashboard/report data for a range.
	 *
	 * @param int           $days    Range in days.
	 * @param string[]|null $sources Optional source-slug filter (validate via
	 *                               GEOINS_Bots::valid_sources() before passing).
	 * @return array<string,mixed>
	 */
	public static function collect( $days, $sources = null ) {
		$now      = time();
		$from     = $now - ( $days * DAY_IN_SECONDS );
		$cat_keys = self::category_keys();

		// Titles pass through wptexturize/convert_chars and arrive as HTML
		// entities (&#8217; etc.). The React app renders text nodes without
		// HTML parsing, so decode here; JSX escapes safely on output.
		$decode = static function ( $s ) {
			return html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		};

		// Totals: current + previous period (for the delta arrows).
		$totals      = self::totals( $from, $now, $sources );
		$totals_prev = self::totals( $from - ( $days * DAY_IN_SECONDS ), $from, $sources );

		// Daily series.
		$series = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$d            = gmdate( 'Y-m-d', $now - ( $i * DAY_IN_SECONDS ) );
			$series[ $d ] = array(
				'date'      => $d,
				'training'  => 0,
				'retrieval' => 0,
				'agent'     => 0,
				'search'    => 0,
				'referral'  => 0,
			);
		}
		foreach ( self::merged_group( array( 'day', 'hit_type', 'category' ), $from, $now, 0, $sources ) as $row ) {
			$day = (string) $row['day'];
			if ( ! isset( $series[ $day ] ) ) {
				continue;
			}
			if ( 2 === (int) $row['hit_type'] ) {
				$series[ $day ]['referral'] += (int) $row['n'];
			} else {
				$key = isset( $cat_keys[ (int) $row['category'] ] ) ? $cat_keys[ (int) $row['category'] ] : null;
				if ( $key ) {
					$series[ $day ][ $key ] += (int) $row['n'];
				}
			}
		}

		// Bots: one grouped query for top list + verification stats.
		$bot_totals   = array();
		$verification = array(
			'enabled'   => (int) ! empty( geoins()->settings()['verify_bots'] ),
			'verified'  => 0,
			'failed'    => 0,
			'unchecked' => 0,
		);
		foreach ( self::merged_group( array( 'source', 'category', 'verified' ), $from, $now, 1, $sources ) as $row ) {
			$source = (string) $row['source'];
			if ( ! isset( $bot_totals[ $source ] ) ) {
				$key                   = isset( $cat_keys[ (int) $row['category'] ] ) ? $cat_keys[ (int) $row['category'] ] : 'training';
				$bot_totals[ $source ] = array(
					'source'   => $source,
					'label'    => GEOINS_Bots::label( $source ),
					'category' => $key,
					'count'    => 0,
					'failed'   => 0,
				);
			}
			$bot_totals[ $source ]['count'] += (int) $row['n'];
			switch ( (int) $row['verified'] ) {
				case GEOINS_Verify::VERIFIED:
					$verification['verified'] += (int) $row['n'];
					break;
				case GEOINS_Verify::FAILED:
					$verification['failed']          += (int) $row['n'];
					$bot_totals[ $source ]['failed'] += (int) $row['n'];
					break;
				default:
					$verification['unchecked'] += (int) $row['n'];
			}
		}
		usort(
			$bot_totals,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);
		$top_bots = array_slice( $bot_totals, 0, 15 );

		// Uncapped compact list (source + count only) so the dashboard's
		// per-AI filter chips can cover companies below the top-15 display cap.
		$bot_sources = array();
		foreach ( $bot_totals as $bot ) {
			$bot_sources[] = array(
				'source' => $bot['source'],
				'count'  => $bot['count'],
			);
		}

		// Referral breakdown.
		$referrals = array();
		foreach ( self::merged_group( array( 'source' ), $from, $now, 2, $sources ) as $row ) {
			$referrals[] = array(
				'source' => $row['source'],
				'label'  => GEOINS_Bots::label( $row['source'] ),
				'count'  => (int) $row['n'],
			);
		}
		usort(
			$referrals,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		// Term matrix: single grouped query, assembled in PHP (no N+1).
		$pages = array();
		foreach ( self::merged_group( array( 'post_id', 'path', 'source' ), $from, $now, 1, $sources ) as $row ) {
			$page_id = $row['post_id'] . '|' . $row['path'];
			if ( ! isset( $pages[ $page_id ] ) ) {
				$pages[ $page_id ] = array(
					'post_id' => (int) $row['post_id'],
					'path'    => (string) $row['path'],
					'count'   => 0,
					'sources' => array(),
				);
			}
			$pages[ $page_id ]['count']                    += (int) $row['n'];
			$pages[ $page_id ]['sources'][ $row['source'] ] = ( isset( $pages[ $page_id ]['sources'][ $row['source'] ] ) ? $pages[ $page_id ]['sources'][ $row['source'] ] : 0 ) + (int) $row['n'];
		}
		usort(
			$pages,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);
		$matrix = array();
		foreach ( array_slice( $pages, 0, 15 ) as $page ) {
			$post_id = $page['post_id'];
			if ( $post_id && ! get_post( $post_id ) ) {
				$post_id = 0; // Post deleted after hits were recorded: fall back to the path.
			}
			arsort( $page['sources'] );
			$chips = array(); // NOT named $sources: that is the filter parameter.
			foreach ( array_slice( $page['sources'], 0, 6, true ) as $slug => $count ) {
				$chips[] = array(
					'slug'  => $slug,
					'label' => GEOINS_Bots::label( $slug ),
					'count' => $count,
				);
			}
			$matrix[] = array(
				'post_id' => $post_id,
				'title'   => $post_id ? $decode( get_the_title( $post_id ) ) : $page['path'],
				'keyword' => $post_id ? $decode( GEOINS_Analysis::keyword( $post_id ) ) : $page['path'],
				'url'     => $post_id ? get_permalink( $post_id ) : self::path_url( $page['path'] ),
				'edit'    => $post_id ? get_edit_post_link( $post_id, 'raw' ) : '',
				'count'   => $page['count'],
				'sources' => $chips,
			);
		}

		// Referral landing pages.
		$landing_rows = self::merged_group( array( 'post_id', 'path', 'source' ), $from, $now, 2, $sources );
		usort(
			$landing_rows,
			static function ( $a, $b ) {
				return (int) $b['n'] - (int) $a['n'];
			}
		);
		$landings = array();
		foreach ( array_slice( $landing_rows, 0, 10 ) as $row ) {
			$post_id = (int) $row['post_id'];
			if ( $post_id && ! get_post( $post_id ) ) {
				$post_id = 0; // Post deleted after hits were recorded: fall back to the path.
			}
			$landings[] = array(
				'title'  => $post_id ? $decode( get_the_title( $post_id ) ) : $row['path'],
				'url'    => $post_id ? get_permalink( $post_id ) : self::path_url( $row['path'] ),
				'source' => GEOINS_Bots::label( $row['source'] ),
				'count'  => (int) $row['n'],
			);
		}

		return array(
			'days'         => $days,
			'totals'       => $totals,
			'totalsPrev'   => $totals_prev,
			'series'       => array_values( $series ),
			'topBots'      => $top_bots,
			'botSources'   => $bot_sources,
			'heatmap'      => self::heatmap( $sources ),
			'referrals'    => $referrals,
			'matrix'       => $matrix,
			'landings'     => $landings,
			'verification' => $verification,
			'status'       => geoins()->status_checks(),
		);
	}

	/**
	 * Bot hits by weekday x hour over the last 7 days (raw table), shifted
	 * into the site timezone – for the "when do AIs read your site?" heatmap.
	 * Grouped in SQL per UTC hour (max 168 buckets), converted in PHP.
	 *
	 * @param string[]|null $sources Optional source filter.
	 * @return array<int,array{0:int,1:int,2:int}> Each: [ weekday 0=Mon..6=Sun, hour 0-23, count ].
	 */
	public static function heatmap( $sources = null ) {
		global $wpdb;

		// Same constant-folded optional IN(...) filter as merged_group().
		$filter_sources = is_array( $sources ) && ! empty( $sources );
		$source_list    = $filter_sources ? array_values( array_map( 'strval', $sources ) ) : array( '' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom stats table; live dashboard data.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(hit_time, '%%Y-%%m-%%d %%H') AS bucket, COUNT(*) AS n FROM %i WHERE hit_type = 1 AND hit_time >= %s AND ( 0 = %d OR source IN (" . implode( ',', array_fill( 0, count( $source_list ), '%s' ) ) . ') ) GROUP BY bucket',
				array_merge(
					array( $wpdb->prefix . 'geoins_hits', gmdate( 'Y-m-d H:i:s', time() - ( 7 * DAY_IN_SECONDS ) ), (int) $filter_sources ),
					$source_list
				)
			),
			ARRAY_A
		);
		// phpcs:enable

		$tz   = wp_timezone();
		$grid = array_fill( 0, 7, array_fill( 0, 24, 0 ) );
		foreach ( (array) $rows as $row ) {
			$dt = DateTime::createFromFormat( 'Y-m-d H', (string) $row['bucket'], new DateTimeZone( 'UTC' ) );
			if ( ! $dt ) {
				continue;
			}
			$dt->setTimezone( $tz );
			$grid[ (int) $dt->format( 'N' ) - 1 ][ (int) $dt->format( 'G' ) ] += (int) $row['n'];
		}

		$out = array();
		foreach ( $grid as $weekday => $hours ) {
			foreach ( $hours as $hour => $n ) {
				if ( $n > 0 ) {
					$out[] = array( $weekday, $hour, $n );
				}
			}
		}
		return $out;
	}

	/**
	 * Bot-hit counts per post for a set of post IDs (posts-list column).
	 *
	 * @param int[] $post_ids Post IDs.
	 * @param int   $days     Range in days.
	 * @return array<int,int> post_id => count.
	 */
	public static function counts_for_posts( $post_ids, $days = 30 ) {
		global $wpdb;

		$post_ids = array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );
		if ( empty( $post_ids ) ) {
			return array();
		}

		$from = time() - ( $days * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom stats tables; placeholders built from a counted array.
		$raw = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, COUNT(*) AS n FROM %i WHERE hit_type = 1 AND hit_time >= %s AND post_id IN (' . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ') GROUP BY post_id',
				array_merge( array( $wpdb->prefix . 'geoins_hits', gmdate( 'Y-m-d H:i:s', $from ) ), $post_ids )
			),
			ARRAY_A
		);
		$agg = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, SUM(n) AS n FROM %i WHERE hit_type = 1 AND day >= %s AND post_id IN (' . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ') GROUP BY post_id',
				array_merge( array( $wpdb->prefix . 'geoins_daily', gmdate( 'Y-m-d', $from + DAY_IN_SECONDS ) ), $post_ids )
			),
			ARRAY_A
		);
		// phpcs:enable

		$counts = array();
		foreach ( array_merge( (array) $raw, (array) $agg ) as $row ) {
			$id            = (int) $row['post_id'];
			$counts[ $id ] = ( isset( $counts[ $id ] ) ? $counts[ $id ] : 0 ) + (int) $row['n'];
		}
		return $counts;
	}
}
