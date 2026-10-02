# GEO Insights – AI Search Visibility & Stats

WordPress-Plugin: Sehen, welche AI (ChatGPT, Claude, Perplexity …) welche Seite zu welchem Begriff liest – plus alle State-of-the-Art-GEO-Optimierungen, jede Option mit direkter Nutzen-Erklärung. Kostenlos, kein Abo, keine externen Dienste, keine personenbezogenen Daten.

## Struktur

```
geo-insights-ai/                     Version 2.2.0
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
├── src/dashboard/                   Quellcode des React-Dashboards (wird mitgeliefert, Build: esbuild)
├── assets/
│   ├── css/geoins-admin.css
│   └── js/geoins-dashboard.js       React-Dashboard (gebaut, React 19 + Apache ECharts 6 gebündelt)
│       ├── geoins-admin.js          Settings-Aktionen + Classic-Editor-Metabox
│       ├── geoins-editor.js         Gutenberg-Sidebar: Live-GEO-Checks beim Schreiben
│       └── geoins-beacon.js         Referral-Beacon fürs Frontend (<1 kB)
└── languages/                       POT + de_DE als .l10n.php/.po (generiert aus i18n/de_DE.json)
```

**Dashboard-Frontend (React):** Quellcode in `geo-insights-ai/src/dashboard/` – liegt bewusst im Plugin, damit
wordpress.org-Reviewer das minifizierte Bundle nachvollziehen können. Build: `npm run build:dashboard` (esbuild →
`assets/js/geoins-dashboard.js`, selbst-enthaltenes IIFE-Bundle, kein CDN) oder `npm run watch`. Die CI baut das Bundle
neu und schlägt fehl, wenn es nicht zum Quellcode passt.

Alles außerhalb von `geo-insights-ai/` ist Entwicklungswerkzeug und wird nie mit ausgeliefert. Verzeichnis-Grafiken
(Icon, Banner, Screenshots) liegen in [`.wordpress-org/`](.wordpress-org/) und landen im SVN unter `/assets`.

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

## Lokale Entwicklung

Voraussetzung: Node ≥ 24 (unter Node 22 stürzt das php-wasm von Playground mit PHP 7.4 ab). PHP muss nicht installiert sein.

```bash
npm ci
```

```bash
npm run setup:php
```

`setup:php` lädt unter Windows ein portables PHP 7.4 + Composer nach `.cache/` (Prüfsummen werden verifiziert, nichts wird systemweit installiert). PHP 8.4 lässt sich mit `npm run setup:php -- --php 8.4` ergänzen.

| Befehl | Was |
| --- | --- |
| `npm run verify` | alles, was die CI prüft (inkl. WordPress-Laufzeit- und Browsertests) |
| `npm run verify -- --fast` | statische Prüfungen + Unit-Tests (≈ 30 s, auch als pre-push-Hook) |
| `npm run playground` | WordPress mit Plugin, Beispielinhalten und 90 Tagen AI-Traffic auf http://127.0.0.1:9400 |
| `npm run test:e2e -- --php 7.4 --wp 6.5` | Laufzeittests gegen eine bestimmte PHP-/WP-Version |
| `npm run phpcs` / `npm run phpcbf` | Coding Standards prüfen / automatisch korrigieren |
| `npm run build:dashboard` / `npm run watch` | React-Dashboard bauen |
| `npm run i18n` | Übersetzungen aus `i18n/de_DE.json` neu erzeugen (`-- --prune` entfernt verwaiste Einträge) |
| `npm run build` | Release-ZIP nach `dist/` |
| `node scripts/wporg-assets.mjs` | Icon (animiertes GIF, braucht ffmpeg), Banner und Screenshots für wordpress.org neu erzeugen |

Pre-push-Hook einmalig aktivieren:

```bash
git config core.hooksPath .githooks
```

Bot-Zugriffe gegen den laufenden Playground simulieren. Das Cookie beim zweiten Befehl verhindert Playgrounds Auto-Login – sonst zählt der Besuch als eingeloggter Admin und wird (richtigerweise) nicht als AI-Besucher gewertet:

