#!/usr/bin/env node
/**
 * Build the React dashboard bundle.
 *
 *   npm run build:dashboard                          # build
 *   npm run watch                                    # rebuild on change (unminified)
 *   node scripts/build-dashboard.mjs --check         # CI: fail if the committed bundle differs from the source
 *
 * Source: geo-insights-ai/src/dashboard/ (shipped with the plugin, so the minified bundle can be reviewed and rebuilt).
 * Output: geo-insights-ai/assets/js/geoins-dashboard.js (self-contained IIFE: React + ECharts bundled, nothing leaks
 * to window).
 */
import fs from 'node:fs';
import path from 'node:path';
import * as esbuild from 'esbuild';
import { root } from './lib/php.mjs';

const outfile = path.join(root, 'geo-insights-ai', 'assets', 'js', 'geoins-dashboard.js');
const options = {
	absWorkingDir: root,
	entryPoints: ['geo-insights-ai/src/dashboard/index.jsx'],
	outfile,
	bundle: true,
	minify: true,
	format: 'iife',
	target: ['es2019'],
	jsx: 'automatic',
	define: { 'process.env.NODE_ENV': '"production"' },
	// Keep the bundled libraries' @license headers (MIT requires shipping
	// the copyright + permission notice with every copy).
	legalComments: 'eof',
	logLevel: 'info',
};

if (process.argv.includes('--watch')) {
	const ctx = await esbuild.context({ ...options, minify: false });
	await ctx.watch();
	console.log('watching…');
} else if (process.argv.includes('--check')) {
	const result = await esbuild.build({ ...options, write: false, logLevel: 'warning' });
	const built = Buffer.from(result.outputFiles[0].contents);
	const committed = fs.existsSync(outfile) ? fs.readFileSync(outfile) : Buffer.alloc(0);
	if (!built.equals(committed)) {
		console.error('geo-insights-ai/assets/js/geoins-dashboard.js is outdated – run `npm run build:dashboard` and commit it.');
		process.exit(1);
	}
	console.log(`Dashboard bundle matches src/dashboard/ (${(built.length / 1024).toFixed(0)} KB).`);
} else {
	await esbuild.build(options);
}
