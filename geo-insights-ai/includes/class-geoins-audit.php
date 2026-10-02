<?php
/**
 * Site-wide GEO audit: the 13-point check for EVERY published post and
 * page, cached per post and served as a sortable table – so you see at a
 * glance which content is citation-ready and which needs work, next to
 * the actual AI interest it gets.
 *
 * Scores are computed in small batches (REST-driven) and cached in post
 * meta; editing a post invalidates its cached score. Adding new checks
 * in a future version invalidates automatically via the stored check
 * count.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit module.
 */
class GEOINS_Audit {

	const META_SCORE = '_geoins_score';
	const META_FAILS = '_geoins_score_fails';
	const META_V     = '_geoins_score_v';

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'invalidate' ) );
	}

	/**
	 * Drop the cached score when a post changes.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function invalidate( $post_id ) {
		delete_post_meta( $post_id, self::META_SCORE );
		delete_post_meta( $post_id, self::META_FAILS );
		delete_post_meta( $post_id, self::META_V );
	}

	/**
	 * Current cache version: check-set size + a manual scan generation, so
	 * both new checks and an explicit "Re-scan" invalidate every cache.
	 *
	 * @return string
	 */
	public static function checks_version() {
		return count( GEOINS_Analysis::definitions() ) . '.' . (int) get_option( 'geoins_audit_gen', 1 );
	}

	/**
	 * Force a full re-scan: bump the generation so every cached score is
	 * considered stale (time- and author-dependent checks refresh too).
	 *
	 * @return void
	 */
	public static function bump_generation() {
		update_option( 'geoins_audit_gen', (int) get_option( 'geoins_audit_gen', 1 ) + 1, false );
	}

	/**
	 * Total number of published, auditable posts.
	 *
	 * @return int
	 */
	public static function total_posts() {
		$counts = 0;
		foreach ( array( 'post', 'page' ) as $type ) {
			$c       = wp_count_posts( $type );
			$counts += isset( $c->publish ) ? (int) $c->publish : 0;
		}
		return $counts;
	}

	/**
	 * How many posts already carry a fresh cached score.
	 *
	 * @return int
	 */
	public static function cached_posts() {
		$query = new WP_Query(
			array(
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_V,
						'value' => self::checks_version(),
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Analyze a batch of not-yet-cached posts.
	 *
	 * @param int $batch Max posts to analyze in this call.
	 * @return array{analyzed:int,cached:int,total:int}
	 */
	public static function run_batch( $batch = 25 ) {
		$batch = min( 50, max( 1, (int) $batch ) );

		// Two separate queries instead of one OR meta_query: WordPress gives
		// the "!=" clause its own INNER JOIN under OR, which silently drops
		// posts with zero postmeta rows – exactly the never-scanned ones.
		$base_args = array(
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => $batch,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		);

		$missing = new WP_Query(
			array_merge(
				$base_args,
				array(
					'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'     => self::META_V,
							'compare' => 'NOT EXISTS',
						),
					),
				)
			)
		);
		$posts   = $missing->posts;

		if ( count( $posts ) < $batch ) {
			$stale = new WP_Query(
				array_merge(
					$base_args,
					array(
						'posts_per_page' => $batch - count( $posts ),
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							array(
								'key'     => self::META_V,
								'value'   => self::checks_version(),
								'compare' => '!=',
							),
						),
					)
				)
			);
			$posts = array_merge( $posts, $stale->posts );
		}

		// Error isolation + time budget: one broken/heavy post must not
		// wedge the scan – it gets a fallback score and is never retried.
		$analyzed = 0;
		$start    = microtime( true );
		foreach ( $posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue; // Queries above never use 'fields' => 'ids'; this only narrows the type.
			}
			try {
				self::score_post( $post );
			} catch ( \Throwable $e ) {
				update_post_meta( $post->ID, self::META_SCORE, 0 );
				update_post_meta( $post->ID, self::META_FAILS, '' );
				update_post_meta( $post->ID, self::META_V, self::checks_version() );
			}
			++$analyzed;
			if ( microtime( true ) - $start > 15 ) {
				break;
			}
		}

		return array(
			'analyzed' => $analyzed,
			'cached'   => self::cached_posts(),
			'total'    => self::total_posts(),
		);
	}

	/**
	 * Compute and cache the score for one post.
	 *
	 * @param WP_Post $post Post.
	 * @return int Score.
	 */
	public static function score_post( $post ) {
		$result = GEOINS_Analysis::analyze( $post );
		$fails  = array();
		foreach ( $result['checks'] as $check ) {
			if ( empty( $check['pass'] ) ) {
				$fails[] = $check['id'];
			}
		}
		update_post_meta( $post->ID, self::META_SCORE, (int) $result['score'] );
		update_post_meta( $post->ID, self::META_FAILS, implode( ',', $fails ) );
		update_post_meta( $post->ID, self::META_V, self::checks_version() );
		return (int) $result['score'];
	}

	/**
	 * Chart data for the audit page: score distribution over all cached
	 * posts (one grouped SQL) and "opportunity" points = posts with AI
	 * accesses in the last 30 days plotted against their score (bounded
	 * to the 150 most-read posts).
	 *
	 * @return array{distribution:array<int,array{score:int,n:int}>,points:array<int,array<string,mixed>>}
	 */
	public static function summary() {
		global $wpdb;

		$version = self::checks_version();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.meta_value AS score, COUNT(*) AS n
				FROM {$wpdb->postmeta} s
				INNER JOIN {$wpdb->postmeta} v ON v.post_id = s.post_id AND v.meta_key = %s AND v.meta_value = %s
				INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id AND p.post_status = 'publish' AND p.post_type IN ('post','page')
				WHERE s.meta_key = %s
				GROUP BY s.meta_value",
				self::META_V,
				$version,
				self::META_SCORE
			),
			ARRAY_A
		);
		// phpcs:enable

		$total        = self::checks_version_total();
		$distribution = array();
		for ( $i = 0; $i <= $total; $i++ ) {
			$distribution[ $i ] = array(
				'score' => $i,
				'n'     => 0,
			);
		}
		foreach ( (array) $rows as $row ) {
			$score = (int) $row['score'];
			if ( isset( $distribution[ $score ] ) ) {
				$distribution[ $score ]['n'] = (int) $row['n'];
			}
		}

		// Opportunity points: pages AI actually reads, with their score.
		$from = time() - ( 30 * DAY_IN_SECONDS );
		$hits = array_values(
			array_filter(
				GEOINS_Stats::merged_group( array( 'post_id' ), $from, time(), 1 ),
				static function ( $row ) {
					return (int) $row['post_id'] > 0; // Hits on non-post URLs carry post_id 0.
				}
			)
		);
		usort(
			$hits,
			static function ( $a, $b ) {
				return (int) $b['n'] - (int) $a['n'];
			}
		);
		$hits = array_slice( $hits, 0, 150 );

		$ids = array_map( 'intval', wp_list_pluck( $hits, 'post_id' ) );
		if ( $ids ) {
			update_meta_cache( 'post', $ids ); // One query primes all score lookups.
		}

		$points = array();
		foreach ( $hits as $row ) {
			$post_id = (int) $row['post_id'];
			if ( (string) get_post_meta( $post_id, self::META_V, true ) !== $version ) {
				continue; // Not scanned yet (or stale) – no score to plot.
			}
			$post = get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			$points[] = array(
				'id'    => $post_id,
				'title' => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'score' => (int) get_post_meta( $post_id, self::META_SCORE, true ),
				'hits'  => (int) $row['n'],
				'edit'  => get_edit_post_link( $post_id, 'raw' ),
			);
		}

		return array(
			'distribution' => array_values( $distribution ),
			'points'       => $points,
			'total'        => $total,
		);
	}

	/**
	 * Number of checks (max score).
	 *
	 * @return int
	 */
	public static function checks_version_total() {
		return count( GEOINS_Analysis::definitions() );
	}

	/**
	 * One page of the audit table, sorted.
	 *
	 * @param array<string,mixed> $args { orderby: score|modified|title, order: asc|desc,
	 *                                    type: ''|post|page, page: int, per_page: int }.
	 * @return array{rows:array<int,array<string,mixed>>,found:int,pages:int}
	 */
	public static function get_rows( $args ) {
		$orderby  = isset( $args['orderby'] ) ? $args['orderby'] : 'score';
		$order    = ( isset( $args['order'] ) && 'desc' === $args['order'] ) ? 'DESC' : 'ASC';
		$type     = ( isset( $args['type'] ) && in_array( $args['type'], array( 'post', 'page' ), true ) ) ? $args['type'] : '';
		$page     = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;
		$per_page = isset( $args['per_page'] ) ? min( 100, max( 5, (int) $args['per_page'] ) ) : 50;

		$query_args = array(
			'post_type'              => $type ? $type : array( 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'update_post_term_cache' => false,
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => self::META_V,
					'value' => self::checks_version(),
				),
			),
		);

		// ID as secondary sort key: a total order keeps pagination stable
		// (scores are 0-13, so ties are the norm, not the exception).
		if ( 'modified' === $orderby ) {
			$query_args['orderby'] = array(
				'modified' => $order,
				'ID'       => 'ASC',
			);
		} elseif ( 'title' === $orderby ) {
			$query_args['orderby'] = array(
				'title' => $order,
				'ID'    => 'ASC',
			);
		} else {
			$query_args['meta_key'] = self::META_SCORE; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query_args['orderby']  = array(
				'meta_value_num' => $order,
				'ID'             => 'ASC',
			);
		}

		$query = new WP_Query( $query_args );

		$ids    = wp_list_pluck( $query->posts, 'ID' );
		$hits   = GEOINS_Stats::counts_for_posts( $ids, 30 );
		$decode = static function ( $s ) {
			return html_entity_decode( (string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		};

		$rows = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue; // No 'fields' => 'ids' above; this only narrows the type.
			}
			$fails  = (string) get_post_meta( $post->ID, self::META_FAILS, true );
			$rows[] = array(
				'id'       => $post->ID,
				'title'    => $decode( get_the_title( $post ) ),
				'type'     => $post->post_type,
				'score'    => (int) get_post_meta( $post->ID, self::META_SCORE, true ),
				'fails'    => '' === $fails ? array() : explode( ',', $fails ),
				'hits'     => isset( $hits[ $post->ID ] ) ? (int) $hits[ $post->ID ] : 0,
				'modified' => get_post_modified_time( 'Y-m-d', true, $post ),
				'edit'     => get_edit_post_link( $post->ID, 'raw' ),
				'url'      => get_permalink( $post ),
			);
		}

		return array(
			'rows'  => $rows,
			'found' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}
}