```bash
curl -A "Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)" http://127.0.0.1:9400/wordpress-backup-guide/
```

```bash
curl -b "playground_auto_login_already_happened=1" -e "https://chatgpt.com/" http://127.0.0.1:9400/wordpress-backup-guide/
```

Erweitern: Prefix `geoins_` / `GEOINS_`, Text-Domain `geo-insights-ai`. Neue Bots über den Filter `geoins_bots`, Referral-Quellen über `geoins_referral_sources`, Verify-Quellen über `geoins_verify_sources`. Neue Strings brauchen eine Übersetzung in `i18n/de_DE.json` – die CI meldet fehlende.

## Tests

| Ebene | Wo | Was |
| --- | --- | --- |
| Unit (PHPUnit + Brain Monkey) | `tests/unit/` | Bot-/Referral-Erkennung inkl. Registry-Integrität, IP-Verifikation (CIDR v4/v6), robots.txt-Analyse und -Ausgabe, Markdown, TOC/Anker, FAQ-Schema, GEO-Checks, eingebauter Selbsttest |
| Integration (echtes WordPress) | `tests/e2e/selftest.php` | Aktivierung, Tracking von Bot- und Referral-Zugriffen, Dedupe, Rollup, robots.txt, llms.txt, .md, JSON-LD, GEO-Checks, REST-Rechte (anonym/Abonnent/Admin), Beacon, Deaktivierung, Deinstallation |
| Plugin Check | `tests/e2e/plugin-check.php`, `scripts/plugin-check.mjs` | statische Checks des offiziellen Plugin Check in WordPress + seine PHPCS-Regeln (gepinnte Version) |
| Browser (Playwright) | `tests/e2e/*.spec.js` | Dashboard mit Charts, Filter, CSV-Export, Audit, Einstellungen inkl. Nonce, Erklärseite, Editor-Panel; öffentlich über echtes HTTP: Tracking, Zitat-Alarm, llms.txt, .md mit 304, JSON-LD, TOC, robots.txt |

## Pipeline

[`.github/workflows/ci.yml`](.github/workflows/ci.yml) läuft bei jedem Push auf `main`, in Pull Requests, wöchentlich und manuell. Jeder Job blockiert – das Release-ZIP entsteht nur, wenn alle grün sind.

| Bereich | Prüfung |
| --- | --- |
| **Lauffähigkeit** | `php -l` auf PHP 7.4 – 8.5 · PHPUnit auf 7.4 und 8.4 · Integrationstest in echtem WordPress · Browsertests (Playwright) – jeweils auf der ältesten (PHP 7.4 / WP 6.5) und neuesten Kombination |
| **Sicherheit** | PHPCS `WordPress.Security`/`DB` + VIP-Security-Sniffs · ESLint `no-unsanitized` (DOM-XSS) · REST-Rechte- und Nonce-Tests · Plugin Check (offizielle Action + gepinnte PHPCS-Regeln) · gitleaks über die ganze Historie · `composer audit` / `npm audit` · actionlint + zizmor für die Workflows |
| **Codequalität** | PHPCS WordPress-Extra ohne Baseline · PHPStan Level 8 gegen PHP 7.4 – 8.5 · PHPCompatibility · ESLint inkl. React-Hooks-Regeln · Dashboard-Bundle passt zum Quellcode |
| **wordpress.org** | Readme/Header/Versionen/Changelog/Assets (`scripts/repo-checks.mjs`) · „Tested up to“ gegen die aktuelle WP-Version · Links erreichbar · keine externen Ressourcen in den Assets · Übersetzungen vollständig |
| **ZIP** | reproduzierbar (gleicher Commit → gleiche SHA-256), nur erlaubte Dateitypen, wird nach dem Bauen geprüft (Struktur, Version, Direktzugriffs-Schutz, 10-MB-Limit) |

