// Admin screens in a real browser: React dashboard, CSV export, GEO audit, settings, explainer page, editor panel.
import { expect, test } from '@playwright/test';

const DASHBOARD = '/wp-admin/admin.php?page=geo-insights';

/**
 * Collects console errors, uncaught exceptions and requests to other hosts (the plugin promises
 * "no external services": its admin screens must not load anything from elsewhere).
 */
function watch(page) {
	const errors = [];
	const external = [];
	page.on('console', (msg) => {
		if (msg.type() === 'error') errors.push(msg.text());
	});
	page.on('pageerror', (err) => errors.push(err.message));
	page.on('request', (req) => {
		const url = new URL(req.url());
		if (!url.protocol.startsWith('http')) return; // blob:/data: URLs (ECharts) are local.
		// WordPress core itself may fetch Gravatar/emoji/s.w.org – not the plugin's business.
		if (!['127.0.0.1', 'localhost'].includes(url.hostname) && !/(^|\.)(gravatar\.com|w\.org|wordpress\.org)$/.test(url.hostname)) {
			external.push(req.url());
		}
	});
	page.on('dialog', (dialog) => dialog.accept());
	return { errors, external };
}

test.describe.configure({ mode: 'serial' });

/** Submits the settings form and waits for options.php to redirect back (settings-updated=true). */
async function saveSettings(page) {
	await Promise.all([page.waitForURL(/settings-updated=true/), page.getByRole('button', { name: 'Save settings' }).click()]);
}

test('dashboard renders the seeded statistics with charts', async ({ page }) => {
	const seen = watch(page);
	await page.goto(DASHBOARD);

	const root = page.locator('#geoins-dashboard-root');
	await expect(root.getByRole('heading', { name: 'AI accesses per day' })).toBeVisible();
	await expect(root.getByRole('heading', { name: 'Which AI bots?' })).toBeVisible();
	await expect(root.getByRole('heading', { name: 'Human visitors from AI answers' })).toBeVisible();
	await expect(root).toContainText('GPTBot');
	await expect(root).toContainText('WordPress backup plugin'); // Term × AI matrix uses the focus term.

	// ECharts draws into canvases; every chart got a non-empty size.
	const canvases = root.locator('canvas');
	await expect(canvases.first()).toBeVisible();
	expect(await canvases.count()).toBeGreaterThanOrEqual(4);
	for (const box of await canvases.evaluateAll((els) => els.map((el) => [el.width, el.height]))) {
		expect(box[0]).toBeGreaterThan(0);
		expect(box[1]).toBeGreaterThan(0);
	}

	expect(seen.errors).toEqual([]);
	expect(seen.external).toEqual([]);

	// The matrix really runs the requested WordPress version (the CLI banner always shows its defaults).
	if (process.env.E2E_WP && process.env.E2E_WP !== 'latest') {
		expect(await page.content()).toMatch(new RegExp(`ver=${process.env.E2E_WP.replace('.', '\\.')}(\\.\\d+)?\\b`));
	}
});

test('date range and company filter reload the data', async ({ page }) => {
	const seen = watch(page);
	await page.goto(DASHBOARD);
	const root = page.locator('#geoins-dashboard-root');
	await expect(root.getByRole('heading', { name: 'AI accesses per day' })).toBeVisible();

	const range = page.waitForRequest((req) => req.url().includes('geoins/v1/dashboard') && req.url().includes('days=90'));
	await root.getByRole('button', { name: /90/ }).click();
	await range;

	const filter = page.waitForRequest((req) => req.url().includes('geoins/v1/dashboard') && req.url().includes('sources='));
	await root.getByRole('button', { name: /Anthropic/ }).first().click();
	await filter;
	await expect(root).toContainText('ClaudeBot');
	await expect(root).not.toContainText('GPTBot');

	expect(seen.errors).toEqual([]);
});

test('CSV export', async ({ page }) => {
	await page.goto(DASHBOARD);
	const download = page.waitForEvent('download');
	await page.locator('#geoins-dashboard-root').getByText('Export CSV').click();
	const file = await download;
	expect(file.suggestedFilename()).toMatch(/\.csv$/);
	const csv = await (await file.createReadStream()).toArray();
	const text = Buffer.concat(csv).toString('utf8');
	expect(text.split('\n').length).toBeGreaterThan(2);
	expect(text).toContain('gptbot');
});

