<?php
/**
 * WP-CLI commands: wp geoins <command>.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage Wille GEO from the command line.
 */
class GEOINS_CLI {

	/**
	 * Show AI visibility statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Range in days (7, 30 or 90). Default 30.
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp geoins stats
	 *     wp geoins stats --days=7 --format=json
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Named args.
	 * @return void
	 */
	public function stats( $args, $assoc_args ) {
		$days = isset( $assoc_args['days'] ) ? absint( $assoc_args['days'] ) : 30;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			WP_CLI::error( '--days must be 7, 30 or 90.' );
		}
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$data = GEOINS_Stats::collect( $days );

		if ( 'table' === $format ) {
			WP_CLI::log( sprintf( 'Last %d days: %d AI bot accesses, %d human visitors from AI answers.', $days, $data['totals']['bots'], $data['totals']['referrals'] ) );
			WP_CLI::log( sprintf( 'Agent: %d | Retrieval: %d | Search: %d | Training: %d', $data['totals']['agent'], $data['totals']['retrieval'], $data['totals']['search'], $data['totals']['training'] ) );
		}

		$rows = array();
		foreach ( $data['topBots'] as $bot ) {
			$rows[] = array(
				'type'   => 'bot',
				'source' => $bot['label'],
				'class'  => $bot['category'],
				'count'  => $bot['count'],
			);
		}
		foreach ( $data['referrals'] as $ref ) {
			$rows[] = array(
				'type'   => 'referral',
				'source' => $ref['label'],
				'class'  => 'referral',
				'count'  => $ref['count'],
			);
		}

		if ( empty( $rows ) ) {
			WP_CLI::log( 'No AI activity recorded in this range yet.' );
			return;
		}
		WP_CLI\Utils\format_items( $format, $rows, array( 'type', 'source', 'class', 'count' ) );
	}

	/**
	 * Export raw statistics as CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Range in days (7, 30 or 90). Default 30.
	 *
	 * [--file=<file>]
	 * : Write to this file instead of STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp geoins export --days=90 --file=geo-stats.csv
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Named args.
	 * @return void
	 */
	public function export( $args, $assoc_args ) {
		$days = isset( $assoc_args['days'] ) ? absint( $assoc_args['days'] ) : 30;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			WP_CLI::error( '--days must be 7, 30 or 90.' );
		}

		$from = time() - ( $days * DAY_IN_SECONDS );
		$rows = GEOINS_Stats::merged_group( array( 'day', 'hit_type', 'source', 'category', 'post_id', 'path', 'verified' ), $from, time() );
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['day'], $b['day'] );
			}
		);

		$lines   = array();
		$lines[] = 'day,type,source,category,post_id,path,verified,count';
		$cats    = GEOINS_Stats::category_keys();
		foreach ( $rows as $row ) {
			$lines[] = implode(
				',',
				array(
					$row['day'],
					2 === (int) $row['hit_type'] ? 'referral' : 'bot',
					$row['source'],
					isset( $cats[ (int) $row['category'] ] ) ? $cats[ (int) $row['category'] ] : '',
					$row['post_id'],
					'"' . str_replace( '"', '""', $row['path'] ) . '"',
					$row['verified'],
					$row['n'],
				)
			);
		}
		$csv = implode( "\n", $lines ) . "\n";

		if ( ! empty( $assoc_args['file'] ) ) {
			global $wp_filesystem;
			require_once ABSPATH . 'wp-admin/includes/file.php';
			if ( ! WP_Filesystem() || ! $wp_filesystem instanceof WP_Filesystem_Base || ! $wp_filesystem->put_contents( $assoc_args['file'], $csv, FS_CHMOD_FILE ) ) {
				WP_CLI::error( sprintf( 'Could not write %s.', $assoc_args['file'] ) );
			}
			WP_CLI::success( sprintf( '%d rows written to %s.', count( $rows ), $assoc_args['file'] ) );
		} else {
			WP_CLI::log( $csv );
		}
	}

	/**
	 * Run the daily maintenance now (rollup + retention cleanup).
	 *
	 * ## EXAMPLES
	 *
	 *     wp geoins cleanup
	 *
	 * @return void
	 */
	public function cleanup() {
		GEOINS_Install::cleanup();
		WP_CLI::success( 'Rollup and retention cleanup completed.' );
	}

	/**
	 * Refresh the official crawler IP ranges used for bot verification.
	 *
	 * ## EXAMPLES
	 *
	 *     wp geoins refresh-ranges
	 *
	 * @subcommand refresh-ranges
	 * @return void
	 */
	public function refresh_ranges() {
		$settings = geoins()->settings();
		if ( empty( $settings['verify_bots'] ) ) {
			WP_CLI::error( 'Bot verification is disabled – enable it in Wille GEO → Settings first.' );
		}
		GEOINS_Verify::refresh_ranges();
		WP_CLI::success( GEOINS_Verify::ranges_info() );
	}

	/**
	 * Send the weekly email report now.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Send even when there was no AI activity this week.
	 *
	 * ## EXAMPLES
	 *
	 *     wp geoins report --force
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Named args.
	 * @return void
	 */
	public function report( $args, $assoc_args ) {
		$sent = GEOINS_Report::send( ! empty( $assoc_args['force'] ) );
		if ( $sent ) {
			WP_CLI::success( 'Report sent.' );
		} else {
			WP_CLI::warning( 'No report sent (no AI activity this week, or the recipient address is invalid). Use --force to send anyway.' );
		}
	}

	/**
	 * Run the built-in self-test (pure-function smoke tests).
	 *
	 * ## EXAMPLES
	 *
	 *     wp geoins selftest
	 *
	 * @return void
	 */
	public function selftest() {
		$result = GEOINS_Selftest::run();
		if ( $result['failures'] ) {
			foreach ( $result['failures'] as $failure ) {
				WP_CLI::warning( $failure );
			}
			WP_CLI::error( sprintf( '%d passed, %d failed.', $result['passed'], count( $result['failures'] ) ) );
		}
		WP_CLI::success( sprintf( 'All %d checks passed.', $result['passed'] ) );
	}
}
