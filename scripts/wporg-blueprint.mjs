#!/usr/bin/env node
/**
 * Live Preview on wordpress.org: builds .wordpress-org/blueprints/blueprint.json (deployed to SVN assets/blueprints/).
 *
 *   node scripts/wporg-blueprint.mjs           # write the blueprint
 *   node scripts/wporg-blueprint.mjs --check   # CI: fail if it is outdated
 *
 * The preview installs the released plugin from wordpress.org and runs the same seed as the browser tests and the
 * directory screenshots (tests/e2e/seed.php: sample posts + 90 days of AI traffic), so visitors see a filled
 * dashboard instead of "No data yet". The seed is inlined because Playground cannot read files from this repo.
 */
import fs from 'node:fs';
import path from 'node:path';
import { PLUGIN_SLUG, root } from './lib/php.mjs';

const check = process.argv.includes('--check');
const seedFile = path.join(root, 'tests', 'e2e', 'seed.php');
const outFile = path.join(root, '.wordpress-org', 'blueprints', 'blueprint.json');

// The e2e-only marker file (/e2e-out does not exist in the preview) is dropped.
const marker = /^file_put_contents\( '\/e2e-out\/seeded'.*\n/m;
const seed = fs.readFileSync(seedFile, 'utf8').replace(/\r\n/g, '\n');
if (!marker.test(seed)) {
	console.error('tests/e2e/seed.php: the /e2e-out/seeded marker line was not found – update scripts/wporg-blueprint.mjs');
	process.exit(1);
}

const blueprint = {
	$schema: 'https://playground.wordpress.net/blueprint-schema.json',
	landingPage: `/wp-admin/admin.php?page=wille-geo`,
	preferredVersions: { php: '8.3', wp: 'latest' },
	features: { networking: true },
	login: true,
	steps: [
		{ step: 'setSiteOptions', options: { blogname: 'Wille GEO Demo', blogdescription: 'Guides about WordPress and AI search', permalink_structure: '/%postname%/' } },
		{ step: 'installPlugin', pluginData: { resource: 'wordpress.org/plugins', slug: PLUGIN_SLUG }, options: { activate: true } },
		{ step: 'runPHP', code: seed.replace(marker, '') },
	],
};
const content = JSON.stringify(blueprint, null, '\t') + '\n';

if (check) {
	const current = fs.existsSync(outFile) ? fs.readFileSync(outFile, 'utf8').replace(/\r\n/g, '\n') : null;
	if (current !== content) {
		console.error('.wordpress-org/blueprints/blueprint.json is outdated – run `node scripts/wporg-blueprint.mjs`');
		process.exit(1);
	}
	console.log('Live Preview blueprint up to date.');
	process.exit(0);
}
fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, content);
console.log(`Wrote ${path.relative(root, outFile)}.`);
