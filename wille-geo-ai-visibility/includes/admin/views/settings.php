<?php
/**
 * Settings view: every option ships with a direct benefit explanation.
 *
 * @package Wille_GEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$geoins_settings = geoins()->settings();
$geoins_seo      = geoins()->seo_plugin_active();
$geoins_bots     = GEOINS_Bots::bots();
$geoins_cats     = GEOINS_Bots::category_labels();
$geoins_expl     = GEOINS_Bots::category_explainers();
$geoins_blocked  = (array) $geoins_settings['blocked_bots'];

$geoins_by_cat = array();
foreach ( $geoins_bots as $geoins_slug => $geoins_bot ) {
	$geoins_by_cat[ (int) $geoins_bot['category'] ][ $geoins_slug ] = $geoins_bot;
}
$geoins_cat_order = array( GEOINS_Bots::CAT_AGENT, GEOINS_Bots::CAT_RETRIEVAL, GEOINS_Bots::CAT_SEARCH, GEOINS_Bots::CAT_TRAINING );
?>
<div class="wrap geoins-wrap">
	<?php GEOINS_Admin::header( 'wille-geo-settings' ); ?>
	<h1><?php esc_html_e( 'GEO Settings', 'wille-geo-ai-visibility' ); ?></h1>
	<?php settings_errors(); // Custom top-level menu pages must render "Settings saved." themselves. ?>
	<p class="geoins-intro"><?php esc_html_e( 'Everything is free, runs on your server and stores no personal data. External services are only contacted by the opt-in features that say so. Every option tells you what you get from it.', 'wille-geo-ai-visibility' ); ?></p>

	<form method="post" action="options.php">
		<?php settings_fields( 'geoins_settings_group' ); ?>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'Tracking', 'wille-geo-ai-visibility' ); ?></h2>
			<?php
			GEOINS_Admin::toggle_row(
				'track_bots',
				__( 'Log AI bot accesses', 'wille-geo-ai-visibility' ),
				__( 'Benefit: see which AI (ChatGPT, Claude, Perplexity …) reads which page – the basis of all statistics.', 'wille-geo-ai-visibility' ),
				__( 'Bots are recognized by their official user-agent signatures. Stored per access: bot name, category, page and time – nothing else. No IP addresses, no cookies, no personal data, fully GDPR-friendly.', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'track_referrals',
				__( 'Log human visitors from AI answers', 'wille-geo-ai-visibility' ),
				__( 'Benefit: measure the actual payoff of GEO – people who click through to you from ChatGPT, Perplexity & co.', 'wille-geo-ai-visibility' ),
				__( 'Detected via the referrer domain and via utm_source (ChatGPT appends utm_source=chatgpt.com to cited links, which survives even when the referrer is stripped). Note: many AI visits arrive without any referrer, so the real number is higher than what any tool can show.', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'verify_bots',
				__( 'Verify bot identity against official IP ranges', 'wille-geo-ai-visibility' ),
				__( 'Benefit: user agents can be faked. This separates real AI bots from impostors, so your statistics show provable AI interest.', 'wille-geo-ai-visibility' ),
				__( 'Once a day the plugin downloads the public IP lists that OpenAI, Anthropic, Perplexity, Google and Microsoft publish for their crawlers and marks each logged hit as verified, failed (claimed identity from a foreign IP) or unchecked. The visitor IP is compared in memory only and never stored. Because it makes external requests, it is opt-in.', 'wille-geo-ai-visibility' )
			);
			if ( ! empty( $geoins_settings['verify_bots'] ) ) :
				?>
				<p class="description"><?php echo esc_html( GEOINS_Verify::ranges_info() ); ?></p>
				<div class="geoins-field">
					<label for="geoins-proxy"><strong><?php esc_html_e( 'Site runs behind a proxy/CDN?', 'wille-geo-ai-visibility' ); ?></strong></label>
					<select id="geoins-proxy" name="geoins_settings[proxy_header]">
						<option value="" <?php selected( $geoins_settings['proxy_header'], '' ); ?>><?php esc_html_e( 'No – use the direct connection IP', 'wille-geo-ai-visibility' ); ?></option>
						<option value="cf" <?php selected( $geoins_settings['proxy_header'], 'cf' ); ?>><?php esc_html_e( 'Yes, Cloudflare (CF-Connecting-IP)', 'wille-geo-ai-visibility' ); ?></option>
						<option value="xff" <?php selected( $geoins_settings['proxy_header'], 'xff' ); ?>><?php esc_html_e( 'Yes, other proxy (X-Forwarded-For)', 'wille-geo-ai-visibility' ); ?></option>
					</select>
					<p class="geoins-benefit"><?php esc_html_e( 'Important: behind a CDN the direct connection IP is the proxy – every real bot would be flagged as an impostor. Select your setup so the original visitor IP is used. Only enable a header your proxy actually sets, because these headers can be faked otherwise.', 'wille-geo-ai-visibility' ); ?></p>
				</div>
			<?php endif; ?>
			<?php
			GEOINS_Admin::toggle_row(
				'referral_beacon',
				__( 'Count AI visitors behind full-page caches (beacon)', 'wille-geo-ai-visibility' ),
				__( 'Benefit: cached pages never run PHP, so AI visitors on them are invisible to server-side tracking. This tiny browser beacon (<1 kB) closes that gap.', 'wille-geo-ai-visibility' ),
				__( 'The beacon runs only for logged-out visitors, only checks whether the visit came from a known AI (referrer domain or utm_source) and only then reports the source and page path to your own site. No cookies, no IPs, no fingerprinting, no external service. Double counting with the server-side tracker is prevented automatically. Leave this off if your site does not use full-page caching – the PHP tracker already sees everything.', 'wille-geo-ai-visibility' )
			);
			?>
			<div class="geoins-field">
				<label for="geoins-retention"><strong><?php esc_html_e( 'Keep statistics for', 'wille-geo-ai-visibility' ); ?></strong></label>
				<select id="geoins-retention" name="geoins_settings[retention_days]">
					<?php
					$geoins_retentions = array(
						30  => __( '30 days', 'wille-geo-ai-visibility' ),
						90  => __( '90 days', 'wille-geo-ai-visibility' ),
						180 => __( '180 days', 'wille-geo-ai-visibility' ),
						365 => __( '1 year', 'wille-geo-ai-visibility' ),
						0   => __( 'forever', 'wille-geo-ai-visibility' ),
					);
					foreach ( $geoins_retentions as $geoins_val => $geoins_label ) :
						?>
						<option value="<?php echo esc_attr( (string) $geoins_val ); ?>" <?php selected( (int) $geoins_settings['retention_days'], $geoins_val ); ?>><?php echo esc_html( $geoins_label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="geoins-benefit"><?php esc_html_e( 'Benefit: keeps your database small. Older entries are deleted automatically once a day.', 'wille-geo-ai-visibility' ); ?></p>
			</div>
		</div>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'Weekly email report', 'wille-geo-ai-visibility' ); ?></h2>
			<?php
			GEOINS_Admin::toggle_row(
				'weekly_report',
				__( 'Send a weekly AI visibility report by email', 'wille-geo-ai-visibility' ),
				__( 'Benefit: your AI numbers come to you – totals with trend, top bots, AI visitors, most-read pages and anything that needs attention, once a week.', 'wille-geo-ai-visibility' ),
				__( 'Sent via your normal WordPress mail system, only when there actually was AI activity that week – quiet sites are never spammed. You can also trigger it manually with the WP-CLI command "wp geoins report".', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'alerts_email',
				__( 'Email me on citation alerts', 'wille-geo-ai-visibility' ),
				__( 'Benefit: know the moment it matters – the first crawl by a new AI, the first visitors from an AI assistant, or a sudden spike in AI visitors.', 'wille-geo-ai-visibility' ),
				__( 'Alerts always appear in the dashboard; this additionally sends them by email (bundled, at most one mail per day). Uses the recipient below.', 'wille-geo-ai-visibility' )
			);
			?>
			<div class="geoins-field">
				<label for="geoins-report-recipient"><strong><?php esc_html_e( 'Recipient', 'wille-geo-ai-visibility' ); ?></strong></label>
				<input type="email" id="geoins-report-recipient" name="geoins_settings[report_recipient]" value="<?php echo esc_attr( $geoins_settings['report_recipient'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
				<p class="geoins-benefit"><?php esc_html_e( 'Empty = the site admin email address.', 'wille-geo-ai-visibility' ); ?></p>
			</div>
		</div>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'AI crawler access (robots.txt)', 'wille-geo-ai-visibility' ); ?></h2>
			<?php
			GEOINS_Admin::toggle_row(
				'robots_control',
				__( 'Manage AI crawler rules in robots.txt', 'wille-geo-ai-visibility' ),
				__( 'Benefit: decide per bot who may read your content – with a clear warning before you block anything that costs you AI citations.', 'wille-geo-ai-visibility' ),
				__( 'Rules are appended to the WordPress-generated robots.txt. A physical robots.txt file in the web root would override them – the status panel on the statistics page warns you if one exists. Note that robots.txt is an honor system; the well-known bots respect it.', 'wille-geo-ai-visibility' )
			);

			if ( GEOINS_Robots::physical_file_exists() ) :
				$geoins_robots = GEOINS_Robots::physical_summary();
				?>
				<div class="notice notice-<?php echo 'ok' === $geoins_robots['status'] ? 'info' : 'warning'; ?> inline"><p><?php echo esc_html( $geoins_robots['note'] ); ?></p></div>
			<?php endif; ?>

			<?php foreach ( $geoins_cat_order as $geoins_cat ) : ?>
				<?php
				if ( empty( $geoins_by_cat[ $geoins_cat ] ) ) {
					continue;
				}
				$geoins_risky = in_array( $geoins_cat, array( GEOINS_Bots::CAT_AGENT, GEOINS_Bots::CAT_RETRIEVAL, GEOINS_Bots::CAT_SEARCH ), true );
				?>
				<div class="geoins-botgroup <?php echo $geoins_risky ? 'is-risky' : 'is-safe'; ?>">
					<h3>
						<?php echo esc_html( $geoins_cats[ $geoins_cat ] ); ?>
						<span class="geoins-tag <?php echo $geoins_risky ? 'geoins-tag-warn' : 'geoins-tag-ok'; ?>">
							<?php echo $geoins_risky ? esc_html__( 'blocking costs citations', 'wille-geo-ai-visibility' ) : esc_html__( 'blocking is safe for citations', 'wille-geo-ai-visibility' ); ?>
						</span>
					</h3>
					<p class="geoins-benefit"><?php echo esc_html( $geoins_expl[ $geoins_cat ] ); ?></p>
					<div class="geoins-botgrid">
						<?php foreach ( $geoins_by_cat[ $geoins_cat ] as $geoins_slug => $geoins_bot ) : ?>
							<label class="geoins-botchip" title="<?php echo esc_attr( $geoins_bot['company'] ); ?>">
								<input type="checkbox" name="geoins_settings[blocked_bots][]" value="<?php echo esc_attr( $geoins_slug ); ?>" <?php checked( in_array( $geoins_slug, $geoins_blocked, true ) ); ?> />
								<span><?php echo esc_html( $geoins_bot['label'] ); ?> <small><?php echo esc_html( $geoins_bot['company'] ); ?></small></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
			<p>
				<button type="button" class="button" id="geoins-block-training"><?php esc_html_e( 'Block all training bots', 'wille-geo-ai-visibility' ); ?></button>
				<button type="button" class="button" id="geoins-unblock-all"><?php esc_html_e( 'Allow all bots', 'wille-geo-ai-visibility' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Checked = blocked via robots.txt. Google-Extended and Applebot-Extended are pure opt-out tokens: blocking them excludes you from Google/Apple AI training without affecting normal search.', 'wille-geo-ai-visibility' ); ?></p>
		</div>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'llms.txt', 'wille-geo-ai-visibility' ); ?></h2>
			<?php
			GEOINS_Admin::toggle_row(
				'llms_txt',
				__( 'Serve a generated /llms.txt', 'wille-geo-ai-visibility' ),
				__( 'Benefit: a machine-readable site summary for AI agents. Honest take: the big crawlers rarely fetch it yet – but coding/IDE agents do, it costs nothing and positions you as the standard grows.', 'wille-geo-ai-visibility' ),
				__( 'llms.txt is a young standard: a Markdown overview of your most important content at /llms.txt. It is generated automatically from your pages and posts and cached for 12 hours.', 'wille-geo-ai-visibility' )
			);
			?>
			<div class="geoins-field">
				<label for="geoins-llms-intro"><strong><?php esc_html_e( 'Short site description (optional)', 'wille-geo-ai-visibility' ); ?></strong></label>
				<textarea id="geoins-llms-intro" name="geoins_settings[llms_intro]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'One or two sentences: who you are and what your site covers.', 'wille-geo-ai-visibility' ); ?>"><?php echo esc_textarea( $geoins_settings['llms_intro'] ); ?></textarea>
			</div>
			<div class="geoins-field geoins-field-inline">
				<label><input type="checkbox" name="geoins_settings[llms_include_pages]" value="1" <?php checked( ! empty( $geoins_settings['llms_include_pages'] ) ); ?> /> <?php esc_html_e( 'Include pages', 'wille-geo-ai-visibility' ); ?></label>
				<label><input type="checkbox" name="geoins_settings[llms_include_posts]" value="1" <?php checked( ! empty( $geoins_settings['llms_include_posts'] ) ); ?> /> <?php esc_html_e( 'Include latest posts', 'wille-geo-ai-visibility' ); ?></label>
				<label><?php esc_html_e( 'Max. entries per section', 'wille-geo-ai-visibility' ); ?> <input type="number" min="1" max="500" name="geoins_settings[llms_max_items]" value="<?php echo esc_attr( (string) $geoins_settings['llms_max_items'] ); ?>" class="small-text" /></label>
			</div>
			<?php
			GEOINS_Admin::toggle_row(
				'llms_full',
				__( 'Serve /llms-full.txt with full content', 'wille-geo-ai-visibility' ),
				__( 'Benefit: agents that want more than the overview get your complete key content as clean Markdown in a single file.', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'md_endpoints',
				__( 'Markdown version of every page (.md URLs)', 'wille-geo-ai-visibility' ),
				__( 'Benefit: appending .md to any URL returns pure Markdown – no theme markup, no scripts. Cheaper for AI agents to read and advertised via a link tag on every page.', 'wille-geo-ai-visibility' ),
				__( 'Example: /my-post/ becomes /my-post.md. Requires pretty permalinks. The Markdown is generated from your content on the fly and marked noindex so it never competes with the HTML page in search.', 'wille-geo-ai-visibility' )
			);
			?>
			<?php if ( ! empty( $geoins_settings['llms_txt'] ) ) : ?>
				<p class="description">
					<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your llms.txt', 'wille-geo-ai-visibility' ); ?></a>
					<?php if ( ! empty( $geoins_settings['llms_full'] ) ) : ?>
						· <a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your llms-full.txt', 'wille-geo-ai-visibility' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'Content & freshness signals', 'wille-geo-ai-visibility' ); ?></h2>
			<?php
			GEOINS_Admin::toggle_row(
				'sitemap_lastmod',
				__( 'Add exact update times (lastmod) to the XML sitemap', 'wille-geo-ai-visibility' ),
				__( 'Benefit: search engines use reliable lastmod values to recrawl changed pages faster – updated content reaches AI answers sooner. WordPress core omits this field.', 'wille-geo-ai-visibility' ),
				__( 'Adds each post\'s real modification time to the WordPress core sitemap. Only active while the core sitemap runs; SEO plugins that replace the sitemap are unaffected.', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'show_modified',
				__( 'Show a visible "Updated on" date on revised posts', 'wille-geo-ai-visibility' ),
				__( 'Benefit: freshness is a ranking and citation signal – and the visible date is what search snippets and AI answers actually pick up.', 'wille-geo-ai-visibility' ),
				__( 'Shown above the content, only when the post was meaningfully revised (more than two days after publishing). The machine-readable dateModified is always in your structured data regardless of this switch.', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'toc',
				__( 'Automatic table of contents with heading anchors', 'wille-geo-ai-visibility' ),
				__( 'Benefit: every section gets a deep link (#section) that AI systems and search engines can cite precisely, and readers can jump to the answer.', 'wille-geo-ai-visibility' ),
				__( 'Adds anchor IDs to all H2/H3 headings and, on posts with three or more headings, inserts a compact linked table of contents before the first H2. Styling is minimal and inherits your theme fonts and colors.', 'wille-geo-ai-visibility' )
			);
			?>
		</div>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'Instant indexing (IndexNow)', 'wille-geo-ai-visibility' ); ?></h2>
			<?php
			GEOINS_Admin::toggle_row(
				'indexnow',
				__( 'Ping IndexNow on publish and update', 'wille-geo-ai-visibility' ),
				__( 'Benefit: Bing & co. fetch new and updated content within minutes instead of days. Bing feeds Microsoft Copilot and parts of ChatGPT search – faster indexing means faster AI citations.', 'wille-geo-ai-visibility' ),
				__( 'IndexNow is an open protocol supported by Bing, Seznam, Naver and others. On every publish, update or unpublish the plugin sends the public URL plus your site key to the IndexNow API – nothing else is transmitted. A key file is served automatically from your site for verification. If another plugin already submits to IndexNow (e.g. Rank Math or the official Bing plugin), leave this off to avoid duplicate pings.', 'wille-geo-ai-visibility' )
			);
			if ( ! empty( $geoins_settings['indexnow'] ) && get_option( 'geoins_indexnow_unreachable' ) ) :
				?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Your IndexNow key file is not reachable from your own server, so pings are paused. Usual causes: plain permalinks (fix under Settings → Permalinks) or a security plugin blocking loopback requests. After fixing it, save these settings again – the check re-runs shortly after saving.', 'wille-geo-ai-visibility' ); ?></p></div>
			<?php endif; ?>
			<?php
			if ( ! empty( $geoins_settings['indexnow'] ) ) :
				?>
				<p class="description">
					<?php echo esc_html( GEOINS_IndexNow::last_info() ); ?>
					<a href="<?php echo esc_url( GEOINS_IndexNow::key_location() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View your key file', 'wille-geo-ai-visibility' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'Structured data & meta tags', 'wille-geo-ai-visibility' ); ?></h2>
			<?php if ( $geoins_seo ) : ?>
				<div class="notice notice-info inline"><p>
					<?php
					/* translators: %s: SEO plugin name. */
					printf( esc_html__( '%s is active. To avoid duplicate output, Wille GEO automatically leaves schema and meta tags to it – the toggles below are ignored while it runs. Everything else (tracking, llms.txt, robots control, GEO checks) works alongside it.', 'wille-geo-ai-visibility' ), esc_html( $geoins_seo ) );
					?>
				</p></div>
			<?php endif; ?>
			<?php
			GEOINS_Admin::toggle_row(
				'schema',
				__( 'Output schema.org structured data (JSON-LD)', 'wille-geo-ai-visibility' ),
				__( 'Benefit: machine-readable article, author, date and site metadata – the cheapest way to help AI systems understand and correctly attribute your content.', 'wille-geo-ai-visibility' ),
				__( 'Outputs WebSite, Organization/Person, Article (with dates and author), and breadcrumbs. AI retrieval systems use these signals to judge freshness and trustworthiness.', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'faq_schema',
				__( 'Auto-generate FAQ schema from question headings', 'wille-geo-ai-visibility' ),
				__( 'Benefit: headings ending in “?” plus their answers become FAQ structured data – exactly the Q&A format AI assistants quote.', 'wille-geo-ai-visibility' ),
				__( 'Works on posts and pages with at least two question headings (H2/H3 ending in a question mark). No extra work needed – write questions as headings and the schema follows.', 'wille-geo-ai-visibility' )
			);
			GEOINS_Admin::toggle_row(
				'meta_tags',
				__( 'Output meta description & social previews (Open Graph)', 'wille-geo-ai-visibility' ),
				__( 'Benefit: clean snippets in search, chats and social apps – generated from your excerpt or content, zero maintenance.', 'wille-geo-ai-visibility' )
			);
			?>
			<div class="geoins-field geoins-field-inline">
				<label><strong><?php esc_html_e( 'Site represents', 'wille-geo-ai-visibility' ); ?></strong></label>
				<label><input type="radio" name="geoins_settings[schema_entity]" value="organization" <?php checked( 'organization' === $geoins_settings['schema_entity'] ); ?> /> <?php esc_html_e( 'an organization', 'wille-geo-ai-visibility' ); ?></label>
				<label><input type="radio" name="geoins_settings[schema_entity]" value="person" <?php checked( 'person' === $geoins_settings['schema_entity'] ); ?> /> <?php esc_html_e( 'a person', 'wille-geo-ai-visibility' ); ?></label>
				<input type="text" name="geoins_settings[schema_name]" value="<?php echo esc_attr( $geoins_settings['schema_name'] ); ?>" placeholder="<?php esc_attr_e( 'Name (defaults to site title)', 'wille-geo-ai-visibility' ); ?>" />
			</div>
			<div class="geoins-field">
				<label for="geoins-sameas"><strong><?php esc_html_e( 'Official profiles (sameAs)', 'wille-geo-ai-visibility' ); ?></strong></label>
				<textarea id="geoins-sameas" name="geoins_settings[schema_sameas]" rows="3" class="large-text" placeholder="https://www.linkedin.com/company/…&#10;https://github.com/…&#10;https://www.youtube.com/@…"><?php echo esc_textarea( $geoins_settings['schema_sameas'] ); ?></textarea>
				<p class="geoins-benefit"><?php esc_html_e( 'Benefit: one profile URL per line (LinkedIn, GitHub, YouTube, Wikipedia …). AI systems use these links to recognize you as one consistent, trustworthy entity across the web (E-E-A-T).', 'wille-geo-ai-visibility' ); ?></p>
			</div>
		</div>

		<div class="geoins-panel">
			<h2><?php esc_html_e( 'Data', 'wille-geo-ai-visibility' ); ?></h2>
			<?php
			GEOINS_Admin::toggle_row(
				'delete_on_uninstall',
				__( 'Delete all plugin data on uninstall', 'wille-geo-ai-visibility' ),
				__( 'Benefit: leaves your database exactly as it was. Off by default so your statistics survive a reinstall.', 'wille-geo-ai-visibility' )
			);
			?>
			<p class="description"><?php esc_html_e( 'Privacy: this plugin stores no IP addresses, sets no cookies, and makes no external requests by default. The only exceptions are two opt-in features that you control: bot verification (downloads public crawler IP lists once a day) and IndexNow (sends published URLs to the IndexNow API). All statistics stay in your WordPress database.', 'wille-geo-ai-visibility' ); ?></p>
		</div>

		<div class="geoins-savebar">
			<?php submit_button( __( 'Save settings', 'wille-geo-ai-visibility' ), 'primary', 'submit', false ); ?>
		</div>
	</form>
</div>
