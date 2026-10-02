#!/usr/bin/env node
/**
 * Generates the wordpress.org directory assets in .wordpress-org/ (deployed to SVN /assets, not into the plugin):
 *
 *   node scripts/wporg-assets.mjs                                # icons, banners and screenshots
 *   node scripts/wporg-assets.mjs --no-shots                     # only icons and banners
 *   node scripts/wporg-assets.mjs --base http://127.0.0.1:9400   # screenshots from an already running `npm run playground`
 *
 * Icons/banners come from scripts/assets/wporg-brand.html. Screenshots are taken from the real admin screens in
 * WordPress Playground, seeded with 30 days of example traffic (tests/e2e/seed.php). Uses the locally installed Edge.
 * The captions are the "== Screenshots ==" list in geo-insights-ai/readme.txt (same order).
 */
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';
import { root } from './lib/php.mjs';

const args = process.argv.slice(2);
const out = path.join(root, '.wordpress-org');
const shots = !args.includes('--no-shots');
const external = args.includes('--base') ? args[args.indexOf('--base') + 1] : null;
fs.mkdirSync(out, { recursive: true });
const browser = await chromium.launch(process.env.CI ? {} : { channel: 'msedge' });

// ------------------------------------------------------------------ icons + banners.
const brand = pathToFileURL(path.join(root, 'scripts', 'assets', 'wporg-brand.html')).href;
for (const [file, asset, width, height] of [
	['icon-128x128.png', 'icon', 128, 128],
	['icon-256x256.png', 'icon', 256, 256],
	['banner-772x250.png', 'banner', 772, 250],
	['banner-1544x500.png', 'banner', 1544, 500],
]) {
	const page = await browser.newPage({ viewport: { width, height } });
	await page.goto(`${brand}?asset=${asset}`);
	await page.screenshot({ path: path.join(out, file) });
	await page.close();
	console.log(`✓ ${file}`);
}

// --------------------------------------------------------------------- screenshots.
if (shots) {
	const port = 9420;
	const base = external || `http://127.0.0.1:${port}`;
	let server = null;
	if (!external) {
		const marker = path.join(root, '.cache', 'e2e-out', 'seeded');
		fs.rmSync(marker, { force: true });
		server = spawn(process.execPath, [path.join(root, 'scripts', 'playground-server.mjs'), '--port', String(port)], { cwd: root, stdio: 'ignore' });
		const deadline = Date.now() + 5 * 60_000;
		while (!fs.existsSync(marker)) {
			if (Date.now() > deadline) throw new Error('Playground did not start.');
			await new Promise((r) => setTimeout(r, 500));
		}
	}

	try {
		const page = await browser.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
		const admin = (slug) => `${base}/wp-admin/admin.php?page=${slug}`;
		const tidy = async () => {
			await page.addStyleTag({ content: '#wpfooter, .notice, .update-nag, #screen-meta-links { display: none !important; } html { scroll-behavior: auto !important; }' });
			await page.evaluate(() => document.activeElement?.blur());
		};
		const scrollTo = async (locator) => {
			await locator.scrollIntoViewIfNeeded();
			await page.evaluate(() => window.scrollBy(0, -60));
		};
		const shot = async (n, what) => {
			await page.waitForTimeout(900); // Chart animations.
			await page.screenshot({ path: path.join(out, `screenshot-${n}.png`) });
			console.log(`✓ screenshot-${n}.png (${what})`);
		};

		// 1. Dashboard: KPIs, alerts, timeline.
		await page.goto(admin('geo-insights'));
		const dash = page.locator('#geoins-dashboard-root');
		await dash.getByRole('heading', { name: 'AI accesses per day' }).waitFor();
		await tidy();
		await shot(1, 'dashboard');

		// 2. Term × AI matrix and flows.
		await scrollTo(dash.getByRole('heading', { name: 'Terms & pages: which AI reads what?' }));
		await shot(2, 'term matrix');

		// 3. Companies treemap + crawl heatmap.
		await scrollTo(dash.getByRole('heading', { name: 'AI companies: share of accesses' }));
		await shot(3, 'treemap + heatmap');

		// 4. GEO audit.
		await page.goto(admin('geo-insights-audit'));
		await page.locator('#geoins-audit-root table.geoins-audit-table tbody tr').first().waitFor({ timeout: 60_000 });
		await tidy();
		await shot(4, 'audit');

		// 5. Live GEO checks in the block editor.
		const id = await page.evaluate(async () => (await (await fetch('/wp-json/wp/v2/posts?slug=wordpress-backup-guide')).json())[0].id);
		await page.goto(`${base}/wp-admin/post.php?post=${id}&action=edit`);
		const guide = page.getByRole('dialog').getByRole('button', { name: /close/i });
		if (await guide.waitFor({ timeout: 15_000 }).then(() => true, () => false)) await guide.click();
		const panel = page.getByRole('button', { name: /GEO check/i }).first();
		await panel.waitFor({ timeout: 60_000 });
		if ((await panel.getAttribute('aria-expanded')) === 'false') await panel.click();
		await page.getByText('Focus term set').first().waitFor();
		await panel.evaluate((el) => el.scrollIntoView({ block: 'start' }));
		await page.evaluate(() => document.activeElement?.blur());
		await shot(5, 'editor panel');

		// 6. Settings: every option explains its benefit.
		await page.goto(admin('geo-insights-settings'));
		await tidy();
		await shot(6, 'settings');

		// 7. AI crawler control grouped by what blocking costs.
		await scrollTo(page.getByRole('heading', { name: 'AI crawler access (robots.txt)' }));
		await shot(7, 'crawler control');
	} finally {
		server?.kill();
	}
}

await browser.close();
