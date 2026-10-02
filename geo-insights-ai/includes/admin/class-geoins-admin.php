<?php
/**
 * Admin: menu, assets, settings registration.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin module.
 */
class GEOINS_Admin {

	/**
	 * Author credit shown in the brand bar and the plugin list.
	 */
	const AUTHOR = 'Lucas Wille';

	/**
	 * Author website.
	 */
	const AUTHOR_URL = 'https://lucaswille.de/';

	/**
	 * Where a review is written on wordpress.org.
	 */
	const REVIEW_URL = 'https://wordpress.org/support/plugin/geo-insights-ai/reviews/#new-post';

	/**
	 * Hook suffixes of our admin pages, as returned by add_menu_page /
	 * add_submenu_page. Captured instead of hardcoded because WordPress
	 * derives submenu hooks from the TRANSLATED menu title – a localized
	 * title would silently break a hardcoded allowlist.
	 *
	 * @var string[]
	 */
	protected static $page_hooks = array();

	/**
	 * Menu slug of the page currently rendering its brand bar.
	 *
	 * @var string
	 */
	protected static $current_page = '';

	/**
	 * Hook up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'welcome_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GEOINS_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_filter( 'admin_footer_text', array( __CLASS__, 'footer_text' ) );
	}

	/**
	 * Whether the current admin screen is one of the plugin's own pages.
	 *
	 * @return bool
	 */
	public static function is_plugin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen instanceof WP_Screen && in_array( $screen->id, self::$page_hooks, true );
	}

	/**
	 * Brand bar with navigation and author credit (top of every plugin page).
	 *
	 * @param string $current Menu slug of the current page.
	 * @return void
	 */
	public static function header( $current ) {
		self::$current_page = $current;
		require GEOINS_DIR . 'includes/admin/views/header.php';
	}

	/**
	 * Menu slug of the page being rendered (for views/header.php).
	 *
	 * @return string
	 */
	public static function current_page() {
		return self::$current_page;
	}

	/**
	 * Extra links in the plugin's row on the plugins screen: explainer + review.
	 *
	 * @param string[] $links Row meta links.
	 * @param string   $file  Plugin basename of the row.
	 * @return string[]
	 */
	public static function row_meta( $links, $file ) {
		if ( plugin_basename( GEOINS_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=geo-insights-learn' ) ) . '">' . esc_html__( 'How it works', 'geo-insights-ai' ) . '</a>';
		$links[] = '<a href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr__( 'Rate GEO Insights on WordPress.org (opens in a new tab)', 'geo-insights-ai' ) . '">' . esc_html__( 'Rate ★★★★★', 'geo-insights-ai' ) . '</a>';
		return $links;
	}

	/**
	 * Footer line on the plugin's own pages only: author credit + a quiet review request.
	 *
	 * @param string $text Default footer text.
	 * @return string
	 */
	public static function footer_text( $text ) {
		if ( ! self::is_plugin_screen() ) {
			return $text;
		}
		return sprintf(
			/* translators: 1: author link, 2: review link with five stars */
			esc_html__( 'GEO Insights is made by %1$s. Does it help you? A %2$s review on WordPress.org helps others find it – thank you!', 'geo-insights-ai' ),
			'<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener">' . esc_html( self::AUTHOR ) . '</a>',
			'<a class="geoins-footer-stars" href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener">★★★★★</a>'
		);
	}

	/**
	 * One-time notice after activation.
	 *
	 * @return void
	 */
	public static function welcome_notice() {
		if ( ! get_transient( 'geoins_welcome_notice' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'toplevel_page_geo-insights' === $screen->id ) {
			delete_transient( 'geoins_welcome_notice' );
			return;
		}
		delete_transient( 'geoins_welcome_notice' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'GEO Insights is active.', 'geo-insights-ai' ); ?></strong>
				<?php esc_html_e( 'AI bot accesses and visitors from AI answers are being recorded from now on – data appears as soon as the first AI reaches your site.', 'geo-insights-ai' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=geo-insights' ) ); ?>"><?php esc_html_e( 'Open the statistics dashboard', 'geo-insights-ai' ); ?></a>
				· <a href="<?php echo esc_url( admin_url( 'admin.php?page=geo-insights-learn' ) ); ?>"><?php esc_html_e( 'New here? How it all works, in plain language.', 'geo-insights-ai' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Admin menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$hooks   = array();
		$hooks[] = add_menu_page(
			__( 'GEO Insights', 'geo-insights-ai' ),
			__( 'GEO Insights', 'geo-insights-ai' ),
			'manage_options',
			'geo-insights',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-visibility',
			58
		);
		$hooks[] = add_submenu_page(
			'geo-insights',
			__( 'AI Statistics', 'geo-insights-ai' ),
			__( 'AI Statistics', 'geo-insights-ai' ),
			'manage_options',
			'geo-insights',
			array( __CLASS__, 'render_dashboard' )
		);
		$hooks[] = add_submenu_page(
			'geo-insights',
			__( 'GEO Audit', 'geo-insights-ai' ),
			__( 'GEO Audit', 'geo-insights-ai' ),
			'edit_others_posts',
			'geo-insights-audit',
			array( __CLASS__, 'render_audit' )
		);
		$hooks[] = add_submenu_page(
			'geo-insights',
			__( 'GEO Settings', 'geo-insights-ai' ),
			__( 'Settings', 'geo-insights-ai' ),
			'manage_options',
			'geo-insights-settings',
			array( __CLASS__, 'render_settings' )
		);
		$hooks[] = add_submenu_page(
			'geo-insights',
			__( 'How it works', 'geo-insights-ai' ),
			__( 'How it works', 'geo-insights-ai' ),
			'edit_posts',
			'geo-insights-learn',
			array( __CLASS__, 'render_learn' )
		);

		self::$page_hooks = array_values( array_filter( $hooks ) );
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=geo-insights' ) ) . '">' . esc_html__( 'Statistics', 'geo-insights-ai' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=geo-insights-settings' ) ) . '">' . esc_html__( 'Settings', 'geo-insights-ai' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Register the single settings option.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'geoins_settings_group',
			'geoins_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize all settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize_settings( $input ) {
		$defaults = GEOINS_Install::defaults();
		$input    = is_array( $input ) ? $input : array();
		$clean    = array();

		foreach ( array( 'track_bots', 'track_referrals', 'verify_bots', 'referral_beacon', 'weekly_report', 'alerts_email', 'indexnow', 'llms_txt', 'llms_full', 'llms_include_pages', 'llms_include_posts', 'md_endpoints', 'sitemap_lastmod', 'show_modified', 'toc', 'schema', 'faq_schema', 'meta_tags', 'robots_control', 'delete_on_uninstall' ) as $flag ) {
			$clean[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		// Verification switched on: fetch the IP ranges right away.
		$previous = geoins()->settings();
		if ( ! empty( $clean['verify_bots'] ) && empty( $previous['verify_bots'] ) ) {
			wp_schedule_single_event( time() + 10, 'geoins_refresh_ip_ranges' );
		}

		// IndexNow on: make sure the key exists, then schedule a reachability
		// check for the key file. The check must run AFTER this save is
		// written (the loopback request reads the stored option to decide
		// whether the key route is active), so it goes through a single cron
		// event instead of running inline here.
		if ( ! empty( $clean['indexnow'] ) ) {
			GEOINS_IndexNow::key();
			if ( empty( $previous['indexnow'] ) || get_option( 'geoins_indexnow_unreachable' ) ) {
				wp_schedule_single_event( time() + 5, 'geoins_indexnow_check' );
			}
		}

		$clean['report_recipient'] = '';
		if ( ! empty( $input['report_recipient'] ) ) {
			$email = sanitize_email( (string) $input['report_recipient'] );
			if ( is_email( $email ) ) {
				$clean['report_recipient'] = $email;
			}
		}

		$retention               = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : $defaults['retention_days'];
		$clean['retention_days'] = in_array( $retention, array( 0, 30, 90, 180, 365 ), true ) ? $retention : $defaults['retention_days'];

		$max                     = isset( $input['llms_max_items'] ) ? absint( $input['llms_max_items'] ) : $defaults['llms_max_items'];
		$clean['llms_max_items'] = min( 500, max( 1, $max ) );
		$clean['llms_intro']     = isset( $input['llms_intro'] ) ? sanitize_textarea_field( $input['llms_intro'] ) : '';
		$clean['schema_entity']  = ( isset( $input['schema_entity'] ) && 'person' === $input['schema_entity'] ) ? 'person' : 'organization';
		$clean['schema_name']    = isset( $input['schema_name'] ) ? sanitize_text_field( $input['schema_name'] ) : '';

		// proxy_header: the select is only rendered while verification is on,
		// so an absent field means "keep the stored value", not "reset" –
		// otherwise any save while verification is off would silently wipe a
		// CDN setup and later flag every genuine bot as an impostor.
		$clean['proxy_header'] = ( isset( $previous['proxy_header'] ) && in_array( $previous['proxy_header'], array( '', 'cf', 'xff' ), true ) ) ? $previous['proxy_header'] : '';
		if ( isset( $input['proxy_header'] ) && in_array( $input['proxy_header'], array( '', 'cf', 'xff' ), true ) ) {
			$clean['proxy_header'] = $input['proxy_header'];
		}

		$clean['schema_sameas'] = '';
		if ( ! empty( $input['schema_sameas'] ) ) {
			$urls  = array();
			$lines = preg_split( '/\r\n|\r|\n/', (string) $input['schema_sameas'] );
			foreach ( is_array( $lines ) ? $lines : array() as $url ) {
				$url = esc_url_raw( trim( $url ) );
				if ( '' !== $url ) {
					$urls[] = $url;
				}
			}
			$clean['schema_sameas'] = implode( "\n", array_slice( $urls, 0, 20 ) );
		}

		$clean['blocked_bots'] = array();
		if ( ! empty( $input['blocked_bots'] ) && is_array( $input['blocked_bots'] ) ) {
			$known = array_keys( GEOINS_Bots::bots() );
			foreach ( $input['blocked_bots'] as $slug ) {
				$slug = sanitize_key( $slug );
				if ( in_array( $slug, $known, true ) ) {
					$clean['blocked_bots'][] = $slug;
				}
			}
		}

		geoins()->flush_settings_cache();
		delete_transient( 'geoins_llms_txt_cache' );
		delete_transient( 'geoins_llms_full_cache' );

		return $clean;
	}

	/**
	 * Enqueue admin assets where needed.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		$is_plugin_page = in_array( $hook, self::$page_hooks, true );
		$is_editor      = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_plugin_page && ! $is_editor ) {
			return;
		}

		wp_enqueue_style( 'geoins-admin', GEOINS_URL . 'assets/css/geoins-admin.css', array(), GEOINS_VERSION );
		wp_enqueue_script( 'geoins-admin', GEOINS_URL . 'assets/js/geoins-admin.js', array(), GEOINS_VERSION, true );

		wp_localize_script(
			'geoins-admin',
			'geoinsData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'passed' => __( 'passed', 'geo-insights-ai' ),
				),
			)
		);

		// React bundle: statistics page + audit page (both roots live in it).
		$is_stats = 'toplevel_page_geo-insights' === $hook || ( ! empty( self::$page_hooks[0] ) && self::$page_hooks[0] === $hook );
		$is_audit = str_ends_with( $hook, '_page_geo-insights-audit' );
		if ( $is_stats || $is_audit ) {
			$directory = self::dashboard_sources();

			// Localized short weekday names, Monday first (heatmap axis).
			$weekdays = array();
			for ( $d = 1; $d <= 7; $d++ ) {
				$weekdays[] = $GLOBALS['wp_locale']->get_weekday_abbrev( $GLOBALS['wp_locale']->get_weekday( $d % 7 ) );
			}

			$check_labels = array();
			foreach ( GEOINS_Analysis::definitions() as $check_id => $check_def ) {
				$check_labels[ $check_id ] = $check_def['label'];
			}
			wp_enqueue_script( 'geoins-dashboard', GEOINS_URL . 'assets/js/geoins-dashboard.js', array(), GEOINS_VERSION, true );
			wp_localize_script(
				'geoins-dashboard',
				'geoinsDash',
				array(
					'restUrl'     => esc_url_raw( rest_url() ),
					'nonce'       => wp_create_nonce( 'wp_rest' ),
					'exportUrl'   => wp_nonce_url( admin_url( 'admin-post.php?action=geoins_export' ), 'geoins_export' ),
					'learnUrl'    => admin_url( 'admin.php?page=geo-insights-learn' ),
					'auditUrl'    => admin_url( 'admin.php?page=geo-insights-audit' ),
					'companies'   => $directory['companies'],
					'sources'     => $directory['sources'],
					'checkLabels' => $check_labels,
					'weekdays'    => $weekdays,
					'catLabels'   => array(
						'training'  => __( 'Training', 'geo-insights-ai' ),
						'retrieval' => __( 'Retrieval', 'geo-insights-ai' ),
						'agent'     => __( 'Agent', 'geo-insights-ai' ),
						'search'    => __( 'Search', 'geo-insights-ai' ),
						'referral'  => __( 'AI visitors', 'geo-insights-ai' ),
					),
					'i18n'        => array(
						'range'             => __( 'Date range', 'geo-insights-ai' ),
						'days'              => __( 'days', 'geo-insights-ai' ),
						'exportCsv'         => __( 'Export CSV', 'geo-insights-ai' ),
						'hits'              => __( 'accesses', 'geo-insights-ai' ),
						'visitors'          => __( 'visitors', 'geo-insights-ai' ),
						'noData'            => __( 'No data yet. AI accesses appear here as soon as a known AI bot or an AI-referred visitor reaches your site.', 'geo-insights-ai' ),
						'loadError'         => __( 'Could not load statistics.', 'geo-insights-ai' ),
						'sessionExpired'    => __( 'Your session check expired – reload the page to continue.', 'geo-insights-ai' ),
						'reload'            => __( 'Reload page', 'geo-insights-ai' ),
						'term'              => __( 'Term', 'geo-insights-ai' ),
						'page'              => __( 'Page', 'geo-insights-ai' ),
						'ai'                => __( 'AI accesses', 'geo-insights-ai' ),
						'from'              => __( 'AI source', 'geo-insights-ai' ),
						'vsPrev'            => __( 'vs. previous period', 'geo-insights-ai' ),
						'newLabel'          => __( 'new', 'geo-insights-ai' ),
						'verified'          => __( 'verified', 'geo-insights-ai' ),
						'spoofed'           => __( 'impostors', 'geo-insights-ai' ),
						'unchecked'         => __( 'unchecked', 'geo-insights-ai' ),
						'verifyOff'         => __( 'Identity verification is off. User agents can be faked – enable verification in the settings to separate real AI bots from impostors.', 'geo-insights-ai' ),
						'timelineTitle'     => __( 'AI accesses per day', 'geo-insights-ai' ),
						'timelineHint'      => __( 'Agent + Retrieval = citation-relevant: an AI read your page to answer a real question. Training only feeds models.', 'geo-insights-ai' ),
						'botsTitle'         => __( 'Which AI bots?', 'geo-insights-ai' ),
						'referralsTitle'    => __( 'Human visitors from AI answers', 'geo-insights-ai' ),
						'referralsHint'     => __( 'Real people who clicked your link inside ChatGPT, Perplexity & co. This is the payoff of GEO.', 'geo-insights-ai' ),
						'matrixTitle'       => __( 'Terms & pages: which AI reads what?', 'geo-insights-ai' ),
						'matrixHint'        => __( 'AI crawlers do not transmit search queries. This matrix maps every access to the focus term of the page (set it in the GEO check box on the edit screen) – the honest, practical equivalent.', 'geo-insights-ai' ),
						'landingsTitle'     => __( 'AI visitors: landing pages', 'geo-insights-ai' ),
						'statusTitle'       => __( 'GEO status of your site', 'geo-insights-ai' ),
						'emptyTitle'        => __( 'Waiting for the first AI visit', 'geo-insights-ai' ),
						'emptyHint'         => __( 'Meanwhile: fix anything that is not green in the status panel below, and set focus terms on your most important pages.', 'geo-insights-ai' ),
						'learnLink'         => __( 'New here? How it all works, in plain language.', 'geo-insights-ai' ),
						'filterLabel'       => __( 'Filter by AI', 'geo-insights-ai' ),
						'filterAll'         => __( 'All AIs', 'geo-insights-ai' ),
						'filterNoData'      => __( 'No data for this AI in the selected range.', 'geo-insights-ai' ),
						'resetFilter'       => __( 'Show all AIs', 'geo-insights-ai' ),
						'alertsTitle'       => __( 'What happened', 'geo-insights-ai' ),
						'markRead'          => __( 'Mark all as read', 'geo-insights-ai' ),
						/* translators: %s: AI assistant name. */
						'alertNewRef'       => __( 'First human visitors from %s! Your content is being cited there.', 'geo-insights-ai' ),
						/* translators: %s: bot name. */
						'alertNewBot'       => __( '%s crawled your site for the first time – a citation-relevant AI is now reading you.', 'geo-insights-ai' ),
						/* translators: %s: bot name. */
						'alertNewTrain'     => __( '%s (training crawler) visited your site for the first time.', 'geo-insights-ai' ),
						/* translators: 1: visitor count, 2: average. */
						'alertSpike'        => __( 'AI visitor spike: %1$s visitors from AI answers yesterday (recent average: %2$s/day).', 'geo-insights-ai' ),
						'unknownTitle'      => __( 'AI radar: unrecognized AI-like crawlers', 'geo-insights-ai' ),
						'unknownHint'       => __( 'These user agents sound like AI systems but are not in the registry yet – so their hits are NOT in the statistics above. Frequent entries are worth adding via the geoins_bots filter (or report them to the plugin).', 'geo-insights-ai' ),
						'unknownUa'         => __( 'User agent', 'geo-insights-ai' ),
						'lastSeen'          => __( 'Last seen', 'geo-insights-ai' ),
						'dismiss'           => __( 'Dismiss', 'geo-insights-ai' ),
						/* translators: 1: pages analyzed so far, 2: total pages. */
						'auditScanning'     => __( 'Analyzing content … %1$s of %2$s done', 'geo-insights-ai' ),
						'auditTitleCol'     => __( 'Title', 'geo-insights-ai' ),
						'auditScoreCol'     => __( 'GEO score', 'geo-insights-ai' ),
						'auditFailsCol'     => __( 'Open improvements', 'geo-insights-ai' ),
						'auditHitsCol'      => __( 'AI accesses (30d)', 'geo-insights-ai' ),
						'auditModCol'       => __( 'Updated', 'geo-insights-ai' ),
						'auditTypePost'     => __( 'Post', 'geo-insights-ai' ),
						'auditTypePage'     => __( 'Page', 'geo-insights-ai' ),
						'auditAllTypes'     => __( 'All types', 'geo-insights-ai' ),
						'auditEmpty'        => __( 'No published content to audit yet.', 'geo-insights-ai' ),
						'auditPerfect'      => __( 'All checks passed', 'geo-insights-ai' ),
						'auditRescan'       => __( 'Re-scan', 'geo-insights-ai' ),
						'auditMore'         => __( 'more', 'geo-insights-ai' ),
						'auditPrev'         => __( 'Previous', 'geo-insights-ai' ),
						'auditNext'         => __( 'Next', 'geo-insights-ai' ),
						'auditOpportunity'  => __( 'High AI interest, low score – fix these first.', 'geo-insights-ai' ),
						'auditScatterTitle' => __( 'Opportunity map', 'geo-insights-ai' ),
						'auditScatterHint'  => __( 'Every dot is a page AI read in the last 30 days: right = read often, low = weak GEO score. Bottom-right dots are your biggest wins – click a dot to edit the page.', 'geo-insights-ai' ),
						'auditScatterEmpty' => __( 'No AI accesses on scored pages in the last 30 days yet – the map fills up as AI systems read your content.', 'geo-insights-ai' ),
						'auditHistTitle'    => __( 'Score distribution', 'geo-insights-ai' ),
						'auditPages'        => __( 'pages', 'geo-insights-ai' ),
						'movingAvg'         => __( '7-day average', 'geo-insights-ai' ),
						'verifyTitle'       => __( 'Bot identity', 'geo-insights-ai' ),
						'treemapTitle'      => __( 'AI companies: share of accesses', 'geo-insights-ai' ),
						'treemapHint'       => __( 'Area = accesses in the selected range. Click a company to zoom into its individual bots.', 'geo-insights-ai' ),
						'sankeyTitle'       => __( 'Flows: which AI reads which page', 'geo-insights-ai' ),
						'sankeyHint'        => __( 'Left: AI bots, right: your pages. Band width = accesses. Hover a band for the exact count.', 'geo-insights-ai' ),
						'heatmapTitle'      => __( 'When do AIs read your site?', 'geo-insights-ai' ),
						'heatmapHint'       => __( 'Bot accesses of the last 7 days by weekday and hour, in your site’s timezone. Publishing before the busy hours gets new content read sooner.', 'geo-insights-ai' ),
						'heatmapEmpty'      => __( 'No bot accesses in the last 7 days yet.', 'geo-insights-ai' ),
						'hourLabel'         => __( 'Hour', 'geo-insights-ai' ),
						'gaugeTitle'        => __( 'Average GEO score', 'geo-insights-ai' ),
						'gaugeHint'         => __( 'Across all scored pages. Every point up is one more criterion your typical page fulfils.', 'geo-insights-ai' ),
					),
				)
			);
		}
	}

	/**
	 * Company + per-source metadata for the dashboard filter and the brand
	 * icons. Icon keys map to the bundled Simple Icons set in the React app;
	 * keys without a bundled icon fall back to a monogram badge there.
	 *
	 * @return array{companies:array<string,array<string,mixed>>,sources:array<string,array<string,mixed>>}
	 */
	protected static function dashboard_sources() {
		$company_icons = array(
			'openai'                 => 'openai',
			'anthropic'              => 'anthropic',
			'perplexity'             => 'perplexity',
			'mistral-ai'             => 'mistral',
			'duckduckgo'             => 'duckduckgo',
			'microsoft'              => 'microsoft',
			'meta'                   => 'meta',
			'bytedance'              => 'bytedance',
			'amazon'                 => 'amazon',
			'common-crawl'           => 'commoncrawl',
			'google'                 => 'google',
			'apple'                  => 'apple',
			'you-com'                => 'you',
			'huawei'                 => 'huawei',
			'allen-institute-for-ai' => 'ai2',
			'cohere'                 => 'cohere',
			'diffbot'                => 'diffbot',
			'timpi'                  => 'timpi',
			'webz-io'                => 'webz',
			'deepseek'               => 'deepseek',
			'xai'                    => 'x',
		);
		// Sources whose product icon differs from the company icon.
		$source_icons = array(
			'claude'  => 'claude',
			'gemini'  => 'gemini',
			'copilot' => 'copilot',
			'bingbot' => 'bing',
		);

		$companies = array();
		foreach ( GEOINS_Bots::companies() as $key => $company ) {
			$companies[ $key ] = array(
				'label'   => $company['label'],
				'icon'    => isset( $company_icons[ $key ] ) ? $company_icons[ $key ] : '',
				'sources' => $company['sources'],
			);
		}

		$sources = array();
		foreach ( $companies as $key => $company ) {
			foreach ( $company['sources'] as $slug ) {
				$sources[ $slug ] = array(
					'company' => $key,
					'icon'    => isset( $source_icons[ $slug ] ) ? $source_icons[ $slug ] : $company['icon'],
				);
			}
		}

		return array(
			'companies' => $companies,
			'sources'   => $sources,
		);
	}

	/**
	 * Live GEO checks in the block editor.
	 *
	 * @return void
	 */
	public static function editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		wp_enqueue_style( 'geoins-admin', GEOINS_URL . 'assets/css/geoins-admin.css', array(), GEOINS_VERSION );
		wp_enqueue_script(
			'geoins-editor',
			GEOINS_URL . 'assets/js/geoins-editor.js',
			array( 'wp-plugins', 'wp-element', 'wp-data', 'wp-core-data', 'wp-components', 'wp-editor', 'wp-edit-post' ),
			GEOINS_VERSION,
			true
		);

		$post       = get_post();
		$author_bio = false;
		if ( $post ) {
			$author_bio = '' !== trim( (string) get_the_author_meta( 'description', (int) $post->post_author ) );
		}

		$checks = array();
		$order  = array();
		foreach ( GEOINS_Analysis::definitions() as $id => $definition ) {
			$order[]       = $id;
			$checks[ $id ] = $definition;
		}

		wp_localize_script(
			'geoins-editor',
			'geoinsEditor',
			array(
				'checks'    => $checks,
				'order'     => $order,
				'siteHost'  => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
				'authorBio' => $author_bio,
				'i18n'      => array(
					'panelTitle' => __( 'GEO check (AI visibility)', 'geo-insights-ai' ),
					'focusTerm'  => __( 'Focus term', 'geo-insights-ai' ),
					'focusHelp'  => __( 'The statistics dashboard maps every AI access to this term. Empty = post title.', 'geo-insights-ai' ),
					'passed'     => __( 'passed', 'geo-insights-ai' ),
					'pinLabel'   => __( 'Feature in llms.txt (key content)', 'geo-insights-ai' ),
					'pinHelp'    => __( 'Pinned content appears first in llms.txt and llms-full.txt – the pages you most want AI systems to read.', 'geo-insights-ai' ),
				),
			)
		);
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require GEOINS_DIR . 'includes/admin/views/dashboard.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require GEOINS_DIR . 'includes/admin/views/settings.php';
	}

	/**
	 * Render the plain-language explainer page.
	 *
	 * @return void
	 */
	public static function render_learn() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		require GEOINS_DIR . 'includes/admin/views/learn.php';
	}

	/**
	 * Render the site-wide GEO audit page.
	 *
	 * @return void
	 */
	public static function render_audit() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		require GEOINS_DIR . 'includes/admin/views/audit.php';
	}

	/**
	 * Helper: one toggle card row on the settings page.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Label.
	 * @param string $benefit One-line benefit ("what do I get?").
	 * @param string $detail  Optional longer explanation.
	 * @return void
	 */
	public static function toggle_row( $key, $label, $benefit, $detail = '' ) {
		$settings = geoins()->settings();
		$checked  = ! empty( $settings[ $key ] );
		?>
		<div class="geoins-toggle-row">
			<label class="geoins-switch">
				<input type="checkbox" name="geoins_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $checked ); ?> />
				<span class="geoins-slider" aria-hidden="true"></span>
			</label>
			<div class="geoins-toggle-text">
				<strong><?php echo esc_html( $label ); ?></strong>
				<p class="geoins-benefit"><?php echo esc_html( $benefit ); ?></p>
				<?php if ( '' !== $detail ) : ?>
					<details class="geoins-details">
						<summary><?php esc_html_e( 'Why does this matter?', 'geo-insights-ai' ); ?></summary>
						<p><?php echo esc_html( $detail ); ?></p>
					</details>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
