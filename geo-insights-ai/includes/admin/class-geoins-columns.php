<?php
/**
 * "AI (30d)" column on the posts and pages list tables.
 *
 * Shows how often AI bots read each item in the last 30 days, so authors
 * see AI interest right where they manage content. One grouped query per
 * screen (all visible IDs at once), no N+1.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * List-table column module.
 */
class GEOINS_Columns {

	/**
	 * Per-request cache: post_id => hit count.
	 *
	 * @var array<int,int>|null
	 */
	protected static $counts = null;

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'post', 'page' ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( __CLASS__, 'add_column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( __CLASS__, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Register the column.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public static function add_column( $columns ) {
		$columns['geoins_ai'] = __( 'AI (30d)', 'geo-insights-ai' );
		return $columns;
	}

	/**
	 * Render one cell.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function render_column( $column, $post_id ) {
		if ( 'geoins_ai' !== $column ) {
			return;
		}

		if ( null === self::$counts ) {
			$ids = array();
			if ( isset( $GLOBALS['wp_query']->posts ) && is_array( $GLOBALS['wp_query']->posts ) ) {
				$ids = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
			}
			self::$counts  = GEOINS_Stats::counts_for_posts( $ids, 30 );
			self::$counts += array_fill_keys( array_map( 'intval', $ids ), 0 );
		}

		// Row rendered outside the main list query (e.g. quick-edit AJAX):
		// fetch just this one ID.
		if ( ! isset( self::$counts[ $post_id ] ) ) {
			$single                   = GEOINS_Stats::counts_for_posts( array( $post_id ), 30 );
			self::$counts[ $post_id ] = isset( $single[ $post_id ] ) ? (int) $single[ $post_id ] : 0;
		}

		$count = (int) self::$counts[ $post_id ];
		if ( $count > 0 ) {
			printf(
				'<a href="%s" title="%s"><strong>%d</strong></a>',
				esc_url( admin_url( 'admin.php?page=geo-insights' ) ),
				esc_attr__( 'AI bot accesses in the last 30 days – open the statistics dashboard', 'geo-insights-ai' ),
				(int) $count
			);
		} else {
			echo '<span aria-hidden="true" style="color:#a7aaad">–</span>';
		}
	}
}
