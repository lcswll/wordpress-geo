<?php
/**
 * CSV export for the dashboard.
 *
 * All queries live in GEOINS_Stats (shared with the weekly report and
 * WP-CLI); the dashboard data itself is served by GEOINS_Rest to the
 * React app.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard export provider.
 */
class GEOINS_Dashboard {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_post_geoins_export', array( __CLASS__, 'export_csv' ) );
	}

	/**
	 * Stream the current range as CSV (admin-post.php?action=geoins_export).
	 */
	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'geo-insights-ai' ) );
		}
		check_admin_referer( 'geoins_export' );

		$days = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}
		$from = time() - ( $days * DAY_IN_SECONDS );

		// Optional per-AI filter (matches the dashboard's current filter).
		$sources = null;
		if ( isset( $_GET['sources'] ) ) {
			$sources = GEOINS_Bots::valid_sources( explode( ',', sanitize_text_field( wp_unslash( $_GET['sources'] ) ) ) );
			if ( empty( $sources ) ) {
				$sources = null;
			}
		}

		// A filtered export must be distinguishable from a full one.
		$suffix = '';
		if ( $sources ) {
			$joined = implode( '-', $sources );
			$suffix = '-' . ( strlen( $joined ) <= 40 ? $joined : 'filtered' );
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=geo-insights-' . gmdate( 'Y-m-d' ) . '-' . $days . 'd' . $suffix . '.csv' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'day', 'type', 'source', 'label', 'category', 'post_id', 'path', 'verified', 'count' ) );

		$cat_keys = GEOINS_Stats::category_keys();
		$rows     = GEOINS_Stats::merged_group( array( 'day', 'hit_type', 'source', 'category', 'post_id', 'path', 'verified' ), $from, time(), '', $sources );
		usort( $rows, static function ( $a, $b ) { return strcmp( $a['day'], $b['day'] ); } );

		$verified_labels = array( 0 => 'failed', 1 => 'verified', 2 => 'unchecked' );
		foreach ( $rows as $row ) {
			$cat = isset( $cat_keys[ (int) $row['category'] ] ) ? $cat_keys[ (int) $row['category'] ] : '';
			fputcsv(
				$out,
				array(
					$row['day'],
					2 === (int) $row['hit_type'] ? 'referral' : 'bot',
					$row['source'],
					GEOINS_Bots::label( $row['source'] ),
					$cat,
					$row['post_id'],
					$row['path'],
					isset( $verified_labels[ (int) $row['verified'] ] ) ? $verified_labels[ (int) $row['verified'] ] : '',
					$row['n'],
				)
			);
		}
		exit;
	}
}
