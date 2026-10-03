<?php
/**
 * Seeds WordPress for the browser tests and the directory screenshots: three posts/pages with focus terms,
 * an author bio, the opt-in features switched on and 90 days of plausible AI traffic (raw hits for the last
 * 7 days, daily aggregates before that – the same split the plugin's own rollup produces).
 *
 * Writes /e2e-out/seeded last (tests/e2e/wait-for-wordpress.js waits for it).
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery
 *
 * @package Wille_GEO
 */

require '/wordpress/wp-load.php';

global $wpdb;

$ids   = array();
$ids[] = wp_insert_post(
	array(
		'post_title'   => 'WordPress Backup Guide',
		'post_name'    => 'wordpress-backup-guide',
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_author'  => 1,
		'post_content' => '<p>A WordPress backup plugin copies your database and files to a safe place so you can restore the site after a crash, a hack or a bad update. The best plugins run automatically, store copies off-site and let you restore with one click. This guide compares the 5 most popular options and shows exactly how to set them up in 10 minutes.</p>' .
			'<h2>Why do you need a WordPress backup?</h2><p>Because hosting backups are often incomplete. In a 2025 survey, 43% of site owners lost data at least once. A daily backup costs less than 2 minutes of setup.</p>' .
			'<h2>Which backup plugin is best?</h2><ul><li>UpdraftPlus – free, 3 million installs</li><li>BlogVault – incremental, paid</li><li>Duplicator – migration focus</li></ul>' .
			'<table><tr><th>Plugin</th><th>Price</th></tr><tr><td>UpdraftPlus</td><td>0 €</td></tr><tr><td>BlogVault</td><td>89 €</td></tr></table>' .
			'<p>See the <a href="https://wordpress.org/plugins/updraftplus/">official plugin page</a> and our <a href="/best-seo-plugins-2026/">SEO plugin comparison</a>.</p>' .
			'<h3>How often should you back up?</h3><p>Daily for shops, weekly for blogs. Keep at least 30 versions.</p><img src="/backup.png" alt="Backup settings screen" />',
	)
);
update_post_meta( $ids[0], '_geoins_keyword', 'WordPress backup plugin' );
update_post_meta( $ids[0], '_geoins_llms_pin', 1 );
$ids[] = wp_insert_post(
	array(
		'post_title'   => 'Best SEO Plugins 2026',
		'post_name'    => 'best-seo-plugins-2026',
		'post_status'  => 'publish',
		'post_type'    => 'post',
		'post_author'  => 1,
		'post_content' => '<p>Short intro.</p><p>' . str_repeat( 'Lorem ipsum dolor sit amet consectetur. ', 40 ) . '</p><img src="/x.png" />',
	)
);
$ids[] = wp_insert_post(
	array(
		'post_title'   => 'About us',
		'post_name'    => 'about-us',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_author'  => 1,
		'post_content' => '<p>We are a small team of 4 people writing about WordPress since 2019. We publish 12 guides a year.</p><h2>What do we do?</h2><p>Guides.</p><h2>Who writes here?</h2><p>Experts.</p><h2>How to reach us?</h2><p>Mail.</p>',
	)
);
update_option( 'geoins_e2e_ids', $ids );
// In use for a month: the review request (GEOINS_Review) is due once the traffic below is in.
update_option( 'geoins_activated_at', time() - 30 * DAY_IN_SECONDS );

$settings                    = get_option( 'geoins_settings', array() );
$settings['toc']             = 1;
$settings['show_modified']   = 1;
$settings['referral_beacon'] = 1;
$settings['indexnow']        = 0;
update_option( 'geoins_settings', $settings );
update_user_meta( 1, 'description', 'Lucas writes about WordPress and GEO since 2019.' );
update_user_meta( 1, 'geoins_job_title', 'WordPress Developer' );
update_user_meta( 1, 'geoins_sameas', "https://github.com/example\nhttps://www.linkedin.com/in/example" );
flush_rewrite_rules( false );

