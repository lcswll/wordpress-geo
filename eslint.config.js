// ESLint flat config: correctness + DOM-XSS rules for the shipped scripts and the React dashboard source,
// Node rules for the tooling. The built bundle (assets/js/geoins-dashboard.js) is generated from src/dashboard/.
import js from '@eslint/js';
import nounsanitized from 'eslint-plugin-no-unsanitized';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';

const security = {
	// innerHTML/outerHTML/insertAdjacentHTML/document.write with non-literal input.
	'no-unsanitized/method': 'error',
	'no-unsanitized/property': 'error',
	'no-eval': 'error',
	'no-implied-eval': 'error',
	'no-new-func': 'error',
	'no-script-url': 'error',
	eqeqeq: ['error', 'always'],
	'no-unused-vars': ['error', { args: 'after-used', caughtErrors: 'none' }],
};

export default [
	{
		ignores: [
			'node_modules/**',
			'vendor/**',
			'.cache/**',
			'dist/**',
			'test-results/**',
			'playwright-report/**',
			'wille-geo-ai-visibility/assets/js/geoins-dashboard.js',
		],
	},
	{
		// Inline lint directives in comments are ignored and reported; rules change only here.
		linterOptions: { noInlineConfig: true, reportUnusedDisableDirectives: 'error' },
	},
	js.configs.recommended,
	{
		// Plain browser scripts without a build step (ES5 style, wp.* globals from WordPress).
		files: ['wille-geo-ai-visibility/assets/js/**/*.js'],
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'script',
			globals: { ...globals.browser, wp: 'readonly' },
		},
		plugins: { 'no-unsanitized': nounsanitized },
		rules: security,
	},
	{
		// React dashboard source, bundled by scripts/build-dashboard.mjs.
		files: ['wille-geo-ai-visibility/src/**/*.{js,jsx}'],
		languageOptions: {
			ecmaVersion: 2024,
			sourceType: 'module',
			parserOptions: { ecmaFeatures: { jsx: true } },
			globals: { ...globals.browser },
		},
		plugins: { 'no-unsanitized': nounsanitized, 'react-hooks': reactHooks },
		rules: {
			...security,
			'react-hooks/rules-of-hooks': 'error',
			'react-hooks/exhaustive-deps': 'error',
			'no-var': 'error',
			'prefer-const': 'error',
		},
	},
	{
		files: ['**/*.mjs', 'eslint.config.js', 'tests/e2e/**/*.js', 'playwright.config.js'],
		languageOptions: {
			ecmaVersion: 2024,
			sourceType: 'module',
			globals: { ...globals.node },
		},
		rules: {
			'no-unused-vars': ['error', { caughtErrors: 'none' }],
		},
	},
	{
		// Callbacks passed to page.evaluate() run in the browser.
		files: ['tests/e2e/**/*.js', 'scripts/wporg-assets.mjs'],
		languageOptions: {
			globals: { ...globals.node, ...globals.browser },
		},
	},
];
