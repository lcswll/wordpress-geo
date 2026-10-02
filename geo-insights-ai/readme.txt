=== GEO Insights – AI Search Visibility & Stats ===
Contributors: lcswll
Tags: geo, ai, seo, statistics, llms.txt
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See which AI (ChatGPT, Claude, Perplexity …) reads which page for which term – and optimize your site for AI search. Free, no subscription.

== Description ==

**GEO (Generative Engine Optimization) made simple.** AI assistants are becoming a major way people find websites. GEO Insights shows you what is actually happening – and gives you the state-of-the-art optimizations, each with a plain-language explanation of its direct benefit.

**100% free. No subscription, no premium version, no external services, no personal data.**

= AI statistics =

* **Which AI reads which page for which term** – every access by ChatGPT-User, GPTBot, OAI-SearchBot, ClaudeBot, Claude-User, PerplexityBot, Perplexity-User, Bingbot, Bytespider, Amazonbot, CCBot and more is logged and mapped to the focus term of the page.
* **The three bot classes explained**: Agent bots fetch your page live because a human just asked an AI about it. Retrieval bots build the index AI answers cite from. Training bots only feed models. The dashboard separates them, because they mean completely different things for your business.
* **Human visitors from AI answers**: people who click your link inside ChatGPT, Perplexity, Gemini, Copilot & co. – detected via referrer and via utm_source (ChatGPT tags its outbound links, which survives even stripped referrers).
* **Bot identity verification (opt-in)**: user agents can be faked. Enable verification and every hit is checked against the official IP ranges published by OpenAI, Anthropic, Perplexity, Google and Microsoft – impostors are flagged separately.
* **Counts AI visitors even behind full-page caches**: an opt-in, sub-1-kB beacon reports visits from AI answers that server-side tracking cannot see (no cookies, no IPs, no external service).
* **Weekly email report (opt-in)**: totals with trend, top bots, AI visitors, most-read pages and open issues – sent only when there was AI activity.
* **AI column in your content lists**: see AI accesses of the last 30 days next to every post and page.
* Modern React dashboard: interactive stacked timeline (tooltips, zoom brush, toggleable series), KPI cards with sparklines and change vs. the previous period, bot-class donut, top bots, term × AI matrix, landing pages, 7/30/90-day ranges, CSV export.
* Built to scale: raw hits are compressed into a daily aggregate table after 7 days, so the dashboard stays fast on busy sites.
* **WP-CLI**: `wp geoins stats`, `export`, `report`, `cleanup`, `refresh-ranges`, `selftest`.

= GEO optimizations – always with a benefit explanation =

* **AI crawler control (robots.txt)**: block or allow each AI bot individually, grouped by what blocking actually costs you. Blocking training bots is free of consequences; blocking retrieval/agent bots removes you from AI answers – the UI makes that impossible to miss.
* **llms.txt generator**: a machine-readable Markdown summary of your site at /llms.txt. Honest explanation included: big crawlers rarely fetch it yet, but it costs nothing and helps AI agents.
* **llms-full.txt + Markdown endpoints**: your complete key content as clean Markdown in one file, and a Markdown version of every page by appending .md to its URL (advertised via a link tag) – pure content, cheap for AI agents to read.
* **Live GEO checks while you write**: in the block editor, the 13-point checklist updates as you type, right in the sidebar.
* **Structured data (JSON-LD)**: WebSite, Organization/Person, Article with dates and author, breadcrumbs – plus automatic **FAQ schema from question headings** (H2/H3 ending in “?”).
* **Meta description & Open Graph** fallbacks – only when no other SEO plugin is active, so nothing is ever duplicated.
* **GEO check per post**: a 13-point checklist (direct answer first, question headings, lists/tables, concrete numbers, internal links, image alt text, freshness, author bio …) – every check explains why AI systems care.
* **Site status panel**: visibility, robots.txt analysis, permalinks, sitemap, llms.txt, IndexNow, schema – traffic-light simple.
* **Content & freshness signals**: exact lastmod times in the XML sitemap, an optional visible "Updated on" date, and an optional auto table of contents with citable heading anchors.
* **Author E-E-A-T**: job title + profile URLs per author feed the article schema; author archives ship ProfilePage structured data.
* **IndexNow (opt-in)**: new and updated content is pushed to Bing & co. within minutes. Bing feeds Copilot and parts of ChatGPT search – faster indexing means faster AI citations.
* **"How it works" page**: a plain-language explainer for non-technical users – what GEO is, how the plugin works, and an honest assessment of what results to expect.

