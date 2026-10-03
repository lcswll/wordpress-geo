// Public side over real HTTP: AI bot and referral tracking, llms.txt, Markdown endpoints, robots.txt, JSON-LD.
// The page fixture is logged in (Playground --login); `anon` is a logged-out request context (see anonymous()).
import { expect, test } from '@playwright/test';

const POST = '/wordpress-backup-guide/';
const GPTBOT = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)';
// Not part of the seeded traffic, so its first visit is a first contact.
const PERPLEXITY_USER = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)';
const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

test.describe.configure({ mode: 'serial' });

// Playground runs several PHP workers whose SQLite views converge with a short delay, so a hit logged by one
// request can take a moment to show up in the next one. Poll instead of reading once.
const SYNC = { timeout: 20_000, intervals: [500, 1000, 2000] };

/** Submits the settings form and waits for options.php to redirect back (settings-updated=true). */
async function saveSettings(page) {
	await Promise.all([page.waitForURL(/settings-updated=true/), page.getByRole('button', { name: 'Save settings' }).click()]);
}

/** Dashboard data as the admin sees it (REST with the nonce the dashboard page localizes). */
async function dashboard(page, query = '') {
	if (!page.url().includes('page=wille-geo')) await page.goto('/wp-admin/admin.php?page=wille-geo');
	return page.evaluate(async (q) => {
		const c = window.geoinsDash;
		const base = c.restUrl + 'geoins/v1/dashboard';
		const res = await fetch(base + (base.includes('?') ? '&' : '?') + 'days=7' + q, { headers: { 'X-WP-Nonce': c.nonce } });
		return res.json();
	}, query);
}

/** Accesses of one bot or AI referral source in the dashboard response. */
function countFor(data, source) {
	const row = [...(data.botSources || []), ...(data.referrals || [])].find((r) => r.source === source);
	return row ? Number(row.count) : 0;
}

/**
 * A logged-out visitor. Playground's --login signs in every cookie-less request automatically; the marker cookie
 * tells it this client has had its auto-login already, so the request stays anonymous.
 */
function anonymous(playwright, baseURL) {
	return playwright.request.newContext({ baseURL, extraHTTPHeaders: { Cookie: 'playground_auto_login_already_happened=1' } });
}

test('AI bot visit is tracked, a normal browser is not', async ({ page, playwright, baseURL }) => {
	const before = await dashboard(page, '&sources=gptbot');
	const anon = await anonymous(playwright, baseURL);

	const bot = await anon.get(POST, { headers: { 'User-Agent': GPTBOT } });
	expect(bot.status()).toBe(200);
	const human = await anon.get(POST, { headers: { 'User-Agent': BROWSER } });
	expect(human.status()).toBe(200);

	await expect.poll(async () => countFor(await dashboard(page, '&sources=gptbot'), 'gptbot'), SYNC).toBe(countFor(before, 'gptbot') + 1);
	await anon.dispose();
});

test('visitors from AI answers are tracked via Referer and utm_source', async ({ page, playwright, baseURL }) => {
	const before = await dashboard(page);
	const anon = await anonymous(playwright, baseURL);

	// Distinct visitors: the plugin counts the same visitor + page + source only once per minute.
	const run = Date.now();
	await anon.get(POST, { headers: { 'User-Agent': `${BROWSER} visitor-a-${run}`, Referer: 'https://chatgpt.com/' } });
	await anon.get(`${POST}?utm_source=perplexity`, { headers: { 'User-Agent': `${BROWSER} visitor-b-${run}` } });

	await expect.poll(async () => countFor(await dashboard(page), 'chatgpt'), SYNC).toBe(countFor(before, 'chatgpt') + 1);
	await expect.poll(async () => countFor(await dashboard(page), 'perplexity'), SYNC).toBe(countFor(before, 'perplexity') + 1);
	await anon.dispose();
});

