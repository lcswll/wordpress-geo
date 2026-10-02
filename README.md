# GEO Insights – AI Search Visibility & Stats

WordPress-Plugin: Sehen, welche AI (ChatGPT, Claude, Perplexity …) welche Seite zu welchem Begriff liest – plus alle State-of-the-Art-GEO-Optimierungen, jede Option mit direkter Nutzen-Erklärung. Kostenlos, kein Abo, keine externen Dienste, keine personenbezogenen Daten.

## Struktur

```
geo-insights-ai/                     Version 2.0.0
├── geo-insights-ai.php              Bootstrap + Plugin-Header + WP-CLI-Registrierung
├── readme.txt                       WordPress.org-Readme
├── uninstall.php                    Datenlöschung (nur bei Opt-in, multisite-fähig)
├── includes/
│   ├── class-geoins-plugin.php      Orchestrator, SEO-Plugin-Erkennung, Status-Checks
│   ├── class-geoins-install.php     2 DB-Tabellen (Raw + Tages-Aggregat), Multisite, Cron
│   ├── class-geoins-bots.php        Bot-Registry (26 AI-Crawler) + Referral-Quellen (9 AIs)
│   ├── class-geoins-stats.php       Geteilte Query-Schicht (Dashboard/Report/CLI)
│   ├── class-geoins-tracker.php     Bot- & Referral-Logging, 60s-Dedupe, Verify-Flag
│   ├── class-geoins-beacon.php      Cache-fester Referral-Beacon (REST, opt-in)
│   ├── class-geoins-verify.php      Opt-in IP-Verifikation (OpenAI/Anthropic/Perplexity/Google/Microsoft)
│   ├── class-geoins-llms-txt.php    /llms.txt-Generator (12h-Cache)
│   ├── class-geoins-markdown.php    HTML→Markdown, /llms-full.txt, .md-Endpunkte
│   ├── class-geoins-robots.php      robots.txt-Regeln pro Bot
│   ├── class-geoins-schema.php      JSON-LD inkl. Auto-FAQ + keywords
│   ├── class-geoins-meta.php        Meta-Description + Open Graph (Fallback)
│   ├── class-geoins-analysis.php    13 GEO-Checks (Definitionen geteilt mit Editor-Panel)
│   ├── class-geoins-content.php     Sitemap-lastmod, „Aktualisiert am“, Auto-TOC + Anker
│   ├── class-geoins-alerts.php      Zitat-Alarme (neue Quelle, Referral-Spike; opt-in Mail)
│   ├── class-geoins-audit.php       Site-weiter GEO-Audit (Score-Cache in Post-Meta, Batches)
│   ├── class-geoins-indexnow.php    IndexNow-Pings + Key-Datei (opt-in)
│   ├── class-geoins-report.php      E-Mail-Wochenreport (opt-in)
│   ├── class-geoins-rest.php        REST: geoins/v1/dashboard (React-App, admin-only)
│   ├── class-geoins-cli.php         wp geoins stats/export/report/cleanup/refresh-ranges/selftest
│   └── admin/                       Menü, Einstellungen, CSV-Export,
│       │                            AI-Spalte (Beitragsliste), Views (inkl. learn.php-Erklärseite)
├── assets/
│   ├── css/geoins-admin.css
│   └── js/geoins-dashboard.js       React-Dashboard (gebaut, React 19 + Apache ECharts 6 gebündelt)
│       ├── geoins-admin.js          Settings-Aktionen + Classic-Editor-Metabox
│       ├── geoins-editor.js         Gutenberg-Sidebar: Live-GEO-Checks beim Schreiben
│       └── geoins-beacon.js         Referral-Beacon fürs Frontend (<1 kB)
└── languages/                       POT + de_DE (355 Strings übersetzt)
```

**Dashboard-Frontend (React):** Quellcode in `src/dashboard/` (Repo-Root, nicht im Plugin-ZIP).
Build: `npm install` einmalig, dann `npm run build` (esbuild → `assets/js/geoins-dashboard.js`,
selbst-enthaltenes IIFE-Bundle, kein CDN) oder `npm run watch` während der Entwicklung.
i18n-Build: `python tools-build_i18n.py` (Wörterbuch: `tools-i18n-de.json`; alle
Dashboard-Strings laufen über `wp_localize_script`, werden also aus dem PHP extrahiert).

## Kernkonzepte

**Drei Bot-Klassen** (das zentrale UI-Konzept, überall erklärt):