= Plays nicely with your SEO plugin =

Yoast SEO, Rank Math, All in One SEO, SEOPress and The SEO Framework are detected automatically. GEO Insights then leaves schema and meta tags to them and only adds what they don't do: AI tracking, llms.txt, AI robots control and GEO content checks.

= Privacy =

* No IP addresses stored, no cookies set, no fingerprinting.
* No external requests by default. The only exceptions are two opt-in features: bot verification (downloads the public crawler IP lists from OpenAI, Anthropic, Perplexity, Google and Microsoft once a day; visitor IPs are compared in memory and never stored) and IndexNow (sends published URLs to the IndexNow API).
* Configurable data retention with automatic cleanup.
* Data is only removed on uninstall if you opt in.

= About the author =

GEO Insights is developed and maintained by Lucas Wille, a web developer from Magdeburg, Germany, who builds websites and AI automations: https://lucaswille.de/

Found a bug or have an idea? Open a topic in the support forum. And if the plugin helps you, a review on WordPress.org is the best way to say thanks – it helps other site owners find it.

== Installation ==

1. In your dashboard go to Plugins → Add New, search for "GEO Insights" and click Install, then Activate. (Or upload the ZIP under Plugins → Add New → Upload Plugin.)
2. Open GEO Insights → Settings. Tracking, llms.txt, Markdown endpoints and sitemap freshness work right away; everything that sends data anywhere (bot verification, IndexNow, email reports) stays off until you switch it on.
3. Optional: give your key posts a focus term in the "GEO check" panel of the editor, so the statistics can map AI accesses to topics.
4. AI Statistics fills up as soon as the first AI crawler visits – usually within a few days. GEO Audit scores your existing content immediately.

== Frequently Asked Questions ==

= Which term did an AI search for when it accessed my site? =

AI crawlers do not transmit search queries – no tool can see them. GEO Insights gives you the honest, practical equivalent: you assign a focus term to each post (or the title is used), and every AI access is mapped to that term. For human visitors from AI answers, the source (ChatGPT, Perplexity …) and landing page are recorded.

= Does this plugin slow down my site? =

No. Tracking is a single indexed database insert, and only for requests that match a known AI signature. By default there is no JavaScript on your public pages (the optional AI-visitor beacon is under 1 kB) and no external service.

= Do I need this if I already use an SEO plugin? =

Yes – classic SEO plugins optimize for Google's blue links. GEO Insights adds the AI layer: who reads your content, AI crawler control, llms.txt and AI-oriented content checks. Overlapping features are disabled automatically.

= Does it work with page caching? =

Bot tracking runs in PHP; requests served entirely from a full-page cache bypass it. AI crawlers frequently request uncached variants, so bot numbers stay a solid picture (treat them as a lower bound). For human visitors from AI answers there is a fix: enable the opt-in beacon and those visits are counted in the browser – cache or no cache.

= How many new customers will this bring me? =

An honest answer: visitors from AI answers are still a small share of most sites' traffic, but the share grows quickly, and these visitors convert unusually well – they already read an AI summary and clicked because they want more. The plugin removes every technical obstacle to being cited and shows you what works; the content quality that actually earns citations still has to come from you. The "How it works" page in the plugin gives you a realistic, hype-free assessment.

= Is the user-agent detection reliable? =

User agents can be spoofed. By default the plugin matches the official signatures of each AI company – a very good indicator. For proof, enable the opt-in identity verification: every hit from OpenAI, Anthropic and Perplexity crawlers is then checked against the official IP ranges those companies publish, and impostors are flagged separately in your statistics.

= Where is the source code of the dashboard? =

The dashboard script (assets/js/geoins-dashboard.js) is built with esbuild from the readable React source that ships with the plugin in src/dashboard/. Development repository with the build and test tooling: https://github.com/lcswll/wordpress-geo

= Is this GDPR compliant? =