Actions sind auf Commit-SHAs gepinnt, Werkzeuge werden mit fester Version und SHA-256 geladen, Dependabot hält beides aktuell (mit 7 Tagen Abkühlzeit).

## Release

1. Version in `geo-insights-ai/geo-insights-ai.php` (Header **und** `GEOINS_VERSION`) und `readme.txt` (`Stable tag`) anheben, Changelog-Eintrag `= x.y.z =` ergänzen. `npm run check` meldet jede Abweichung.
2. Tag pushen:

   ```bash
   git tag v2.1.0
   ```

   ```bash
   git push origin v2.1.0
   ```

3. [`.github/workflows/release.yml`](.github/workflows/release.yml) führt die komplette CI aus, prüft Tag = Version, erstellt ein GitHub-Release mit ZIP + SHA-256 und – sobald freigeschaltet – deployt genau dieses ZIP nach wordpress.org.

## Veröffentlichung auf wordpress.org (einmalig)

1. Öffentliches GitHub-Repo `lcswll/wordpress-geo` anlegen und pushen – Plugin-URI und readme verlinken darauf, die CI prüft, dass der Link erreichbar ist.
2. wordpress.org-Konto `lcswll` (steht in `readme.txt` unter `Contributors:`) mit aktivierter Zwei-Faktor-Authentifizierung.
3. ZIP bauen (`npm run build`) und unter [wordpress.org/plugins/developers/add](https://wordpress.org/plugins/developers/add/) hochladen. Slug: `geo-insights-ai` (am 02.10.2026 noch frei).
4. Prüfung durch das Plugin-Team abwarten (Mail kommt an die Konto-Adresse; Rückfragen dort beantworten).
5. Nach der Freigabe: SVN-Passwort unter *Profil → Konto & Sicherheit* erzeugen, im GitHub-Repo die Secrets `SVN_USERNAME` und `SVN_PASSWORD` sowie die Variable `WPORG_DEPLOY=true` setzen. Optional die Umgebung `wordpress-org` mit Freigabe absichern.
6. Ab dann veröffentlicht jeder Tag automatisch – inklusive Icon, Banner und Screenshots aus `.wordpress-org/`.

Übersetzungen: wordpress.org baut Sprachpakete über translate.wordpress.org; die mitgelieferte deutsche Übersetzung ist nur der Fallback, solange es kein Sprachpaket gibt.

## Versionshistorie

**2.2.0**: Neues Admin-Design mit Marken-Kopfzeile (Radar-Logo, Navigation zwischen allen Plugin-Seiten, „by Lucas Wille“ → lucaswille.de) und Indigo-Akzent. Autor im Plugin-Header (Author/Author URI), in der Plugin-Liste („How it works“, „Rate ★★★★★“) und in der Fußzeile der Plugin-Seiten. Bewertungs-Bitte (`GEOINS_Review`): nur auf den eigenen Seiten, frühestens 14 Tage nach Aktivierung und ab 50 AI-Zugriffen in 30 Tagen, „Vielleicht später“ = 30 Tage Ruhe, „Habe ich schon“ = nie wieder. Animiertes GIF-Icon für das Plugin-Verzeichnis (Radar-Sweep, `node scripts/wporg-assets.mjs`, braucht ffmpeg) und neues Banner.

**2.1.0**: CI-Pipeline wie bei wordpress-mails (PHPCS/PHPStan Level 8 ohne Baseline, Unit-, Integrations- und Browsertests in echtem WordPress, Plugin Check, reproduzierbares ZIP, Release-Workflow mit wordpress.org-Deploy). Mindestversion WordPress 6.5 (Übersetzung als `.l10n.php`, Laden ohne `load_plugin_textdomain()`), alle Statistik-Queries mit `%i`-Identifiern und Spalten-Allowlist, Meta-Box ohne `innerHTML`, Dashboard-Quellcode im Plugin.

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

## Lizenz

GPLv2 oder später – siehe [LICENSE](LICENSE).
