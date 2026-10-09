<?php
/**
 * Integration self-test inside a real WordPress (Playground): activation, the built-in self-test, tracking of bot
 * and referral hits, rollup, robots.txt, llms.txt, Markdown, structured data, GEO checks, REST API incl.
 * permissions, beacon, deactivation and uninstall.
 *
 * Writes /e2e-out/selftest.json (scripts/e2e.mjs reads it and fails on any failed assertion).
 *
 * @package Wille_GEO
 */

require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$results = array();

/**
 * Records one assertion.
 *
 * @param bool   $ok
 * @param string $name
 * @param mixed  $detail
 */
function check( $ok, $name, $detail = null ) {
	global $results;
	$results[] = array(
		'ok'     => (bool) $ok,
		'name'   => $name,
		'detail' => $ok ? null : $detail,
	);
}

/** @return WP_REST_Response */
function rest( $method, $route, $params = array(), $headers = array() ) {
	$request = new WP_REST_Request( $method, '/geoins/v1' . $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	foreach ( $headers as $key => $value ) {
		$request->set_header( $key, $value );
	}
	return rest_ensure_response( rest_do_request( $request ) );
}

/** Simulates a front-end page view of $post_id with the given request headers and runs the tracker. */
function visit( $post_id, $user_agent, $referrer = '', $query = array() ) {
	$_SERVER['HTTP_USER_AGENT'] = $user_agent;
	$_SERVER['REQUEST_URI']     = wp_parse_url( get_permalink( $post_id ), PHP_URL_PATH ) . ( $query ? '?' . http_build_query( $query ) : '' );
	$_SERVER['REMOTE_ADDR']     = '203.0.113.' . wp_rand( 1, 250 );
	if ( '' !== $referrer ) {
		$_SERVER['HTTP_REFERER'] = $referrer;
	} else {
		unset( $_SERVER['HTTP_REFERER'] );
	}
	$_GET = $query;
	$GLOBALS['wp_query']->query( array( 'p' => $post_id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	GEOINS_Tracker::maybe_track();
}

function hits( $hit_type = null ) {
	global $wpdb;
	if ( null === $hit_type ) {
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id', $wpdb->prefix . 'geoins_hits' ), ARRAY_A );
	}
	return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE hit_type = %d ORDER BY id', $wpdb->prefix . 'geoins_hits', $hit_type ), ARRAY_A );
}

try {
	global $wpdb;
	$hits_table  = $wpdb->prefix . 'geoins_hits';
	$daily_table = $wpdb->prefix . 'geoins_daily';
	$plugin      = 'wille-geo-ai-visibility/wille-geo-ai-visibility.php';

	// ---------------------------------------------------------------- activation.
	check( is_plugin_active( $plugin ), 'plugin is active' );
	check( $hits_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hits_table ) ), 'hits table exists', $wpdb->last_error );
	check( $daily_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $daily_table ) ), 'daily table exists', $wpdb->last_error );
	check( GEOINS_DB_VERSION === get_option( 'geoins_db_version' ), 'schema version stored', get_option( 'geoins_db_version' ) );
	check( is_array( get_option( 'geoins_settings' ) ), 'default settings stored' );
	foreach ( array( 'geoins_daily_cleanup', 'geoins_refresh_ip_ranges', 'geoins_weekly_report' ) as $hook ) {
		check( (bool) wp_next_scheduled( $hook ), "cron {$hook} scheduled" );
	}

	// --------------------------------------------------------- built-in self-test.
	$self = GEOINS_Selftest::run();
	check( array() === $self['failures'], "built-in self-test ({$self['passed']} assertions)", $self['failures'] );

	// ------------------------------------------------------------------- content.
	update_option( 'permalink_structure', '/%postname%/' );
	flush_rewrite_rules( false );
	$admin = get_user_by( 'login', 'admin' );
	update_user_meta( $admin->ID, 'description', 'Writes about WordPress and GEO since 2019.' );
	$post_id = wp_insert_post(
		array(
			'post_title'   => 'WordPress Backup Guide',
			'post_name'    => 'wordpress-backup-guide',
			'post_status'  => 'publish',
			'post_author'  => $admin->ID,
			'post_content' => '<p>A WordPress backup plugin copies your database and files to a safe place so you can restore the site after a crash, a hack or a bad update in 10 minutes.</p>' .
				'<h2>Why do you need a WordPress backup?</h2><p>Because hosting backups are often incomplete. In a 2025 survey, 43% of site owners lost data at least once.</p>' .
				'<h2>Which backup plugin is best?</h2><ul><li>UpdraftPlus – free</li><li>BlogVault – paid</li></ul><p>See <a href="https://wordpress.org/plugins/updraftplus/">the plugin page</a>.</p>',
		)
	);
	update_post_meta( $post_id, '_geoins_keyword', 'WordPress backup' );
	wp_insert_post(
		array(
			'post_title'   => 'Secret draft',
			'post_status'  => 'draft',
			'post_content' => '<p>Not public.</p>',
		)
	);
	check( $post_id > 0, 'test post created' );

	// ------------------------------------------------------------------ tracking.
	$wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', $hits_table ) );
	wp_set_current_user( 0 );

	visit( $post_id, 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)' );
	$rows = hits();
	check( 1 === count( $rows ), 'bot hit logged', $rows );
	check( isset( $rows[0] ) && 'gptbot' === $rows[0]['source'] && (int) $rows[0]['post_id'] === $post_id, 'bot hit: source + post', $rows );
	check( isset( $rows[0] ) && '/wordpress-backup-guide/' === $rows[0]['path'], 'bot hit: path', $rows );
	check( isset( $rows[0] ) && (string) GEOINS_Bots::CAT_TRAINING === $rows[0]['category'], 'bot hit: category', $rows );

	visit( $post_id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Firefox/130.0' );
	check( 1 === count( hits() ), 'ordinary browser is not logged' );

	visit( $post_id, 'Mozilla/5.0 Firefox/130.0', 'https://chatgpt.com/' );
	visit( $post_id, 'Mozilla/5.0 Safari/605.1', '', array( 'utm_source' => 'perplexity' ) );
	visit( $post_id, 'Mozilla/5.0 Safari/605.1', home_url( '/other/' ) );
	$referrals = hits( 2 );
	check( array( 'chatgpt', 'perplexity' ) === array_column( $referrals, 'source' ), 'referrals via Referer and utm_source, internal navigation ignored', $referrals );

	// Referral dedupe: the same visitor reloading within 60 s counts once.
	$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
	$before                 = count( hits() );
	foreach ( array( 1, 2 ) as $ignored ) {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Dedupe';
		$_SERVER['HTTP_REFERER']    = 'https://claude.ai/chat/x';
		$_GET                       = array();
		GEOINS_Tracker::maybe_track();
	}
	check( count( hits() ) === $before + 1, 'referral reload deduplicated' );

	// Logged-in users are never counted as AI visitors.
	wp_set_current_user( $admin->ID );
	$before = count( hits() );
	visit( $post_id, 'Mozilla/5.0 Firefox/130.0', 'https://gemini.google.com/' );
	check( count( hits() ) === $before, 'logged-in visitor not counted' );
	wp_set_current_user( 0 );

	// No PII: the hits table has no column for IPs, user agents or referrers.
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $hits_table ) );
	check( array() === array_intersect( array( 'ip', 'user_agent', 'ua', 'referrer', 'referer' ), $columns ), 'no personal data columns', $columns );

	// Settings off → nothing is logged.
	$settings               = get_option( 'geoins_settings' );
	$settings['track_bots'] = 0;
	update_option( 'geoins_settings', $settings );
	geoins()->flush_settings_cache();
	$before = count( hits() );
	visit( $post_id, 'Mozilla/5.0 (compatible; ClaudeBot/1.0)' );
	check( count( hits() ) === $before, 'bot tracking can be switched off' );
	$settings['track_bots'] = 1;
	update_option( 'geoins_settings', $settings );
	geoins()->flush_settings_cache();

	// ------------------------------------------------------------- statistics.
	$totals = GEOINS_Stats::totals( time() - DAY_IN_SECONDS, time() + 60 );
	check( is_array( $totals ), 'stats totals', $totals );

	// Rollup: raw hits older than 7 days are compressed into the daily table.
	$wpdb->insert(
		$hits_table,
		array(
			'hit_time' => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ),
			'hit_type' => 1,
			'source'   => 'claudebot',
			'category' => GEOINS_Bots::CAT_TRAINING,
			'post_id'  => $post_id,
			'path'     => '/wordpress-backup-guide/',
			'verified' => 2,
		)
	);
	GEOINS_Install::cleanup();
	$old_raw = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE hit_time < %s', $hits_table, gmdate( 'Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS ) ) );
	$rolled  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(n) FROM %i WHERE source = %s', $daily_table, 'claudebot' ) );
	check( 0 === $old_raw && 1 === $rolled, 'old raw hits rolled up into the daily table', compact( 'old_raw', 'rolled' ) );

	// --------------------------------------------------------------- robots.txt.
	$settings['blocked_bots'] = array( 'gptbot', 'ccbot' );
	update_option( 'geoins_settings', $settings );
	geoins()->flush_settings_cache();
	$robots = apply_filters( 'robots_txt', "User-agent: *\nDisallow: /wp-admin/\n", true );
	check( false !== strpos( $robots, "User-agent: GPTBot\nDisallow: /" ) && false !== strpos( $robots, "User-agent: CCBot\nDisallow: /" ), 'robots.txt blocks the selected bots', $robots );
	check( false === strpos( $robots, 'ClaudeBot' ), 'robots.txt leaves other bots alone', $robots );
	check( 'X' === apply_filters( 'robots_txt', 'X', false ), 'robots.txt untouched on private sites' );

	// ------------------------------------------------------------- llms.txt / .md.
	GEOINS_Llms_Txt::flush_cache();
	$llms = GEOINS_Llms_Txt::build();
	check( 0 === strpos( $llms, '# ' ), 'llms.txt starts with an H1', substr( $llms, 0, 80 ) );
	check( false !== strpos( $llms, 'WordPress Backup Guide' ), 'llms.txt lists published posts', $llms );
	check( false === strpos( $llms, 'Secret draft' ), 'llms.txt hides drafts', $llms );

	$md = GEOINS_Markdown::post_markdown( get_post( $post_id ) );
	check( false !== strpos( $md, '## Why do you need a WordPress backup?' ) && false !== strpos( $md, '- UpdraftPlus' ), 'post as Markdown', $md );
	check( false !== strpos( (string) GEOINS_Markdown::md_url( $post_id ), 'wordpress-backup-guide' ), '.md URL', GEOINS_Markdown::md_url( $post_id ) );
	$full = GEOINS_Markdown::build_llms_full();
	check( false !== strpos( $full, 'WordPress Backup Guide' ) && false === strpos( $full, 'Not public.' ), 'llms-full.txt has public content only' );

	// Output escaping: entities in post text decode to literal markup during conversion; it must not reach the response.
	$xss_md = GEOINS_Markdown::html_to_markdown( '<p>Text &lt;script&gt;alert(1)&lt;/script&gt; &amp; more</p><blockquote><p>Quoted</p></blockquote>' );
	check( false !== strpos( $xss_md, '<script>' ), 'conversion decodes entities (precondition)', $xss_md );
	ob_start();
	GEOINS_Markdown::print_markdown( "# Escaping <b>test</b>\n" . $xss_md . "\n> Tag line" );
	$printed = (string) ob_get_clean();
	check( false === stripos( $printed, '<script' ) && false === stripos( $printed, '<b>' ), 'Markdown output escapes markup', $printed );
	check( false !== strpos( $printed, "\n> Quoted" ) && false !== strpos( $printed, "\n> Tag line" ), 'Markdown output keeps blockquotes', $printed );

	// ------------------------------------------------------------- structured data.
	$GLOBALS['wp_query']->query( array( 'p' => $post_id ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$GLOBALS['post']         = get_post( $post_id );
	ob_start();
	GEOINS_Schema::output();
	$schema_html = (string) ob_get_clean();
	check( false !== strpos( $schema_html, 'application/ld+json' ), 'JSON-LD printed on a post', $schema_html );
	preg_match( '#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $schema_html, $json_m );
	$schema = isset( $json_m[1] ) ? json_decode( $json_m[1], true ) : null;
	check( is_array( $schema ), 'JSON-LD is valid JSON', $schema_html );
	$types = array();
	array_walk_recursive(
		$schema,
		static function ( $value, $key ) use ( &$types ) {
			if ( '@type' === $key ) {
				$types[] = $value;
			}
		}
	);
	foreach ( array( 'WebSite', 'Article', 'FAQPage' ) as $type ) {
		check( in_array( $type, $types, true ), "JSON-LD contains {$type}", $types );
	}

	// ----------------------------------------------------------------- GEO checks.
	$analysis = GEOINS_Analysis::analyze( get_post( $post_id ) );
	$passed   = array_column( $analysis['checks'], 'pass', 'id' );
	check( 13 === $analysis['total'], '13 GEO checks', $analysis['total'] );
	check( $passed['keyword_set'] && $passed['keyword_in_title'] && $passed['answer_first'] && $passed['question_headings'] && $passed['lists_tables'] && $passed['external_links'] && $passed['author_bio'], 'GEO checks recognise a well-structured post', $passed );
	check( ! $passed['word_count'], 'GEO checks flag thin content', $passed );

	// ---------------------------------------------------------------- REST API.
	wp_set_current_user( 0 );
	check( 401 === rest( 'GET', '/dashboard' )->get_status(), 'dashboard: anonymous → 401', rest( 'GET', '/dashboard' )->get_status() );
	check( 401 === rest( 'GET', '/selftest' )->get_status(), 'selftest: anonymous → 401' );
	check( 401 === rest( 'GET', '/audit' )->get_status(), 'audit: anonymous → 401' );

	$subscriber = wp_insert_user(
		array(
			'user_login' => 'e2e-subscriber',
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		)
	);
	wp_set_current_user( $subscriber );
	check( 403 === rest( 'GET', '/dashboard' )->get_status(), 'dashboard: subscriber → 403' );
	check( 403 === rest( 'POST', '/audit/run' )->get_status(), 'audit run: subscriber → 403' );

	wp_set_current_user( $admin->ID );
	$dash = rest( 'GET', '/dashboard', array( 'days' => 30 ) );
	check( 200 === $dash->get_status(), 'dashboard: admin → 200', $dash->get_data() );
	$data = (array) $dash->get_data();
	check( isset( $data['totals'] ), 'dashboard has totals', array_keys( $data ) );
	$filtered = rest(
		'GET',
		'/dashboard',
		array(
			'days'    => 30,
			'sources' => 'gptbot,not-a-source',
		)
	);
	check( 200 === $filtered->get_status(), 'dashboard: company filter accepted', $filtered->get_data() );
	$self_rest = rest( 'GET', '/selftest' )->get_data();
	check( isset( $self_rest['failures'] ) && array() === $self_rest['failures'], 'selftest via REST', $self_rest );
	$audit_run = rest( 'POST', '/audit/run', array( 'batch' => 10 ) );
	check( 200 === $audit_run->get_status(), 'audit batch runs', $audit_run->get_data() );
	$audit = rest( 'GET', '/audit' );
	check( 200 === $audit->get_status() && ! empty( $audit->get_data()['rows'] ), 'audit lists scored posts', $audit->get_data() );

	// ------------------------------------------------------------------- beacon.
	wp_set_current_user( 0 );
	$beacon = rest(
		'POST',
		'/beacon',
		array(
			'source' => 'chatgpt',
			'path'   => '/x/',
			'token'  => GEOINS_Beacon::token( 0 ),
		),
		array( 'origin' => home_url() )
	);
	check( 404 === $beacon->get_status(), 'beacon off by default (404)', $beacon->get_status() );
	$settings['referral_beacon'] = 1;
	update_option( 'geoins_settings', $settings );
	geoins()->flush_settings_cache();
	$same_origin                = array( 'origin' => home_url() );
	$before                     = count( hits() );
	$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Beacon visitor';
	$beacon                     = rest(
		'POST',
		'/beacon',
		array(
			'source' => 'chatgpt',
			'path'   => '/wordpress-backup-guide/',
			'token'  => GEOINS_Beacon::token( $post_id ),
		),
		$same_origin
	);
	$new                        = array_slice( hits(), $before );
	check( 204 === $beacon->get_status() && 1 === count( $new ) && 'chatgpt' === $new[0]['source'] && (int) $new[0]['post_id'] === $post_id, 'beacon records an AI visit', compact( 'new' ) );
	$before = count( hits() );
	rest(
		'POST',
		'/beacon',
		array(
			'source' => 'chatgpt',
			'path'   => '/made-up-path/',
			'token'  => GEOINS_Beacon::token( 0 ),
		),
		$same_origin
	);
	check( count( hits() ) === $before, 'beacon ignores paths that are no page of the site' );
	$bad = rest(
		'POST',
		'/beacon',
		array(
			'source' => 'evil',
			'path'   => '/x/',
			'token'  => GEOINS_Beacon::token( 0 ),
		),
		$same_origin
	);
	check( 400 === $bad->get_status(), 'beacon rejects unknown sources', $bad->get_status() );
	$cross = rest(
		'POST',
		'/beacon',
		array(
			'source' => 'chatgpt',
			'path'   => '/wordpress-backup-guide/',
			'token'  => GEOINS_Beacon::token( $post_id ),
		),
		array( 'origin' => 'https://evil.example' )
	);
	check( 403 === $cross->get_status(), 'beacon rejects cross-site posts', $cross->get_status() );
	$no_origin = rest(
		'POST',
		'/beacon',
		array(
			'source' => 'chatgpt',
			'path'   => '/wordpress-backup-guide/',
			'token'  => GEOINS_Beacon::token( $post_id ),
		)
	);
	check( 403 === $no_origin->get_status(), 'beacon rejects posts without Origin/Referer', $no_origin->get_status() );
	$before = count( hits() );
	$forged = rest(
		'POST',
		'/beacon',
		array(
			'source' => 'chatgpt',
			'path'   => '/wordpress-backup-guide/',
			'token'  => GEOINS_Beacon::token( 0 ),
		),
		$same_origin
	);
	check( 403 === $forged->get_status() && count( hits() ) === $before, 'beacon rejects a token of another page', $forged->get_status() );

	// ---------------------------------------------- first-run panel: first-visit notice, one-click alerts.
	require_once GEOINS_DIR . 'includes/admin/class-geoins-admin.php'; // The plugin loads it in wp-admin only.
	delete_option( 'geoins_visit_notice_seen' );
	GEOINS_Alerts::mark_all_read();
	check( null === GEOINS_Admin::unseen_first_visit(), 'no first-visit notice without a new alert' );
	GEOINS_Alerts::add_alert(
		'new_bot',
		array(
			'label'    => 'GPTBot',
			'slug'     => 'gptbot',
			'category' => GEOINS_Bots::CAT_TRAINING,
			'relevant' => 0,
		)
	);
	$visit_alert = GEOINS_Admin::unseen_first_visit();
	check( is_array( $visit_alert ) && 'new_bot' === $visit_alert['type'], 'a new AI bot raises the first-visit notice', $visit_alert );
	GEOINS_Admin::mark_visit_notice_seen();
	check( null === GEOINS_Admin::unseen_first_visit(), 'opening the dashboard (or Dismiss) hides the notice' );
	GEOINS_Alerts::add_alert(
		'spike',
		array(
			'count' => 9,
			'avg'   => '1',
		)
	);
	check( null === GEOINS_Admin::unseen_first_visit(), 'a spike alert does not raise the first-visit notice' );
	GEOINS_Alerts::add_alert(
		'new_referral',
		array(
			'label' => 'ChatGPT',
			'slug'  => 'chatgpt',
		)
	);
	$visit_alert = GEOINS_Admin::unseen_first_visit();
	check( is_array( $visit_alert ) && 'new_referral' === $visit_alert['type'], 'a later first contact raises the notice again', $visit_alert );

	$before_alerts = geoins()->settings();
	update_option( 'geoins_settings', array_merge( $before_alerts, array( 'alerts_email' => 0 ) ) );
	geoins()->flush_settings_cache();
	GEOINS_Admin::turn_on_alert_emails();
	$after_alerts = geoins()->settings();
	check( 1 === (int) $after_alerts['alerts_email'], 'one click switches on the email alerts' );
	$changed = array();
	foreach ( $before_alerts as $key => $value ) {
		// The sanitizer stores flags as int: scalars are compared as strings, so 1 and "1" count as equal.
		$was = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
		$now = is_scalar( $after_alerts[ $key ] ) ? (string) $after_alerts[ $key ] : wp_json_encode( $after_alerts[ $key ] );
		if ( 'alerts_email' !== $key && $was !== $now ) {
			$changed[ $key ] = array( $value, $after_alerts[ $key ] );
		}
	}
	check( array() === $changed, 'switching on the alerts keeps every other setting', $changed );
	update_option( 'geoins_settings', $before_alerts );
	geoins()->flush_settings_cache();

	// ---------------------------------------------- deactivation, uninstall.
	deactivate_plugins( $plugin );
	check( ! wp_next_scheduled( 'geoins_daily_cleanup' ), 'deactivation clears cron' );
	check( $hits_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hits_table ) ), 'deactivation keeps the data' );

	$settings['delete_on_uninstall'] = 1;
	update_option( 'geoins_settings', $settings );
	uninstall_plugin( $plugin );
	check( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hits_table ) ), 'uninstall drops the tables (opt-in)' );
	check( false === get_option( 'geoins_settings' ), 'uninstall deletes the settings' );
	check( '' === get_post_meta( $post_id, '_geoins_keyword', true ), 'uninstall deletes the post meta' );
	$leftover = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'geoins' ) . '%' ) );
	check( array() === $leftover, 'no options left behind', $leftover );
} catch ( Throwable $e ) {
	check( false, 'uncaught ' . get_class( $e ), $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
}

$failed = count( array_filter( $results, static fn( $r ) => ! $r['ok'] ) );
file_put_contents(
	'/e2e-out/selftest.json',
	wp_json_encode(
		array(
			'php'     => PHP_VERSION,
			'wp'      => get_bloginfo( 'version' ),
			'passed'  => count( $results ) - $failed,
			'failed'  => $failed,
			'results' => $results,
		),
		JSON_PRETTY_PRINT
	)
);