test('a first visit by a new AI raises a citation alert', async ({ page, playwright, baseURL }) => {
	const anon = await anonymous(playwright, baseURL);
	await anon.get(POST, { headers: { 'User-Agent': PERPLEXITY_USER } });
	await anon.dispose();

	await expect(async () => {
		await page.goto('/wp-admin/admin.php?page=wille-geo');
		await expect(page.locator('#geoins-dashboard-root')).toContainText('Perplexity-User', { timeout: 3_000 });
	}).toPass(SYNC);
});

test('llms.txt and llms-full.txt', async ({ playwright, baseURL }) => {
	const anon = await anonymous(playwright, baseURL);

	const llms = await anon.get('/llms.txt');
	expect(llms.status()).toBe(200);
	expect(llms.headers()['content-type']).toContain('text/plain');
	const text = await llms.text();
	expect(text).toMatch(/^# Wille GEO Test Site/);
	expect(text).toContain('WordPress Backup Guide');

	const full = await anon.get('/llms-full.txt');
	expect(full.status()).toBe(200);
	expect(full.headers()['content-type']).toContain('text/markdown');
	expect(await full.text()).toContain('## Why do you need a WordPress backup?');
	await anon.dispose();
});

test('Markdown variant of a post with conditional GET', async ({ playwright, baseURL }) => {
	const anon = await anonymous(playwright, baseURL);

	const md = await anon.get('/wordpress-backup-guide.md');
	expect(md.status()).toBe(200);
	expect(md.headers()['content-type']).toContain('text/markdown');
	expect(md.headers()['x-robots-tag']).toBe('noindex');
	const body = await md.text();
	expect(body).toContain('# WordPress Backup Guide');
	expect(body).toContain('| UpdraftPlus | 0 € |');
	expect(body).not.toMatch(/<\/?(p|h2|table)\b/);

	const lastModified = md.headers()['last-modified'];
	expect(lastModified).toBeTruthy();
	const cached = await anon.get('/wordpress-backup-guide.md', { headers: { 'If-Modified-Since': lastModified }, maxRedirects: 0 });
	expect(cached.status()).toBe(304);

	expect((await anon.get('/no-such-post.md')).status()).toBe(404);
	await anon.dispose();
});

test('post page: JSON-LD, Markdown alternate link, table of contents', async ({ playwright, baseURL }) => {
	const anon = await anonymous(playwright, baseURL);
	const html = await (await anon.get(POST, { headers: { 'User-Agent': BROWSER } })).text();

	expect(html).toContain('<link rel="alternate" type="text/markdown"');
	expect(html).toContain('class="geoins-toc"');
	expect(html).toMatch(/<h2[^>]* id="why-do-you-need-a-wordpress-backup"/);

	const blocks = [...html.matchAll(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/g)].map((m) => JSON.parse(m[1]));
	expect(blocks.length).toBeGreaterThan(0);
	const types = JSON.stringify(blocks);
	for (const type of ['"WebSite"', '"Article"', '"FAQPage"', '"BreadcrumbList"']) expect(types).toContain(type);
	expect(types).toContain('WordPress Developer'); // Author E-E-A-T from the profile fields.
	await anon.dispose();
});

test('robots.txt follows the crawler settings', async ({ page, playwright, baseURL }) => {
	await page.goto('/wp-admin/admin.php?page=wille-geo-settings');
	const gptbot = page.locator('input[name="geoins_settings[blocked_bots][]"][value="gptbot"]');
	await gptbot.check();
	await saveSettings(page);
	await expect(page.locator('.notice-success, #setting-error-settings_updated')).toBeVisible();

	const anon = await anonymous(playwright, baseURL);
	const robots = await (await anon.get('/robots.txt')).text();
	expect(robots).toContain('User-agent: GPTBot\nDisallow: /');
	expect(robots).not.toContain('User-agent: ClaudeBot');

	// Undo, so other tests see the default.
	await page.goto('/wp-admin/admin.php?page=wille-geo-settings');
	await gptbot.uncheck();
	await saveSettings(page);
	expect(await (await anon.get('/robots.txt')).text()).not.toContain('GPTBot');
	await anon.dispose();
});