The plugin is built privacy-first: it stores no personal data at all (no IPs, no cookies, no user agents of human visitors). Bot statistics are not personal data. As with any tool, the overall compliance of your site remains your responsibility.

== Third-party libraries ==

The statistics dashboard bundles the following open-source libraries into assets/js/geoins-dashboard.js (no CDN, no external requests; license headers are preserved in the bundle):

* React and ReactDOM – Copyright (c) Meta Platforms, Inc. and affiliates. Released under the MIT License.
* Apache ECharts – Copyright (c) The Apache Software Foundation. Released under the Apache License 2.0 (GPL-compatible).
* Simple Icons (selected brand icon paths) – released under CC0 1.0; all trademarks remain the property of their respective owners and are used for identification only.

The license headers of these libraries are kept in the bundle, and the readable source of the bundle ships with the plugin in src/dashboard/. Full license texts:

* MIT License: https://opensource.org/licenses/MIT
* Apache License 2.0: https://www.apache.org/licenses/LICENSE-2.0
* CC0 1.0: https://creativecommons.org/publicdomain/zero/1.0/

== Screenshots ==

1. AI statistics: citation alerts, filter by AI company, KPIs per bot class and accesses per day.
2. Term × AI matrix and flows: which AI reads which page for which term.
3. AI companies treemap and the crawl heatmap (weekday × hour).
4. GEO audit: every post and page scored against the 13 GEO checks, biggest opportunities first.
5. Live GEO checks in the block editor sidebar.
6. Settings – every option explains its direct benefit.
7. AI crawler control (robots.txt), grouped by what blocking actually costs you.

== Changelog ==

= 2.2.0 =
* New: redesigned admin screens – a brand bar with the radar logo and navigation between Statistics, Audit, Settings and the explainer on every page, plus a consistent indigo accent for buttons, toggles and filters.
* New: author credit and links – the plugin list shows "How it works" and a rating link; the footer of the plugin's pages credits the author.
* New: a friendly, one-time review request on the plugin's own pages – only after two weeks of use and once AI accesses are actually being recorded. "Maybe later" hides it for 30 days, "I already did" for good. Never shown elsewhere in the admin.

= 2.1.0 =
* Changed: requires WordPress 6.5 or newer (the bundled German translation now uses the fast .l10n.php format; wordpress.org language packs take precedence automatically).
* Improved: all statistics queries use prepared identifiers for table and column names, with an allow-list for groupable columns.
* Improved: the GEO check meta box (classic editor) builds its result list with DOM methods instead of HTML strings.
* Fixed: the table of contents could empty a post if the heading scan failed on very large content – the content is now left untouched instead.
* Fixed: rarely hit error paths (CSV export without a writable output stream, missing page lists, failed regex runs on huge content) are handled gracefully.
* Development: automated test suite (unit tests, integration tests in a real WordPress on PHP 7.4 and 8.4, browser tests) and the readable dashboard source in src/dashboard/.

= 2.0.0 =
* New: visualization engine switched to Apache ECharts 6 – smoother canvas rendering, richer tooltips, a zoom slider on long ranges, legend toggling built in.
* New: AI companies treemap (share of accesses, click a company to zoom into its bots).
* New: flow diagram (Sankey) – which AI bot reads which page, band width = accesses.
* New: crawl heatmap – bot accesses by weekday and hour in your site's timezone, so you can publish before the busy hours.
* New: site GEO score gauge on the audit page, next to the score distribution and the opportunity map.
* Improved: donuts with rounded segments and centered totals, moving-average trend for AI visitors, animated gauge/histogram; all charts honor prefers-reduced-motion.
* Verified end-to-end on a real WordPress (Playground, PHP 8.2) – zero PHP warnings across all screens.


= 1.9.0 =
* New: audit charts – an opportunity map (every AI-read page plotted by AI accesses vs. GEO score, click a dot to edit) and a score distribution histogram.
* New: dashboard charts – a bot-identity donut (verified / impostors / unchecked) when verification is on, and an AI-visitor trend line with 7-day moving average.
* New: the built-in self-test can be run from a live site via the admin-only REST route geoins/v1/selftest (44 assertions, shared with wp geoins selftest).
* Verified end-to-end on a real WordPress instance (WordPress Playground, PHP 8.2): bot and referral tracking, beacon, AI radar, alerts, llms.txt/.md endpoints with 304 revalidation, robots.txt analysis, structured data incl. author E-E-A-T, sitemap lastmod, dashboard/audit/settings screens – zero PHP warnings.