test('GEO audit scans and lists every published post', async ({ page }) => {
	const seen = watch(page);
	await page.goto('/wp-admin/admin.php?page=geo-insights-audit');
	const root = page.locator('#geoins-audit-root');
	const rows = root.locator('table.geoins-audit-table tbody tr');
	await expect(rows.first()).toBeVisible({ timeout: 30_000 });
	await expect(root).toContainText('WordPress Backup Guide');
	await expect(root).toContainText('Best SEO Plugins 2026');
	await expect(root).toContainText('About us');
	expect(seen.errors).toEqual([]);
});

test('settings explain every option and save', async ({ page }) => {
	const seen = watch(page);
	await page.goto('/wp-admin/admin.php?page=geo-insights-settings');
	await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

	// Quick action: block all training bots (only the "safe" group).
	await page.getByRole('button', { name: /training bots/i }).click();
	const gptbot = page.locator('input[name="geoins_settings[blocked_bots][]"][value="gptbot"]');
	const chatgptUser = page.locator('input[name="geoins_settings[blocked_bots][]"][value="chatgpt-user"]');
	await expect(gptbot).toBeChecked();
	await expect(chatgptUser).not.toBeChecked();

	await page.locator('#geoins-llms-intro').fill('Guides about WordPress backups & AI search.');
	await saveSettings(page);
	await expect(page.locator('#geoins-llms-intro')).toHaveValue('Guides about WordPress backups & AI search.');
	await expect(gptbot).toBeChecked();

	// Restore the defaults for the other tests.
	await page.getByRole('button', { name: /allow all/i }).click();
	await page.locator('#geoins-llms-intro').fill('');
	await saveSettings(page);
	await expect(gptbot).not.toBeChecked();
	expect(seen.errors).toEqual([]);
});

test('settings form rejects requests without a valid nonce', async ({ page }) => {
	await page.goto('/wp-admin/admin.php?page=geo-insights-settings');
	const status = await page.evaluate(async () => {
		const body = new FormData();
		body.append('option_page', 'geoins_settings_group');
		body.append('action', 'update');
		body.append('_wpnonce', 'invalid');
		body.append('geoins_settings[llms_intro]', 'hacked');
		const res = await fetch('/wp-admin/options.php', { method: 'POST', body, credentials: 'same-origin' });
		return res.status;
	});
	expect(status).toBeGreaterThanOrEqual(400);
	await page.reload();
	await expect(page.locator('#geoins-llms-intro')).not.toHaveValue('hacked');
});

test('explainer page', async ({ page }) => {
	const seen = watch(page);
	await page.goto('/wp-admin/admin.php?page=geo-insights-learn');
	await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
	await expect(page.locator('.wrap')).toContainText('GEO');
	expect(seen.errors).toEqual([]);
});

test('block editor shows the live GEO check panel', async ({ page }) => {
	const seen = watch(page);
	const id = await page.evaluate(async () => {
		const res = await fetch('/wp-json/wp/v2/posts?slug=wordpress-backup-guide', { headers: { 'X-WP-Nonce': window.wpApiSettings?.nonce ?? '' } });
		return (await res.json())[0]?.id;
	}).catch(() => null);
	await page.goto(id ? `/wp-admin/post.php?post=${id}&action=edit` : '/wp-admin/post-new.php');

	// Close the welcome guide if WordPress shows it.
	const guide = page.getByRole('dialog').getByRole('button', { name: /close/i });
	if (await guide.waitFor({ timeout: 15_000 }).then(() => true, () => false)) await guide.click();

	const panel = page.getByRole('button', { name: /GEO check/i }).first();
	await expect(panel).toBeVisible({ timeout: 30_000 });
	if ((await panel.getAttribute('aria-expanded')) === 'false') await panel.click();
	// The checks run live on the editor content (geoins-editor.js) – a question heading exists in the seeded post.
	await expect(page.getByText('Focus term set').first()).toBeVisible();
	await expect(page.getByText('At least one heading phrased as a question').first()).toBeVisible();
	expect(seen.errors.filter((e) => !/wp\.editPost\.PluginDocumentSettingPanel is deprecated/.test(e))).toEqual([]);
});
