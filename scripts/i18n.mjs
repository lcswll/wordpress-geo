#!/usr/bin/env node
/**
 * Translations: extracts the plugin's strings into languages/<slug>.pot (shipped) and builds i18n/<locale>.po from
 * i18n/<locale>.json (NOT shipped – import it on translate.wordpress.org, which delivers language packs).
 *
 *   npm run i18n               # rebuild the language files from i18n/<locale>.json
 *   npm run i18n -- --check    # CI: fail on missing/unused translations or outdated generated files
 *   npm run i18n -- --prune    # drop translations whose string no longer exists in the code
 *
 * The plugin bundles no translation files (wordpress.org review requirement); WordPress loads the language packs
 * just in time. The dashboard's strings are passed from PHP via wp_localize_script, so the PHP files are the only
 * source.
 */
import fs from 'node:fs';
import path from 'node:path';
import { PLUGIN_SLUG, pluginDir, root } from './lib/php.mjs';

const check = process.argv.includes('--check');
const prune = process.argv.includes('--prune');
const FUNCS = '(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)';
const re = new RegExp(`\\b${FUNCS}\\(\\s*(?:'((?:[^'\\\\]|\\\\.)*)'|"((?:[^"\\\\]|\\\\.)*)")\\s*,\\s*'${PLUGIN_SLUG}'\\s*\\)`, 'g');

function walk(dir, out = []) {
	for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
		const p = path.join(dir, e.name);
		if (e.isDirectory()) {
			if (e.name !== 'languages') walk(p, out);
		} else if (p.endsWith('.php')) {
			out.push(p);
		}
	}
	return out;
}

const strings = new Set();
for (const file of walk(pluginDir).sort()) {
	for (const m of fs.readFileSync(file, 'utf8').matchAll(re)) {
		strings.add(m[1] !== undefined ? m[1].replace(/\\'/g, "'").replace(/\\\\/g, '\\') : m[2].replace(/\\"/g, '"').replace(/\\\\/g, '\\'));
	}
}
// translate.wordpress.org also extracts these plugin header fields (shown in the Plugins list).
const mainFile = fs.readFileSync(path.join(pluginDir, `${PLUGIN_SLUG}.php`), 'utf8');
for (const field of ['Plugin Name', 'Plugin URI', 'Description', 'Author', 'Author URI']) {
	const m = mainFile.match(new RegExp(`^\\s*\\*\\s*${field}:\\s*(.+?)\\s*$`, 'm'));
	if (!m) {
		console.error(`Plugin header "${field}" not found in ${PLUGIN_SLUG}.php`);
		process.exit(1);
	}
	strings.add(m[1]);
}
const sorted = [...strings].sort();

// Placeholders and markup must survive translation unchanged (order may differ only with positional %1$s).
const tokens = (s) => [...s.matchAll(/%(?:\d+\$)?[sdf]|%%|<\/?[a-z][^>]*>/gi)].map((m) => m[0]).sort().join(' ');
// Plural-Forms per locale (gettext header; the plugin has no _n() strings, the header is informational).
const PLURALS = {
	ja: 'nplurals=1; plural=0;', ko_KR: 'nplurals=1; plural=0;', zh_CN: 'nplurals=1; plural=0;', zh_TW: 'nplurals=1; plural=0;',
	id_ID: 'nplurals=1; plural=0;', vi: 'nplurals=1; plural=0;', th: 'nplurals=1; plural=0;', fa_IR: 'nplurals=1; plural=0;',
	tr_TR: 'nplurals=2; plural=(n > 1);', fr_FR: 'nplurals=2; plural=(n > 1);', pt_BR: 'nplurals=2; plural=(n > 1);', hi_IN: 'nplurals=2; plural=(n != 1);',
	ru_RU: 'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
	uk: 'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
	hr: 'nplurals=3; plural=(n % 10 == 1 && n % 100 != 11) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
	pl_PL: 'nplurals=3; plural=(n == 1) ? 0 : ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 : 2);',
	cs_CZ: 'nplurals=3; plural=(n == 1) ? 0 : ((n >= 2 && n <= 4) ? 1 : 2);',
	sk_SK: 'nplurals=3; plural=(n == 1) ? 0 : ((n >= 2 && n <= 4) ? 1 : 2);',
	ro_RO: 'nplurals=3; plural=(n == 1) ? 0 : ((n == 0 || n % 100 >= 2 && n % 100 <= 19) ? 1 : 2);',
	ar: 'nplurals=6; plural=(n == 0) ? 0 : ((n == 1) ? 1 : ((n == 2) ? 2 : ((n % 100 >= 3 && n % 100 <= 10) ? 3 : ((n % 100 >= 11 && n % 100 <= 99) ? 4 : 5))));',
	he_IL: 'nplurals=2; plural=(n != 1);',
};

