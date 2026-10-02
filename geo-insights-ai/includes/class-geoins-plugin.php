<?php
/**
 * Core plugin orchestrator.
 *
 * @package GEO_Insights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class (singleton).
 */
class GEOINS_Plugin {

	/**
	 * Instance.
	 *
	 * @var GEOINS_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Cached settings.
	 *
	 * @var array<string,mixed>|null
	 */
	protected $settings = null;

	/**
	 * Singleton accessor.
	 *
	 * @return GEOINS_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Boot all modules.
	 *
	 * @return void
	 */
	protected function boot() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		GEOINS_Verify::init();
		GEOINS_Tracker::init();
		GEOINS_Beacon::init();
		GEOINS_Llms_Txt::init();
		GEOINS_Markdown::init();
		GEOINS_Robots::init();
		GEOINS_Schema::init();
		GEOINS_Meta::init();
		GEOINS_Analysis::init();
		GEOINS_Content::init();
		GEOINS_IndexNow::init();
		GEOINS_Report::init();
		GEOINS_Alerts::init();
		GEOINS_Audit::init();
		GEOINS_Rest::init();

		if ( is_admin() ) {
			GEOINS_Admin::init();
			GEOINS_Dashboard::init();
			GEOINS_Columns::init();
		}

		// Safety net: upgrade tables + cron after plugin updates.
		if ( get_option( 'geoins_db_version' ) !== GEOINS_DB_VERSION ) {
			GEOINS_Install::create_tables();
			GEOINS_Install::schedule_events();
			GEOINS_Alerts::maybe_seed();
			update_option( 'geoins_db_version', GEOINS_DB_VERSION );
		}
	}

	/**
	 * Bundled translations are only a fallback: language packs from
	 * translate.wordpress.org (wp-content/languages/plugins) take precedence
	 * and are loaded just in time by WordPress itself.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		$locale = determine_locale();
		if ( file_exists( WP_LANG_DIR . "/plugins/geo-insights-ai-{$locale}.mo" ) || file_exists( WP_LANG_DIR . "/plugins/geo-insights-ai-{$locale}.l10n.php" ) ) {
			return;
		}
		// WordPress 6.5+ prefers the .l10n.php variant of this path automatically.
		load_textdomain( 'geo-insights-ai', GEOINS_DIR . "languages/geo-insights-ai-{$locale}.mo", $locale );
	}

	/**
	 * Settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function settings() {
		if ( null === $this->settings ) {
			$saved          = get_option( 'geoins_settings', array() );
			$this->settings = wp_parse_args( is_array( $saved ) ? $saved : array(), GEOINS_Install::defaults() );
		}
		return $this->settings;
	}

	/**
	 * Clear the settings cache (after save).
	 *
	 * @return void
	 */
	public function flush_settings_cache() {
		$this->settings = null;
	}

	/**
	 * Detect an active full SEO plugin.
	 *
	 * @return string|false Plugin name or false.
	 */
	public function seo_plugin_active() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return 'All in One SEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'SEOPress';
		}
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return 'The SEO Framework';
		}
		return false;
	}

	/**
	 * Site-wide GEO status checks for the dashboard.
	 *
	 * @return array<int,array{id:string,label:string,status:string,note:string}> Each: { id, label, status(ok|warn|bad), note }
	 */
	public function status_checks() {
		$settings = $this->settings();
		$checks   = array();

		// Site visible to search engines?
		$public   = (bool) get_option( 'blog_public' );
		$checks[] = array(
			'id'     => 'public',
			'label'  => __( 'Site visible to crawlers', 'geo-insights-ai' ),
			'status' => $public ? 'ok' : 'bad',
			'note'   => $public
				? __( 'Search engines and AI crawlers may index the site.', 'geo-insights-ai' )
				: __( '"Discourage search engines" is enabled (Settings → Reading). No AI system can find you until this is turned off.', 'geo-insights-ai' ),
		);

		// Physical robots.txt: analyzed, not blanket-warned. A physical file
		// that blocks no citation-relevant bots and matches the configured
		// blocks is fine and reported green.
		$robots_summary = GEOINS_Robots::physical_summary();
		$checks[]       = array(
			'id'     => 'robots_virtual',
			'label'  => __( 'robots.txt', 'geo-insights-ai' ),
			'status' => $robots_summary['status'],
			'note'   => $robots_summary['note'],
		);

		// Blocking citation-relevant bots?
		$blocked_relevant = array();
		$bots             = GEOINS_Bots::bots();
		foreach ( (array) $settings['blocked_bots'] as $slug ) {
			if ( isset( $bots[ $slug ] ) && in_array( (int) $bots[ $slug ]['category'], array( GEOINS_Bots::CAT_RETRIEVAL, GEOINS_Bots::CAT_AGENT, GEOINS_Bots::CAT_SEARCH ), true ) ) {
				$blocked_relevant[] = $bots[ $slug ]['label'];
			}
		}
		$checks[] = array(
			'id'     => 'citation_bots',
			'label'  => __( 'Citation-relevant AI bots allowed', 'geo-insights-ai' ),
			'status' => empty( $blocked_relevant ) ? 'ok' : 'warn',
			'note'   => empty( $blocked_relevant )
				? __( 'Retrieval and agent bots may read your pages – you can appear in AI answers.', 'geo-insights-ai' )
				/* translators: %s: comma-separated bot names. */
				: sprintf( __( 'You are blocking %s. These bots put you into AI answers – blocking them removes you from citations.', 'geo-insights-ai' ), implode( ', ', $blocked_relevant ) ),
		);

		// llms.txt.
		$checks[] = array(
			'id'     => 'llms_txt',
			'label'  => __( 'llms.txt available', 'geo-insights-ai' ),
			'status' => ! empty( $settings['llms_txt'] ) ? 'ok' : 'warn',
			'note'   => ! empty( $settings['llms_txt'] )
				/* translators: %s: llms.txt URL. */
				? sprintf( __( 'Served at %s.', 'geo-insights-ai' ), home_url( '/llms.txt' ) )
				: __( 'Disabled. It costs nothing and helps AI agents understand your site structure.', 'geo-insights-ai' ),
		);

		// Pretty permalinks: .md endpoints and the IndexNow key file need them.
		$pretty   = '' !== get_option( 'permalink_structure' );
		$checks[] = array(
			'id'     => 'permalinks',
			'label'  => __( 'Pretty permalinks enabled', 'geo-insights-ai' ),
			'status' => $pretty ? 'ok' : 'warn',
			'note'   => $pretty
				? __( 'Readable URLs are active – .md endpoints and the IndexNow key file work.', 'geo-insights-ai' )
				: __( 'Plain permalinks are active (Settings → Permalinks). The Markdown endpoints and the IndexNow key file cannot be served, and readable URLs help every crawler.', 'geo-insights-ai' ),
		);

		// Sitemap.
		$sitemap_on = (bool) get_option( 'blog_public' ) && function_exists( 'get_sitemap_url' ) && get_sitemap_url( 'index' );
		$checks[]   = array(
			'id'     => 'sitemap',
			'label'  => __( 'XML sitemap available', 'geo-insights-ai' ),
			'status' => $sitemap_on ? 'ok' : 'warn',
			'note'   => $sitemap_on
				? __( 'Retrieval bots use the sitemap to discover new content quickly.', 'geo-insights-ai' )
				: __( 'No sitemap found. WordPress ships one by default – a plugin or setting may have disabled it.', 'geo-insights-ai' ),
		);

		// Bot verification.
		$verify_on = ! empty( $settings['verify_bots'] );
		$checks[]  = array(
			'id'     => 'verify',
			'label'  => __( 'Bot identity verification', 'geo-insights-ai' ),
			'status' => $verify_on ? 'ok' : 'warn',
			'note'   => $verify_on
				? __( 'Bot hits are checked against the official IP ranges of OpenAI, Anthropic, Perplexity, Google and Microsoft – impostors are flagged in your statistics.', 'geo-insights-ai' )
				: __( 'Off. User agents can be faked; enabling verification separates real AI bots from impostors (fetches public IP lists once a day).', 'geo-insights-ai' ),
		);

		// IndexNow.
		$indexnow_on = ! empty( $settings['indexnow'] );
		$checks[]    = array(
			'id'     => 'indexnow',
			'label'  => __( 'IndexNow: instant indexing pings', 'geo-insights-ai' ),
			'status' => $indexnow_on ? 'ok' : 'warn',
			'note'   => $indexnow_on
				? __( 'New and updated content is pushed to Bing & co. within minutes. Bing feeds Copilot and parts of ChatGPT search – faster indexing means faster AI citations.', 'geo-insights-ai' )
				: __( 'Off. Without it, AI-relevant search indexes only learn about new content when they crawl you again – enabling IndexNow speeds that up to minutes.', 'geo-insights-ai' ),
		);

		// SEO plugin coexistence.
		$seo      = $this->seo_plugin_active();
		$checks[] = array(
			'id'     => 'seo_plugin',
			'label'  => __( 'No duplicate SEO output', 'geo-insights-ai' ),
			'status' => 'ok',
			'note'   => $seo
				/* translators: %s: SEO plugin name. */
				? sprintf( __( '%s detected – GEO Insights automatically leaves schema and meta tags to it and only adds what is missing (tracking, llms.txt, robots control, GEO checks).', 'geo-insights-ai' ), $seo )
				: __( 'No other SEO plugin active – GEO Insights provides schema and meta tags itself.', 'geo-insights-ai' ),
		);

		// Schema active?
		$schema_on = ! empty( $settings['schema'] ) && ! $seo;
		$checks[]  = array(
			'id'     => 'schema',
			'label'  => __( 'Structured data (schema.org)', 'geo-insights-ai' ),
			'status' => ( $schema_on || $seo ) ? 'ok' : 'warn',
			'note'   => ( $schema_on || $seo )
				? __( 'Machine-readable metadata helps AI systems understand and attribute your content.', 'geo-insights-ai' )
				: __( 'Disabled. Structured data is the cheapest way to make your content machine-readable.', 'geo-insights-ai' ),
		);

		return $checks;
	}
}
