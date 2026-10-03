<?php
/**
 * Uninstall: remove plugin data only when the user opted in.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove data for the current site (only when the user opted in).
 *
 * @return void
 */
function geoins_uninstall_site() {
	// Orphaned cron events must go regardless of the data opt-in (covers
	// sites that were still active, or subsites a pre-1.3.0 network
	// deactivation missed).
	wp_clear_scheduled_hook( 'geoins_daily_cleanup' );
	wp_clear_scheduled_hook( 'geoins_refresh_ip_ranges' );
	wp_clear_scheduled_hook( 'geoins_weekly_report' );

	$settings = get_option( 'geoins_settings', array() );
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall (opted in by the user) drops the plugin's own tables; there is nothing to cache.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'geoins_hits' ) );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'geoins_daily' ) );
	// phpcs:enable

	delete_option( 'geoins_settings' );
	delete_option( 'geoins_db_version' );
	delete_option( 'geoins_activated_at' );
	delete_option( 'geoins_ip_ranges' );
	delete_option( 'geoins_indexnow_key' );
	delete_option( 'geoins_indexnow_last' );
	delete_option( 'geoins_indexnow_unreachable' );
	delete_option( 'geoins_report_last' );
	delete_option( 'geoins_seen_sources' );
	delete_option( 'geoins_alerts' );
	delete_option( 'geoins_unknown_bots' );
	delete_option( 'geoins_unknown_dismissed' );
	delete_option( 'geoins_pending_ref' );
	delete_option( 'geoins_audit_gen' );
	delete_option( 'geoins_review' );

	// Per-source first-contact claim rows (atomic add_option markers).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall: bulk-delete the plugin's own option rows; nothing to cache.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'geoins_seen_src_' ) . '%' ) );
	delete_transient( 'geoins_llms_txt_cache' );
	delete_transient( 'geoins_llms_full_cache' );
	delete_transient( 'geoins_welcome_notice' );

	// Remove per-post focus terms, llms.txt pins and cached audit scores.
	delete_post_meta_by_key( '_geoins_keyword' );
	delete_post_meta_by_key( '_geoins_llms_pin' );
	delete_post_meta_by_key( '_geoins_score' );
	delete_post_meta_by_key( '_geoins_score_fails' );
	delete_post_meta_by_key( '_geoins_score_v' );
}

if ( is_multisite() ) {
	$geoins_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $geoins_site_ids as $geoins_site_id ) {
		switch_to_blog( $geoins_site_id );
		geoins_uninstall_site();
		restore_current_blog();
	}
} else {
	geoins_uninstall_site();
}