const po = (s) => '"' + s.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n') + '"';

const problems = [];
const files = {};
const i18nDir = path.join(root, 'i18n');
for (const jsonFile of fs.readdirSync(i18nDir).filter((f) => f.endsWith('.json')).sort()) {
	const locale = path.basename(jsonFile, '.json');
	const jsonPath = path.join(i18nDir, jsonFile);
	const map = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));
	for (const s of sorted) {
		if (!(s in map)) problems.push(`${locale}: missing translation for "${s}"`);
		else if (typeof map[s] !== 'string' || !map[s].trim()) problems.push(`${locale}: empty translation for "${s}"`);
		else if (tokens(map[s]) !== tokens(s)) problems.push(`${locale}: placeholders/markup differ for "${s}" → "${map[s]}"`);
	}
	const unused = Object.keys(map).filter((s) => !strings.has(s));
	if (prune && unused.length) {
		for (const s of unused) delete map[s];
		fs.writeFileSync(jsonPath, JSON.stringify(map, null, '\t') + '\n');
		console.log(`${locale}: removed ${unused.length} unused translation(s).`);
	} else {
		for (const s of unused) problems.push(`${locale}: unused translation "${s}" (npm run i18n -- --prune)`);
	}

	files[path.join(i18nDir, `${locale}.po`)] = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n' +
		`"Language: ${locale}\\n"\n"Plural-Forms: ${PLURALS[locale] ?? 'nplurals=2; plural=(n != 1);'}\\n"\n"X-Domain: ${PLUGIN_SLUG}\\n"\n\n` +
		sorted.filter((s) => s in map).map((s) => `msgid ${po(s)}\nmsgstr ${po(map[s])}\n`).join('\n');
}

// Template for translators (and the wordpress.org import as a cross-check).
files[path.join(pluginDir, 'languages', `${PLUGIN_SLUG}.pot`)] = 'msgid ""\nmsgstr ""\n"Content-Type: text/plain; charset=UTF-8\\n"\n' +
	`"X-Domain: ${PLUGIN_SLUG}\\n"\n\n` + sorted.map((s) => `msgid ${po(s)}\nmsgstr ""\n`).join('\n');

// Nothing but the .pot may ship in languages/ (old bundled .po/.l10n.php/.mo files must not linger in the release).
const languagesDir = path.join(pluginDir, 'languages');
const stale = fs.existsSync(languagesDir)
	? fs.readdirSync(languagesDir).map((f) => path.join(languagesDir, f)).filter((f) => !(f in files))
	: [];

if (check) {
	for (const [file, content] of Object.entries(files)) {
		const current = fs.existsSync(file) ? fs.readFileSync(file, 'utf8').replace(/\r\n/g, '\n') : null;
		if (current !== content) problems.push(`${path.relative(root, file)} is outdated – run \`npm run i18n\``);
	}
	for (const file of stale) problems.push(`${path.relative(root, file)} is not generated by scripts/i18n.mjs – run \`npm run i18n\``);
	if (problems.length) {
		console.error(problems.join('\n'));
		process.exit(1);
	}
	console.log(`i18n: ${strings.size} strings, ${Object.keys(files).length - 1} locale(s) complete and up to date.`);
	process.exit(0);
}

if (problems.length) {
	console.error(problems.join('\n'));
	process.exit(1);
}
fs.mkdirSync(languagesDir, { recursive: true });
for (const file of stale) fs.rmSync(file);
for (const [file, content] of Object.entries(files)) fs.writeFileSync(file, content);
console.log(`${strings.size} strings written for ${Object.keys(files).length - 1} locale(s).`);