// ------------------------------------------------------------- 90 days of AI traffic (deterministic).
mt_srand( 42 );
$bots  = array(
	'gptbot'        => array( GEOINS_Bots::CAT_TRAINING, 9 ),
	'claudebot'     => array( GEOINS_Bots::CAT_TRAINING, 6 ),
	'ccbot'         => array( GEOINS_Bots::CAT_TRAINING, 3 ),
	'oai-searchbot' => array( GEOINS_Bots::CAT_RETRIEVAL, 5 ),
	'perplexitybot' => array( GEOINS_Bots::CAT_RETRIEVAL, 4 ),
	'chatgpt-user'  => array( GEOINS_Bots::CAT_AGENT, 4 ),
	'bingbot'       => array( GEOINS_Bots::CAT_SEARCH, 3 ),
);
$refs  = array(
	'chatgpt'    => 3,
	'perplexity' => 2,
	'gemini'     => 1,
);
$pages = array(
	array( $ids[0], '/wordpress-backup-guide/' ),
	array( $ids[1], '/best-seo-plugins-2026/' ),
	array( $ids[2], '/about-us/' ),
	array( 0, '/' ),
);

$hits_table  = $wpdb->prefix . 'geoins_hits';
$daily_table = $wpdb->prefix . 'geoins_daily';
$today       = (int) strtotime( gmdate( 'Y-m-d' ) ); // Midnight UTC.
for ( $day = 89; $day >= 0; $day-- ) {
	$growth = 0.4 + ( 90 - $day ) / 75; // AI traffic grows over the quarter.
	$start  = $today - $day * DAY_IN_SECONDS;
	$span   = 0 === $day ? max( 1, time() - $today ) : DAY_IN_SECONDS; // Today: only the hours so far.
	$rows   = array();
	foreach ( $bots as $source => $bot ) {
		$rows[] = array( 1, $source, $bot[0], (int) round( $bot[1] * $growth * mt_rand( 60, 140 ) / 100 * $span / DAY_IN_SECONDS ) );
	}
	foreach ( $refs as $source => $base ) {
		$rows[] = array( 2, $source, 0, (int) round( $base * $growth * mt_rand( 0, 160 ) / 100 * $span / DAY_IN_SECONDS ) );
	}
	foreach ( $rows as $row ) {
		list( $type, $source, $category, $count ) = $row;
		$per_page                                 = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$page = mt_rand( 0, 9 ) < 6 ? 0 : mt_rand( 1, 3 );
			if ( $day < 7 ) {
				$wpdb->insert(
					$hits_table,
					array(
						'hit_time' => gmdate( 'Y-m-d H:i:s', $start + mt_rand( 0, $span - 1 ) ),
						'hit_type' => $type,
						'source'   => $source,
						'category' => $category,
						'post_id'  => $pages[ $page ][0],
						'path'     => $pages[ $page ][1],
						'verified' => 2,
					)
				);
			} else {
				$per_page[ $page ] = ( $per_page[ $page ] ?? 0 ) + 1;
			}
		}
		// Older days: one aggregate row per page, as the rollup writes them.
		foreach ( $per_page as $page => $n ) {
			$wpdb->insert(
				$daily_table,
				array(
					'day'      => gmdate( 'Y-m-d', $start ),
					'hit_type' => $type,
					'source'   => $source,
					'category' => $category,
					'post_id'  => $pages[ $page ][0],
					'path'     => $pages[ $page ][1],
					'verified' => 2,
					'n'        => $n,
				)
			);
		}
	}
}
// The seeded sources are "known", so the alert strip shows no first-contact flood.
delete_option( 'geoins_seen_sources' );
GEOINS_Alerts::maybe_seed();
// …except one: a first visit by Claude-User an hour ago, for the "What happened" strip.
$wpdb->insert(
	$hits_table,
	array(
		'hit_time' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
		'hit_type' => 1,
		'source'   => 'claude-user',
		'category' => GEOINS_Bots::CAT_AGENT,
		'post_id'  => $ids[0],
		'path'     => '/wordpress-backup-guide/',
		'verified' => 2,
	)
);
GEOINS_Alerts::on_hit( 1, 'claude-user' );

file_put_contents( '/e2e-out/seeded', gmdate( 'c' ) );