| Klasse | Bots (Beispiele) | Bedeutung | Blockieren? |
|---|---|---|---|
| Agent | ChatGPT-User, Perplexity-User, Claude-User | Mensch fragt gerade live | Kostet dich Leser |
| Retrieval | OAI-SearchBot, PerplexityBot, Claude-SearchBot | Baut den Zitier-Index | Kostet dich Zitate |
| Training | GPTBot, ClaudeBot, CCBot, Bytespider … | Füttert nur Modelle | Kostet nichts |

**„Welche AI bei welchem Begriff":** AI-Crawler senden keine Suchbegriffe (kann kein Tool sehen). Lösung: Fokus-Begriff pro Beitrag (Metabox, Fallback = Titel) × Bot-Zugriffe = Begriff-Matrix im Dashboard. Zusätzlich echte Besucher aus AI-Antworten via Referrer + `utm_source=chatgpt.com`-Erkennung.

**Datenschutz:** Pro Zugriff nur Bot/Quelle, Kategorie, Seite, Zeitpunkt, Verifikations-Status. Keine IPs gespeichert, keine Cookies. Externe Requests nur bei aktivierter IP-Verifikation (öffentliche IP-Listen, 1×/Tag). Aufbewahrung konfigurierbar; nach 7 Tagen werden Rohdaten in eine Tages-Aggregat-Tabelle komprimiert (Dashboard bleibt auch bei viel Traffic schnell).

**Bot-Verifikation (opt-in):** User-Agents sind fälschbar. Das Plugin lädt täglich die offiziellen Crawler-IP-Listen von OpenAI, Anthropic und Perplexity (formatunabhängiger CIDR-Extraktor, übersteht Formatänderungen) und markiert jeden Zugriff als verifiziert / Imitation / ungeprüft. Die Besucher-IP wird nur im RAM verglichen.

**SEO-Plugin-Koexistenz:** Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework werden erkannt → Schema/Meta automatisch abgeschaltet, Rest läuft parallel.

## Lokal testen