= 1.8.0 =
* New: citation alerts – the dashboard (and an opt-in daily-bundled email) tells you the moment a new AI crawls your site for the first time, the first human visitors arrive from an AI assistant, or AI visitors spike. Existing sources are seeded silently on upgrade, so nothing floods.
* New: AI radar – AI-sounding crawlers that are NOT in the registry yet are collected with their user agent and counts, so new AI systems surface instead of staying invisible. Dismiss known noise with one click; add real ones via the geoins_bots filter.
* New: site-wide GEO Audit page – every published post and page scored against the 13 checks in a sortable table (worst first), with the failed checks and the AI accesses of the last 30 days side by side. Scores are cached per post and refresh automatically when content changes.

= 1.7.0 =
* Improved: modernized admin UI – design tokens, elevated KPI cards with gradient accent bars and hover lift, a segmented pill toolbar, gradient stat bars, refined tables and status cards, smoother animated charts (respecting prefers-reduced-motion), polished toggles and a sticky save bar on the settings page. Pure visual refresh, no behavior changes.

= 1.6.0 =
* New: exact update times (lastmod) in the core XML sitemap – WordPress omits them; reliable lastmod makes engines recrawl changed pages faster, so updates reach AI answers sooner. On by default.
* New: visible "Updated on" date on meaningfully revised posts (opt-in) – freshness is a ranking and citation signal, and the visible date is what snippets and AI answers pick up.
* New: automatic table of contents with anchor IDs on H2/H3 headings (opt-in) – every section becomes precisely citable via deep link.
* New: author E-E-A-T signals – job title and profile URLs (sameAs) on the user profile flow into the article schema, and author archives get Google's documented ProfilePage structured data.
* New: two more GEO checks – at least one internal link, and alt text on all images (13 checks total, live in the editor as always).
* New: .md endpoints answer conditional requests (Last-Modified / 304) – cheaper recrawls for AI agents.
* New: status check for pretty permalinks (required by the .md endpoints and the IndexNow key file).

