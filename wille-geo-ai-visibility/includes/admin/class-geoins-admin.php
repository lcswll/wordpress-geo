<?php
/**
 * Admin: menu, assets, settings registration.
 *
 * @package Wille_GEO
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
	const REVIEW_URL = 'https://wordpress.org/support/plugin/wille-geo-ai-visibility/reviews/#new-post';

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
		add_action( 'admin_notices', array( __CLASS__, 'first_visit_notice' ) );
		add_action( 'admin_post_geoins_enable_alerts', array( __CLASS__, 'enable_alerts' ) );
		add_action( 'admin_post_geoins_hide_visit_notice', array( __CLASS__, 'hide_visit_notice' ) );
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
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=wille-geo-learn' ) ) . '">' . esc_html__( 'How it works', 'wille-geo-ai-visibility' ) . '</a>';
		$links[] = '<a href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr__( 'Rate Wille GEO on WordPress.org (opens in a new tab)', 'wille-geo-ai-visibility' ) . '">' . esc_html__( 'Rate ★★★★★', 'wille-geo-ai-visibility' ) . '</a>';
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
			esc_html__( 'Wille GEO is made by %1$s. Does it help you? A %2$s review on WordPress.org helps others find it – thank you!', 'wille-geo-ai-visibility' ),
			'<a href="' . esc_url( self::AUTHOR_URL ) . '" target="_blank" rel="noopener">' . esc_html( self::AUTHOR ) . '</a>',
			'<a class="geoins-footer-stars" href="' . esc_url( self::REVIEW_URL ) . '" target="_blank" rel="noopener">★★★★★</a>'
		);
	}

	/**
	 * One-time notice after activation – only on the Plugins screen the user
	 * just activated from, never elsewhere in the admin.
	 *
	 * @return void
	 */
	public static function welcome_notice() {
		if ( ! get_transient( 'geoins_welcome_notice' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}
		if ( 'toplevel_page_wille-geo' === $screen->id ) {
			delete_transient( 'geoins_welcome_notice' );
			return;
		}
		if ( 'plugins' !== $screen->id ) {
			return;
		}
		delete_transient( 'geoins_welcome_notice' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Wille GEO is active.', 'wille-geo-ai-visibility' ); ?></strong>
				<?php esc_html_e( 'AI bot accesses and visitors from AI answers are being recorded from now on – data appears as soon as the first AI reaches your site.', 'wille-geo-ai-visibility' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wille-geo' ) ); ?>"><?php esc_html_e( 'Open the statistics dashboard', 'wille-geo-ai-visibility' ); ?></a>
				· <a href="<?php echo esc_url( admin_url( 'admin.php?page=wille-geo-learn' ) ); ?>"><?php esc_html_e( 'New here? How it all works, in plain language.', 'wille-geo-ai-visibility' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * The newest first-contact alert (new AI bot or first visitors from an AI assistant) that the site owner
	 * has not seen yet – in the plugin dashboard or in the notice below.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function unseen_first_visit() {
		$seen = (string) get_option( 'geoins_visit_notice_seen', '' );
		foreach ( GEOINS_Alerts::get_alerts() as $alert ) {
			// Alerts are stored newest first: everything from the last seen one on is old.
			if ( (string) ( $alert['id'] ?? '' ) === $seen ) {
				return null;
			}
			if ( ! empty( $alert['read'] ) || ! in_array( $alert['type'] ?? '', array( 'new_bot', 'new_referral' ), true ) ) {
				continue;
			}
			return $alert;
		}
		return null;
	}

	/**
	 * Notice on the WordPress dashboard and the Plugins screen when a new AI read the site for the first time –
	 * the moment the plugin proves its value, also for owners who rarely open its own screens.
	 *
	 * @return void
	 */
	public static function first_visit_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		$alert = self::unseen_first_visit();
		if ( ! $alert ) {
			return;
		}
		$hide = wp_nonce_url( admin_url( 'admin-post.php?action=geoins_hide_visit_notice&alert=' . rawurlencode( (string) $alert['id'] ) ), 'geoins_hide_visit_notice' );
		?>
		<div class="notice notice-success">
			<p>
				<strong><?php esc_html_e( 'Wille GEO', 'wille-geo-ai-visibility' ); ?>:</strong>
				<?php echo esc_html( GEOINS_Alerts::alert_text( $alert ) ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wille-geo' ) ); ?>"><?php esc_html_e( 'Open the statistics dashboard', 'wille-geo-ai-visibility' ); ?></a>
				· <a href="<?php echo esc_url( $hide ); ?>"><?php esc_html_e( 'Dismiss', 'wille-geo-ai-visibility' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Remember an alert as seen (dashboard visit or "Dismiss" in the notice).
	 *
	 * @param string $alert_id Alert id; empty = the newest alert.
	 * @return void
	 */
	public static function mark_visit_notice_seen( $alert_id = '' ) {
		if ( '' === $alert_id ) {
			$alerts   = GEOINS_Alerts::get_alerts();
			$alert_id = $alerts ? (string) ( $alerts[0]['id'] ?? '' ) : '';
		}
		if ( '' !== $alert_id ) {
			update_option( 'geoins_visit_notice_seen', $alert_id, false );
		}
	}

	/**
	 * "Dismiss" link of the first-visit notice.
	 *
	 * @return void
	 */
	public static function hide_visit_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wille-geo-ai-visibility' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'geoins_hide_visit_notice' );
		self::mark_visit_notice_seen( isset( $_GET['alert'] ) ? sanitize_key( wp_unslash( $_GET['alert'] ) ) : '' );
		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url() );
		exit;
	}

	/**
	 * One-click "Turn on email notifications" from the first-run panel.
	 *
	 * @return void
	 */
	public static function enable_alerts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'wille-geo-ai-visibility' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'geoins_enable_alerts' );
		self::turn_on_alert_emails();
		wp_safe_redirect( admin_url( 'admin.php?page=wille-geo' ) );
		exit;
	}

	/**
	 * Switch on the email alerts and keep every other setting.
	 *
	 * @return void
	 */
	public static function turn_on_alert_emails() {
		// Full settings (saved values merged with defaults): the sanitize callback treats a missing flag as "off".
		$settings                 = geoins()->settings();
		$settings['alerts_email'] = 1;
		update_option( 'geoins_settings', $settings );
		geoins()->flush_settings_cache();
	}

	/**
	 * Admin menu.
	 *
	 * @return void
	 */
	public static function menu() {
		$hooks   = array();
		$hooks[] = add_menu_page(
			__( 'Wille GEO', 'wille-geo-ai-visibility' ),
			__( 'Wille GEO', 'wille-geo-ai-visibility' ),
			'manage_options',
			'wille-geo',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-visibility',
			58
		);
		$hooks[] = add_submenu_page(
			'wille-geo',
			__( 'AI Statistics', 'wille-geo-ai-visibility' ),
			__( 'AI Statistics', 'wille-geo-ai-visibility' ),
			'manage_options',
			'wille-geo',
			array( __CLASS__, 'render_dashboard' )
		);
		$hooks[] = add_submenu_page(
			'wille-geo',
			__( 'GEO Audit', 'wille-geo-ai-visibility' ),
			__( 'GEO Audit', 'wille-geo-ai-visibility' ),
			'edit_others_posts',
			'wille-geo-audit',
			array( __CLASS__, 'render_audit' )
		);
		$hooks[] = add_submenu_page(
			'wille-geo',
			__( 'GEO Settings', 'wille-geo-ai-visibility' ),
			__( 'Settings', 'wille-geo-ai-visibility' ),
			'manage_options',
			'wille-geo-settings',
			array( __CLASS__, 'render_settings' )
		);
		$hooks[] = add_submenu_page(
			'wille-geo',
			__( 'How it works', 'wille-geo-ai-visibility' ),
			__( 'How it works', 'wille-geo-ai-visibility' ),
			'edit_posts',
			'wille-geo-learn',
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
			'<a href="' . esc_url( admin_url( 'admin.php?page=wille-geo' ) ) . '">' . esc_html__( 'Statistics', 'wille-geo-ai-visibility' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=wille-geo-settings' ) ) . '">' . esc_html__( 'Settings', 'wille-geo-ai-visibility' ) . '</a>'
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
					'passed' => __( 'passed', 'wille-geo-ai-visibility' ),
				),
			)
		);

		// React bundle: statistics page + audit page (both roots live in it).
		$is_stats = 'toplevel_page_wille-geo' === $hook || ( ! empty( self::$page_hooks[0] ) && self::$page_hooks[0] === $hook );
		$is_audit = '_page_wille-geo-audit' === substr( $hook, -21 );
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
					'restUrl'         => esc_url_raw( rest_url() ),
					'nonce'           => wp_create_nonce( 'wp_rest' ),
					'exportUrl'       => wp_nonce_url( admin_url( 'admin-post.php?action=geoins_export' ), 'geoins_export' ),
					'learnUrl'        => admin_url( 'admin.php?page=wille-geo-learn' ),
					'auditUrl'        => admin_url( 'admin.php?page=wille-geo-audit' ),
					// First-run panel: one-click email notification for the first AI visit.
					'alertsEmail'     => ! empty( geoins()->settings()['alerts_email'] ),
					'enableAlertsUrl' => wp_nonce_url( admin_url( 'admin-post.php?action=geoins_enable_alerts' ), 'geoins_enable_alerts' ),
					'decimalPoint'    => $GLOBALS['wp_locale']->number_format['decimal_point'],
					'companies'       => $directory['companies'],
					'sources'         => $directory['sources'],
					'checkLabels'     => $check_labels,
					'weekdays'        => $weekdays,
					'catLabels'       => array(
						'training'  => __( 'Training', 'wille-geo-ai-visibility' ),
						'retrieval' => __( 'Retrieval', 'wille-geo-ai-visibility' ),
						'agent'     => __( 'Agent', 'wille-geo-ai-visibility' ),
						'search'    => __( 'Search', 'wille-geo-ai-visibility' ),
						'referral'  => __( 'AI visitors', 'wille-geo-ai-visibility' ),
					),
					'i18n'            => array(
						'range'               => __( 'Date range', 'wille-geo-ai-visibility' ),
						'days'                => __( 'days', 'wille-geo-ai-visibility' ),
						'exportCsv'           => __( 'Export CSV', 'wille-geo-ai-visibility' ),
						'hits'                => __( 'accesses', 'wille-geo-ai-visibility' ),
						'visitors'            => __( 'visitors', 'wille-geo-ai-visibility' ),
						'noData'              => __( 'No data yet. AI accesses appear here as soon as a known AI bot or an AI-referred visitor reaches your site.', 'wille-geo-ai-visibility' ),
						'loadError'           => __( 'Could not load statistics.', 'wille-geo-ai-visibility' ),
						'sessionExpired'      => __( 'Your session check expired – reload the page to continue.', 'wille-geo-ai-visibility' ),
						'reload'              => __( 'Reload page', 'wille-geo-ai-visibility' ),
						'term'                => __( 'Term', 'wille-geo-ai-visibility' ),
						'page'                => __( 'Page', 'wille-geo-ai-visibility' ),
						'ai'                  => __( 'AI accesses', 'wille-geo-ai-visibility' ),
						'from'                => __( 'AI source', 'wille-geo-ai-visibility' ),
						'vsPrev'              => __( 'vs. previous period', 'wille-geo-ai-visibility' ),
						'newLabel'            => __( 'new', 'wille-geo-ai-visibility' ),
						'verified'            => __( 'verified', 'wille-geo-ai-visibility' ),
						'spoofed'             => __( 'impostors', 'wille-geo-ai-visibility' ),
						'unchecked'           => __( 'unchecked', 'wille-geo-ai-visibility' ),
						'verifyOff'           => __( 'Identity verification is off. User agents can be faked – enable verification in the settings to separate real AI bots from impostors.', 'wille-geo-ai-visibility' ),
						'timelineTitle'       => __( 'AI accesses per day', 'wille-geo-ai-visibility' ),
						'timelineHint'        => __( 'Agent + Retrieval = citation-relevant: an AI read your page to answer a real question. Training only feeds models.', 'wille-geo-ai-visibility' ),
						'botsTitle'           => __( 'Which AI bots?', 'wille-geo-ai-visibility' ),
						'referralsTitle'      => __( 'Human visitors from AI answers', 'wille-geo-ai-visibility' ),
						'referralsHint'       => __( 'Real people who clicked your link inside ChatGPT, Perplexity & co. This is the payoff of GEO.', 'wille-geo-ai-visibility' ),
						'matrixTitle'         => __( 'Terms & pages: which AI reads what?', 'wille-geo-ai-visibility' ),
						'matrixHint'          => __( 'AI crawlers do not transmit search queries. This matrix maps every access to the focus term of the page (set it in the GEO check box on the edit screen) – the honest, practical equivalent.', 'wille-geo-ai-visibility' ),
						'landingsTitle'       => __( 'AI visitors: landing pages', 'wille-geo-ai-visibility' ),
						'statusTitle'         => __( 'GEO status of your site', 'wille-geo-ai-visibility' ),
						'emptyTitle'          => __( 'Waiting for the first AI visit', 'wille-geo-ai-visibility' ),
						'startIntro'          => __( 'Until then, here is what Wille GEO already found on your site:', 'wille-geo-ai-visibility' ),
						'startStatusOk'       => __( 'Your site is open to AI crawlers: nothing blocks ChatGPT, Perplexity or Claude.', 'wille-geo-ai-visibility' ),
						'startStatusBlocking' => __( 'Something keeps AI systems away from your site – see the red and yellow points in the GEO status below.', 'wille-geo-ai-visibility' ),
						/* translators: %s: number of optional features that are switched off. */
						'startStatusOptional' => __( 'Optional: %s more features in the GEO status below can improve your visibility.', 'wille-geo-ai-visibility' ),
						'startContentTitle'   => __( 'Your content, checked for AI search', 'wille-geo-ai-visibility' ),
						/* translators: 1: number of analyzed pages, 2: average score, 3: maximum score. */
						'startContentScore'   => __( '%1$s pages analyzed, average GEO score %2$s of %3$s.', 'wille-geo-ai-visibility' ),
						'startContentWeakest' => __( 'These pages have the most room for improvement:', 'wille-geo-ai-visibility' ),
						'startAuditAll'       => __( 'Open the full GEO analysis', 'wille-geo-ai-visibility' ),
						'startAlertsTitle'    => __( 'Notification for the first AI visit', 'wille-geo-ai-visibility' ),
						'startAlertsOn'       => __( 'You will get an email as soon as a new AI reads your site for the first time.', 'wille-geo-ai-visibility' ),
						'startAlertsOff'      => __( 'Get an email as soon as a new AI reads your site for the first time – at most one email per day.', 'wille-geo-ai-visibility' ),
						'startAlertsButton'   => __( 'Turn on email notifications', 'wille-geo-ai-visibility' ),
						'learnLink'           => __( 'New here? How it all works, in plain language.', 'wille-geo-ai-visibility' ),
						'filterLabel'         => __( 'Filter by AI', 'wille-geo-ai-visibility' ),
						'filterAll'           => __( 'All AIs', 'wille-geo-ai-visibility' ),
						'filterNoData'        => __( 'No data for this AI in the selected range.', 'wille-geo-ai-visibility' ),
						'resetFilter'         => __( 'Show all AIs', 'wille-geo-ai-visibility' ),
						'alertsTitle'         => __( 'What happened', 'wille-geo-ai-visibility' ),
						'markRead'            => __( 'Mark all as read', 'wille-geo-ai-visibility' ),
						/* translators: %s: AI assistant name. */
						'alertNewRef'         => __( 'First human visitors from %s! Your content is being cited there.', 'wille-geo-ai-visibility' ),
						/* translators: %s: bot name. */
						'alertNewBot'         => __( '%s crawled your site for the first time – a citation-relevant AI is now reading you.', 'wille-geo-ai-visibility' ),
						/* translators: %s: bot name. */
						'alertNewTrain'       => __( '%s (training crawler) visited your site for the first time.', 'wille-geo-ai-visibility' ),
						/* translators: 1: visitor count, 2: average. */
						'alertSpike'          => __( 'AI visitor spike: %1$s visitors from AI answers yesterday (recent average: %2$s/day).', 'wille-geo-ai-visibility' ),
						'unknownTitle'        => __( 'AI radar: unrecognized AI-like crawlers', 'wille-geo-ai-visibility' ),
						'unknownHint'         => __( 'These user agents sound like AI systems but are not in the registry yet – so their hits are NOT in the statistics above. Frequent entries are worth adding via the geoins_bots filter (or report them to the plugin).', 'wille-geo-ai-visibility' ),
						'unknownUa'           => __( 'User agent', 'wille-geo-ai-visibility' ),
						'lastSeen'            => __( 'Last seen', 'wille-geo-ai-visibility' ),
						'dismiss'             => __( 'Dismiss', 'wille-geo-ai-visibility' ),
						/* translators: 1: pages analyzed so far, 2: total pages. */
						'auditScanning'       => __( 'Analyzing content … %1$s of %2$s done', 'wille-geo-ai-visibility' ),
						'auditTitleCol'       => __( 'Title', 'wille-geo-ai-visibility' ),
						'auditScoreCol'       => __( 'GEO score', 'wille-geo-ai-visibility' ),
						'auditFailsCol'       => __( 'Open improvements', 'wille-geo-ai-visibility' ),
						'auditHitsCol'        => __( 'AI accesses (30d)', 'wille-geo-ai-visibility' ),
						'auditModCol'         => __( 'Updated', 'wille-geo-ai-visibility' ),
						'auditTypePost'       => __( 'Post', 'wille-geo-ai-visibility' ),
						'auditTypePage'       => __( 'Page', 'wille-geo-ai-visibility' ),
						'auditAllTypes'       => __( 'All types', 'wille-geo-ai-visibility' ),
						'auditEmpty'          => __( 'No published content to audit yet.', 'wille-geo-ai-visibility' ),
						'auditPerfect'        => __( 'All checks passed', 'wille-geo-ai-visibility' ),
						'auditRescan'         => __( 'Re-scan', 'wille-geo-ai-visibility' ),
						'auditMore'           => __( 'more', 'wille-geo-ai-visibility' ),
						'auditPrev'           => __( 'Previous', 'wille-geo-ai-visibility' ),
						'auditNext'           => __( 'Next', 'wille-geo-ai-visibility' ),
						'auditOpportunity'    => __( 'High AI interest, low score – fix these first.', 'wille-geo-ai-visibility' ),
						'auditScatterTitle'   => __( 'Opportunity map', 'wille-geo-ai-visibility' ),
						'auditScatterHint'    => __( 'Every dot is a page AI read in the last 30 days: right = read often, low = weak GEO score. Bottom-right dots are your biggest wins – click a dot to edit the page.', 'wille-geo-ai-visibility' ),
						'auditScatterEmpty'   => __( 'No AI accesses on scored pages in the last 30 days yet – the map fills up as AI systems read your content.', 'wille-geo-ai-visibility' ),
						'auditHistTitle'      => __( 'Score distribution', 'wille-geo-ai-visibility' ),
						'auditPages'          => __( 'pages', 'wille-geo-ai-visibility' ),
						'movingAvg'           => __( '7-day average', 'wille-geo-ai-visibility' ),
						'verifyTitle'         => __( 'Bot identity', 'wille-geo-ai-visibility' ),
						'treemapTitle'        => __( 'AI companies: share of accesses', 'wille-geo-ai-visibility' ),
						'treemapHint'         => __( 'Area = accesses in the selected range. Click a company to zoom into its individual bots.', 'wille-geo-ai-visibility' ),
						'sankeyTitle'         => __( 'Flows: which AI reads which page', 'wille-geo-ai-visibility' ),
						'sankeyHint'          => __( 'Left: AI bots, right: your pages. Band width = accesses. Hover a band for the exact count.', 'wille-geo-ai-visibility' ),
						'heatmapTitle'        => __( 'When do AIs read your site?', 'wille-geo-ai-visibility' ),
						'heatmapHint'         => __( 'Bot accesses of the last 7 days by weekday and hour, in your site’s timezone. Publishing before the busy hours gets new content read sooner.', 'wille-geo-ai-visibility' ),
						'heatmapEmpty'        => __( 'No bot accesses in the last 7 days yet.', 'wille-geo-ai-visibility' ),
						'hourLabel'           => __( 'Hour', 'wille-geo-ai-visibility' ),
						'gaugeTitle'          => __( 'Average GEO score', 'wille-geo-ai-visibility' ),
						'gaugeHint'           => __( 'Across all scored pages. Every point up is one more criterion your typical page fulfils.', 'wille-geo-ai-visibility' ),
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
					'panelTitle' => __( 'GEO check (AI visibility)', 'wille-geo-ai-visibility' ),
					'focusTerm'  => __( 'Focus term', 'wille-geo-ai-visibility' ),
					'focusHelp'  => __( 'The statistics dashboard maps every AI access to this term. Empty = post title.', 'wille-geo-ai-visibility' ),
					'passed'     => __( 'passed', 'wille-geo-ai-visibility' ),
					'pinLabel'   => __( 'Feature in llms.txt (key content)', 'wille-geo-ai-visibility' ),
					'pinHelp'    => __( 'Pinned content appears first in llms.txt and llms-full.txt – the pages you most want AI systems to read.', 'wille-geo-ai-visibility' ),
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
		// The dashboard lists all alerts, so the first-visit notice elsewhere has done its job.
		self::mark_visit_notice_seen();
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
						<summary><?php esc_html_e( 'Why does this matter?', 'wille-geo-ai-visibility' ); ?></summary>
						<p><?php echo esc_html( $detail ); ?></p>
					</details>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