Am einfachsten mit [wp-env](https://developer.wordpress.org/block-editor/getting-started/devenv/get-started-with-wp-env/) (braucht Docker) oder [Local](https://localwp.com/):

```bash
# Variante wp-env: im Ordner geo-insights-ai/
npm -g install @wordpress/env
wp-env start
# → Plugin unter http://localhost:8888/wp-admin aktivieren
```

Bot-Zugriff simulieren:

```bash
curl -A "Mozilla/5.0 (compatible; GPTBot/1.4; +https://openai.com/gptbot)" http://localhost:8888/
curl -A "compatible; ChatGPT-User/1.0; +https://openai.com/bot" http://localhost:8888/beispiel-seite/
# AI-Referral simulieren:
curl -e "https://chatgpt.com/" http://localhost:8888/
curl "http://localhost:8888/?utm_source=chatgpt.com"
```

Danach: **GEO Insights → AI Statistics** im Admin. Außerdem prüfen: `/llms.txt`, `/llms-full.txt`, `/robots.txt` und eine Beitrags-URL mit angehängtem `.md` (liefert reines Markdown). Im Block-Editor erscheint das Panel „GEO check" mit Live-Checks beim Tippen.

## Veröffentlichung auf WordPress.org – Checkliste

1. **Konto**: wordpress.org-Konto mit aktivierter **2FA** (Pflicht seit Ende 2024).
2. **Plugin Check**: Plugin [„Plugin Check (PCP)"](https://wordpress.org/plugins/plugin-check/) lokal installieren und über `geo-insights-ai` laufen lassen – derselbe Scanner läuft bei der Einreichung automatisch.
3. **Name/Slug prüfen**: Vor Einreichung auf wordpress.org/plugins nach „GEO Insights" suchen; bei Kollision Name in `geo-insights-ai.php` + `readme.txt` anpassen (Text-Domain müsste dann mitgezogen werden).
4. **readme.txt**: `Contributors:` auf deinen wordpress.org-Nutzernamen setzen (aktuell `lcswll02`), `Tested up to:` auf die dann aktuelle WP-Version.
5. **Screenshots** (optional, empfohlen): `screenshot-1.png` … `screenshot-5.png` gemäß den Beschreibungen in readme.txt; kommen später ins SVN-`assets/`-Verzeichnis.
6. **Einreichen**: ZIP hochladen unter https://wordpress.org/plugins/developers/add/ – Review dauert typisch einige Tage bis Wochen.
7. **Nach Freigabe**: Code ins zugeteilte SVN-Repo (`svn co https://plugins.svn.wordpress.org/DEIN-SLUG`), nach `trunk/` committen, Release über `tags/1.0.0` taggen.
8. **Übersetzungen**: Nach Freigabe übernimmt translate.wordpress.org (GlotPress) – die mitgelieferte de_DE dient bis dahin als sofort funktionierende Übersetzung.

## Versionshistorie

**2.0.0**: Chart-Engine auf **Apache ECharts 6.1** umgestellt (Recharts entfernt; `src/dashboard/echart.jsx` = schlanker React-Wrapper mit ResizeObserver + reduced-motion; tree-shaked via `echarts/core`). Neue Visualisierungen: Treemap (Anbieter → Bots), Sankey (Bot → Seite), Crawl-Heatmap (Wochentag × Stunde, Site-Zeitzone; Daten aus `GEOINS_Stats::heatmap()`, in SQL pro UTC-Stunde gruppiert), Score-Gauge. Timeline mit dataZoom-Slider, Donuts mit Rundungen, Referral-Trend als Linie+Fläche. Auf echtem WordPress (Playground) verifiziert.


**1.9.0**: Audit-Charts (Opportunity-Scatter AI-Zugriffe × Score mit Klick-zu-Bearbeiten, Score-Histogramm; Summary-Endpunkt `audit/summary` mit einer gruppierten Postmeta-Query), Dashboard-Charts (Verifikations-Donut, AI-Besucher-Trend mit 7-Tage-Schnitt), Selftest als `GEOINS_Selftest::run()` geteilt zwischen WP-CLI und REST `geoins/v1/selftest` (44 Assertions). **Erstmals End-to-End auf echtem WordPress verifiziert** (WordPress Playground CLI, PHP 8.2, `tools-playground-blueprint.json`): alle Endpunkte, Tracking-Pfade, Schema-Ausgaben und Admin-Screens ohne PHP-Warnings. Recharts-3-Fund: Scatter-`onClick` liefert das Point-Item, das Datum steckt in `.payload`.

**1.8.0** (Top 3 der 50er-Liste): **Zitat-Alarme** (Erst-Kontakt neuer Bots/Referral-Quellen + Referral-Spike-Check im Tages-Cron; Dashboard-Strip + opt-in gebündelte Mail max. 1/Tag; atomarer add_option-Claim gegen Burst-Duplikate, eager Seeding via DB-Version-Bump auf 4, Referral-Promotion erst ab 3 Hits gegen utm-Spoofing), **AI-Radar** (unbekannte AI-artige UAs gesammelt, 50er-Cap, 60s/UA-Throttle + 30/min-Site-Budget gegen Floods, Dismiss per REST), **GEO-Audit-Seite** (Score-Cache in Post-Meta mit Version=Checks.Generation, Force-Re-scan bumpt Generation, Batch-Scan mit try/catch+15s-Budget, zwei getrennte meta_queries statt OR/!=-Falle, ID-Tiebreaker für stabile Pagination, AI-Zugriffe-Join). Merged_group-Lower-Bound `-1s` für Mitternachts-Fenster (Spike-Baseline /7 korrekt).

**1.7.0**: Modernes Admin-UI: Design-Tokens auf `.geoins-wrap`, KPI-Karten mit Gradient-Akzentbalken (color-mix mit Split-Property-Fallback) und Hover-Lift, Segmented-Pill-Toolbar, Gradient-Statbalken (Custom-Property statt Inline-Background), verfeinerte Tabellen/Status-Karten mit farbigen Halos, animierte Charts (Recharts-'auto' respektiert prefers-reduced-motion; bei sichtbarem Brush deaktiviert), polierte Toggles + Sticky-Save-Leiste (pointer-events-transparent). Rein visuell.

**1.6.0** („bewiesene Maßnahmen“): lastmod in der Core-Sitemap (an), sichtbares „Aktualisiert am" (opt-in, the_content-Prio 12 nach do_blocks, Excerpt/oEmbed-Guard), Auto-TOC + Anker-IDs (opt-in, kollisionssicher gegen manuelle Anker), Autor-E-E-A-T (jobTitle/sameAs-Profilfelder → Article-Schema; ProfilePage auf Autorenarchiven — beides nur für User mit publish_posts/Beiträgen, sonst Subscriber-Spam-Vektor), 2 neue GEO-Checks (interne Links, Alt-Texte; PHP↔JS-Parität inkl. IDN-Punycode, Kommentar-Strip, synced-Pattern-Expansion im Editor), Last-Modified/304 auf .md (Header nach nocache_headers() senden — Core entfernt ihn sonst), Status-Check Permalinks.

**1.5.1**: robots.txt-Analyse statt Pauschal-Warnung: Eine physische robots.txt wird gelesen und geparst (REP-pragmatisch: nur Voll-Blocks, Wildcard-Vererbung, BOM-Strip, `/*`-Syntax, versionierte Tokens wie `GPTBot/1.0`, Ausnahme-Gruppen, Bedrock/„own directory"-Webroot, 500-KiB-Limit). Status differenziert: grün wenn harmlos, rot bei Komplett-Block, gezielte Warnungen mit Bot-Namen bei blockierten Zitat-Quellen oder fehlenden konfigurierten Blocks. 12 neue Selftest-Assertions.

**1.5.0**: Per-KI-Filter im Dashboard (Company-Chips, serverseitig über alle Panels + CSV-Export; REST-Param `sources`, validiert gegen die Registries in kanonischer Speicherform) und Marken-Icons überall (Simple Icons CC0, tree-shaked ins Bundle; Monogramm-Badges in Markenfarben für OpenAI/Microsoft/Amazon & Co., deren Icons aus dem CC0-Set entfernt wurden). Chips speisen sich aus einem ungefilterten Snapshot (`botSources`, ungecappt) und sortieren alphabetisch-stabil; gefilterte CSV-Exporte tragen den Filter im Dateinamen.

**1.4.0**: React-Dashboard (React 19 + Recharts 3, per esbuild gebündelt – kein CDN): interaktive gestapelte Timeline mit Tooltips, Zoom-Brush (30/90 Tage) und klickbarer Legende, Sparklines in den KPI-Karten, Bot-Klassen-Donut, horizontale Balkencharts, Loading-Skeletons, Empty-State mit Onboarding. Neuer REST-Endpunkt `geoins/v1/dashboard` (admin-only, ersetzt admin-ajax). Build-Toolchain im Repo-Root (`npm run build`).

**1.3.0**: E-Mail-Wochenreport (opt-in, nur bei Aktivität), IndexNow-Integration (opt-in, Key-Datei automatisch), Cache-fester Referral-Beacon (opt-in, <1 kB, REST-Endpunkt, Session-Guard + 60s-Dedupe), Erklärseite „So funktioniert's" für Non-Techies (inkl. ehrlicher Erwartungs-Einordnung), WP-CLI (`stats/export/report/cleanup/refresh-ranges/selftest`), Verifikation auch für Google (GoogleOther) & Microsoft (Bingbot), 8 neue Bots (Applebot, YouBot, PetalBot, AI2Bot, Cohere, Diffbot, Timpibot, Omgilibot), „AI (30d)"-Spalte in Beitrags-/Seitenlisten, Artikel-Schema mit keywords, Subdirectory-Fixes (.md-URLs, Dashboard-Links), Uninstall räumt Pins + neue Optionen auf.

**1.2.0**: Proxy/CDN-Support für die IP-Verifikation (Cloudflare/XFF-Header wählbar – wichtig, sonst gelten hinter CDNs alle echten Bots als Imitationen), Unicode-Wortzählung (Umlaute!), Editor-Debounce, llms.txt-Pins („Kern-Inhalte" zuerst), sameAs-Profile im Schema, Schnellaktionen für Bot-Blocking.

**1.1.0**: IP-Verifikation (opt-in), Tages-Aggregat + Single-Query-Matrix statt N+1, Multisite-Support, Referral-Dedupe (60 s), Gutenberg-Live-Checks, CSV-Export, Vorzeitraum-Vergleich, Onboarding-Notice, llms-full.txt, .md-Endpunkte mit rel=alternate.

## Roadmap-Ideen (v1.4+)

- Sortierbare „AI (30d)"-Spalte (braucht denormalisierten Zähler oder Meta-Cache)
- Digest-Vergleich: „Deine Seite vs. zitierte Konkurrenz" für einen Fokus-Begriff
- Verifikation auf weitere Anbieter ausweiten, sobald sie IP-Listen veröffentlichen (Meta, Amazon, ByteDance fehlen noch)
- llms.txt: Custom Post Types optional aufnehmen
- Wochenreport optional als CSV-Anhang

## Entwicklung

- Prefix: `geoins_` / `GEOINS_`, Text-Domain: `geo-insights-ai`
- Neue Bots: Filter `geoins_bots`, neue Referral-Quellen: Filter `geoins_referral_sources`, Verify-Quellen: Filter `geoins_verify_sources`
- Nach String-Änderungen: neue Übersetzungen in `tools-i18n-de.json` ergänzen, dann `python tools-build_i18n.py` (extrahiert selbst, bricht bei fehlenden Übersetzungen ab)
- Syntax-Check ohne PHP-Binary: `npm i php-parser && node tools-phpcheck.js geo-insights-ai`
- Laufzeit-Smoke-Tests: `wp geoins selftest` (25+ Assertions über die puren Funktionen)