= 1.5.1 =
* Improved: the robots.txt status check now reads and analyzes a physical robots.txt instead of blanket-warning about it. A file that blocks no citation-relevant AI bots and matches your configured blocks is reported as fine (green); the check warns specifically when the file blocks bots that put you into AI answers, when it blocks ALL crawlers, or when your configured blocks are missing from it. The settings page notice is analysis-aware too.
* New: wp geoins selftest covers the robots.txt analyzer (12 new assertions), including BOM handling, wildcard syntax (Disallow: /*), version-suffixed tokens (GPTBot/1.0) and exception groups.
* Improved: the analyzer resolves the real web-root robots.txt on "WordPress in its own directory"/Bedrock layouts and refuses to judge files larger than the 500 KiB crawler processing limit.

= 1.5.0 =
* New: filter the entire dashboard by AI company – one click on OpenAI, Anthropic, Perplexity, Google, Microsoft & co. filters totals, timeline, bots, visitors, the term matrix, landing pages and the CSV export. Chips appear for the AIs active in the selected range.
* New: brand icons everywhere – top bots, AI visitor sources, the term matrix and the filter chips show each AI's mark (Simple Icons, CC0 collection). Brands that do not permit icon redistribution (OpenAI, Microsoft, Amazon …) get clean monogram badges in their brand colors.

= 1.4.0 =
* New: the statistics dashboard is now a modern React app – interactive stacked timeline with tooltips, zoom brush (30/90 days) and clickable legend to toggle series, sparklines in the KPI cards, a donut of bot classes, horizontal bar charts for top bots and AI referral sources, loading skeletons and a friendly first-run screen.
* New: REST API endpoint (geoins/v1/dashboard, admin-only) replaces the admin-ajax data endpoint.
* Note: charts are rendered with Recharts (MIT), bundled locally – no CDN, no external requests. Build source: src/dashboard/ in the development repository (see plugin homepage).

= 1.3.0 =
* New: weekly email report (opt-in) – totals with trend, top bots, AI visitors, most-read pages and open issues; sent only when there was AI activity.
* New: IndexNow integration (opt-in) – new and updated content is pushed to Bing & co. within minutes; key file is generated and served automatically.
* New: cache-proof AI visitor counting (opt-in) – a sub-1-kB browser beacon counts visitors from AI answers even when pages are served from a full-page cache.
* New: "How it works" page – a plain-language explainer of GEO, the plugin, and realistic expectations, written for non-technical users.
* New: WP-CLI commands – wp geoins stats / export / report / cleanup / refresh-ranges / selftest.
* New: "AI (30d)" column on the posts and pages lists shows AI accesses per item.
* New: bot identity verification now also covers Google (GoogleOther) and Microsoft (Bingbot) via their official IP lists.
* New: 8 more AI crawlers recognized – Applebot, YouBot, PetalBot, AI2Bot, Cohere, Diffbot, Timpibot, Omgilibot (26 total).
* New: article schema now includes keywords (focus term + tags).
* New: AI bot fetches of .md pages, llms.txt and llms-full.txt now appear in the statistics (previously these endpoint hits were invisible).
* Improved: referral dedupe is now per-visitor (salted, expiring hash – nothing stored), so several people arriving from the same AI answer within a minute are all counted.
* Improved: visits through a canonical redirect are no longer counted twice under two paths.
* Improved: statistics windows now partition rolled-up days cleanly – totals, trend arrows and the daily chart are consistent.
* Fixed: .md endpoints and dashboard links now work correctly on subdirectory installs.
* Fixed: a settings save while verification was off no longer wipes the proxy/CDN header choice.
* Fixed: network-wide deactivation now clears the cron events on every subsite; uninstall clears them too.
* Fixed: uninstall now also removes llms.txt pins and all new options.

= 1.2.0 =
* Fixed: bot identity verification now works behind Cloudflare and other proxies/CDNs – declare your setup in the settings and the original visitor IP is used. Without this, every real bot behind a CDN looked like an impostor.
* Fixed: word counts are now Unicode-aware (umlauts and accents no longer split words) – affects the GEO checks and the wordCount in structured data.
* Improved: the live editor checks are debounced, keeping the editor smooth on long posts.
* New: feature content in llms.txt – a checkbox on the edit screen pins your most important pages into a "Key content" section, listed first in llms.txt and llms-full.txt.
* New: sameAs profiles in structured data (one URL per line) – AI systems recognize you as one consistent entity (E-E-A-T).
* New: quick actions "Block all training bots" / "Allow all bots" in the crawler settings.

= 1.1.0 =
* New: opt-in bot identity verification against the official IP ranges of OpenAI, Anthropic and Perplexity – impostors are flagged.
* New: live GEO checks in the block editor sidebar (updates while you type).
* New: llms-full.txt and Markdown endpoints (.md URL for every page, advertised via link tag).
* New: CSV export and change vs. previous period in the dashboard.
* New: welcome pointer after activation.
* Improved: daily aggregate table keeps the dashboard fast on busy sites; term matrix now runs on a single query.
* Improved: referral double-count protection (60-second window).
* Fixed: full multisite support (network activation, new subsites, uninstall).

= 1.0.0 =
* Initial release: AI bot tracking, AI referral tracking, statistics dashboard, robots.txt AI control, llms.txt generator, JSON-LD schema with auto-FAQ, meta tags, per-post GEO checks, EN/DE translations.

== Upgrade Notice ==

= 2.2.0 =
Redesigned admin screens with navigation between all GEO Insights pages.

= 2.1.0 =
Requires WordPress 6.5+. Hardened database queries and translation loading; no settings change needed.

= 1.4.0 =
The statistics dashboard is now a modern interactive React app (Recharts, bundled locally – no CDN), fed by a new admin-only REST endpoint.

= 1.3.0 =
Weekly email report, IndexNow instant indexing, cache-proof AI visitor counting, WP-CLI, plain-language explainer page, Google/Microsoft bot verification, 8 new crawlers.

= 1.2.0 =
Important fix for bot verification behind CDNs/proxies, Unicode word counts, llms.txt key content, sameAs profiles.
