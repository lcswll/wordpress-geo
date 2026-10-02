/**
 * Build the React dashboard bundle.
 *
 * Usage:  node tools-build_dashboard.mjs [--watch]
 * Output: geo-insights-ai/assets/js/geoins-dashboard.js (self-contained
 * IIFE: React + Recharts bundled, nothing leaks to window).
 */
import * as esbuild from 'esbuild';

const options = {
	entryPoints: [ 'src/dashboard/index.jsx' ],
	outfile: 'geo-insights-ai/assets/js/geoins-dashboard.js',
	bundle: true,
	minify: true,
	format: 'iife',
	target: [ 'es2019' ],
	jsx: 'automatic',
	define: { 'process.env.NODE_ENV': '"production"' },
	// Keep the bundled libraries' @license headers (MIT requires shipping
	// the copyright + permission notice with every copy).
	legalComments: 'eof',
	logLevel: 'info',
};

if ( process.argv.includes( '--watch' ) ) {
	const ctx = await esbuild.context( { ...options, minify: false } );
	await ctx.watch();
	console.log( 'watching…' );
} else {
	await esbuild.build( options );
}
