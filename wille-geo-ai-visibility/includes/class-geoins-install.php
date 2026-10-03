<?php
/**
 * Activation / deactivation: database tables, defaults, cron, multisite.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install routines.
 */
class GEOINS_Install {

	/**
	 * Days after which raw hits are compressed into the daily aggregate table.
	 */
	const ROLLUP_AFTER_DAYS = 7;

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'track_bots'          => 1,
			'track_referrals'     => 1,
			'verify_bots'         => 0,
			'proxy_header'        => '',
			'referral_beacon'     => 0,
			'weekly_report'       => 0,
			'alerts_email'        => 0,
			'report_recipient'    => '',
			'indexnow'            => 0,
			'retention_days'      => 180,
			'llms_txt'            => 1,
			'llms_full'           => 1,
			'llms_intro'          => '',
			'llms_include_pages'  => 1,
			'llms_include_posts'  => 1,
			'llms_max_items'      => 50,
			'md_endpoints'        => 1,
			'sitemap_lastmod'     => 1,
			'show_modified'       => 0,
			'toc'                 => 0,
			'schema'              => 1,
			'faq_schema'          => 1,
			'meta_tags'           => 1,
			'robots_control'      => 1,
			'blocked_bots'        => array(),
			'schema_entity'       => 'organization',
			'schema_name'         => '',
			'schema_sameas'       => '',
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * Plugin activation.
	 *
	 * @param bool $network_wide Whether the plugin is activated network-wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				self::activate_single();
				restore_current_blog();
			}
		} else {
			self::activate_single();
		}
	}

	/**
	 * Activation for one site.
	 *
	 * @return void
	 */
	protected static function activate_single() {
		self::create_tables();
		GEOINS_Alerts::maybe_seed();

		if ( false === get_option( 'geoins_settings', false ) ) {
			add_option( 'geoins_settings', self::defaults() );
		}
		update_option( 'geoins_db_version', GEOINS_DB_VERSION );
		add_option( 'geoins_activated_at', time() );
		set_transient( 'geoins_welcome_notice', 1, WEEK_IN_SECONDS );

		self::schedule_events();
	}

	/**
	 * Ensure cron events exist (per site).
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'geoins_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'geoins_daily_cleanup' );
		}
		if ( ! wp_next_scheduled( 'geoins_refresh_ip_ranges' ) ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'daily', 'geoins_refresh_ip_ranges' );
		}
		if ( ! wp_next_scheduled( 'geoins_weekly_report' ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', 'geoins_weekly_report' );
		}
	}

	/**
	 * New subsite created while the plugin is network-active.
	 *
	 * @param WP_Site $new_site New site object.
	 * @return void
	 */
	public static function initialize_site( $new_site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( GEOINS_FILE ) ) ) {
			return;
		}
		switch_to_blog( (int) $new_site->blog_id );
		self::activate_single();
		restore_current_blog();
	}

	/**
	 * Plugin deactivation. Cron events live per site, so a network-wide
	 * deactivation must clear them on every site (mirrors activate()).
	 *
	 * @param bool $network_wide Whether the plugin is deactivated network-wide.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );
				self::deactivate_single();
				restore_current_blog();
			}
		} else {
			self::deactivate_single();
		}
	}

	/**
	 * Deactivation for one site.
	 *
	 * @return void
	 */
	protected static function deactivate_single() {
		wp_clear_scheduled_hook( 'geoins_daily_cleanup' );
		wp_clear_scheduled_hook( 'geoins_refresh_ip_ranges' );
		wp_clear_scheduled_hook( 'geoins_weekly_report' );
		delete_transient( 'geoins_llms_txt_cache' );
		delete_transient( 'geoins_llms_full_cache' );
	}

	/**
	 * Create/upgrade the tables (raw hits + daily aggregate).
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$hits            = $wpdb->prefix . 'geoins_hits';
		$daily           = $wpdb->prefix . 'geoins_daily';

		$sql = "CREATE TABLE {$hits} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			hit_time DATETIME NOT NULL,
			hit_type TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			source VARCHAR(40) NOT NULL DEFAULT '',
			category TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			path VARCHAR(191) NOT NULL DEFAULT '',
			verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 2,
			PRIMARY KEY  (id),
			KEY hit_time (hit_time),
			KEY source (source),
			KEY post_id (post_id)
		) {$charset_collate};
		CREATE TABLE {$daily} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			day DATE NOT NULL,
			hit_type TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			source VARCHAR(40) NOT NULL DEFAULT '',
			category TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			path VARCHAR(191) NOT NULL DEFAULT '',
			verified TINYINT(1) UNSIGNED NOT NULL DEFAULT 2,
			n INT(10) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY day (day),
			KEY source (source),
			KEY post_id (post_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Daily cron: roll raw hits older than ROLLUP_AFTER_DAYS into the
	 * aggregate table, then enforce the retention setting on both tables.
	 *
	 * @return void
	 */
	public static function cleanup() {
		global $wpdb;

		$hits  = $wpdb->prefix . 'geoins_hits';
		$daily = $wpdb->prefix . 'geoins_daily';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom stats tables, cron maintenance.

		// 1) Roll up raw rows older than the rollup window.
		$rollup_cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::ROLLUP_AFTER_DAYS * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (day, hit_type, source, category, post_id, path, verified, n)
				SELECT DATE(hit_time), hit_type, source, category, post_id, path, verified, COUNT(*)
				FROM %i WHERE hit_time < %s
				GROUP BY DATE(hit_time), hit_type, source, category, post_id, path, verified',
				$daily,
				$hits,
				$rollup_cutoff
			)
		);
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE hit_time < %s', $hits, $rollup_cutoff ) );

		// 2) Retention on the aggregate table.
		$settings  = geoins()->settings();
		$retention = absint( $settings['retention_days'] );
		if ( $retention > 0 ) {
			$retention_cutoff = gmdate( 'Y-m-d', time() - ( $retention * DAY_IN_SECONDS ) );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day < %s', $daily, $retention_cutoff ) );
		}

		// phpcs:enable
	}
}

add_action( 'geoins_daily_cleanup', array( 'GEOINS_Install', 'cleanup' ) );
add_action( 'wp_initialize_site', array( 'GEOINS_Install', 'initialize_site' ), 100 );
