// SmartGro browser checks: the public website, on a server that uses the TEST database.
//
//   node docs/operations/scripts/browser/verify-public.mjs <baseUrl> <outDir> [errorPagesDir]
//
// Uses Playwright and axe-core already in the local npx cache (no project dependency). Override their
// paths with PLAYWRIGHT_MODULE and AXE_CORE, and use an installed Chromium instead of Playwright's with CHROMIUM_EXECUTABLE (every script here). For every address at 360, 768, 1024 and 1440 px it records
// the status, horizontal overflow, external requests, failed requests and console errors; at 360 and
// 1440 px axe-core (WCAG 2.0, 2.1 and 2.2, A and AA rules); and with JavaScript off, the heading and
// main content. It then checks, without JavaScript, the phone menu, the workspace-type filter and the
// comparison form; walks the keyboard focus through a location page on a phone; checks the 404, 410
// and 419 answers; and, from errorPagesDir, the rendered 403, 419, 429, 500 and 503 pages. On every
// address it also records any 3D request (three-d-files.mjs): none is allowed (Phase 7 AC 3). It reads
// robots.txt and the sitemap, and loads every address the sitemap lists (FR-SEO-3). Writes
// <outDir>/public.json and prints a summary; exits non-zero when a check fails.
import fs from 'node:fs';
import { isThreeDRequest, threeDFiles } from './three-d-files.mjs';

const PLAYWRIGHT = process.env.PLAYWRIGHT_MODULE ?? '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const AXE = process.env.AXE_CORE ?? '/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/axe-core/axe.min.js';
const { chromium } = await import(PLAYWRIGHT);
const axeSource = fs.readFileSync(AXE, 'utf8');
const axeVersion = JSON.parse(fs.readFileSync(AXE.replace(/axe\.min\.js$/, 'package.json'), 'utf8')).version;
const viewerFiles = threeDFiles();

const [base, outDir, errorPagesDir] = process.argv.slice(2);
const origin = new URL(base).origin;
const WIDTHS = [360, 768, 1024, 1440];
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const PAGES = [
    ['/', 200], ['/locations', 200], ['/locations/noida', 200], ['/locations/noida?workspace=hot-desks', 200],
    ['/locations/gurugram', 200], ['/locations/noida/area/sector-2', 200], ['/locations/noida/a-70', 200],
    ['/locations/noida/a-70/hot-desks', 200], ['/locations/noida/h-105', 200], ['/workspaces', 200], ['/workspaces/hot-desks', 200],
    ['/workspaces/hot-desks/noida', 200], ['/locations/compare', 200],
    ['/locations/compare?locations[]=noida/a-70&locations[]=noida/a-71&locations[]=noida/h-105', 200],
    ['/enquire', 200], ['/book-a-tour', 200], ['/contact', 200], ['/about', 200], ['/test-fixture-sections', 200], ['/test-fixture-media', 200], ['/legal/terms', 200],
    ['/faqs', 200], ['/insights', 200], ['/insights/test-fixture-insight', 200], ['/insights/topic/test-fixture-topic', 200], ['/customers', 200],
    ['/customers/test-fixture-customer-story', 200], ['/no-such-page', 404], ['/pin/old-page', 410],
];

const browser = await chromium.launch(process.env.CHROMIUM_EXECUTABLE ? { executablePath: process.env.CHROMIUM_EXECUTABLE } : {});
const report = { startedAt: new Date().toISOString(), base, chromium: browser.version(), axeCore: axeVersion, axeTags: AXE_TAGS, widths: WIDTHS, threeDFiles: viewerFiles, pages: [], noJs: [], flows: [], keyboard: null, statuses: [], errorPages: [], failures: [] };
const fail = (what) => report.failures.push(what);

async function runAxe(page) {
    await page.evaluate(axeSource);
    const result = await page.evaluate(async (tags) => {
        const r = await window.axe.run(document, { runOnly: { type: 'tag', values: tags } });
        return { violations: r.violations.map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, target: v.nodes[0]?.target?.join(' ') })), incomplete: r.incomplete.map((v) => v.id) };
    }, AXE_TAGS);

    return result;
}

for (const [path, expected] of PAGES) {
    for (const width of WIDTHS) {
        // Reduced motion: axe audits the page as it settles; entrances and scroll reveals pass through
        // partial opacity on purpose (OD-144) and stop entirely under this setting.
        const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
        const page = await context.newPage();
        const external = [];
        const failed = [];
        const consoleErrors = [];
        const threeD = [];
        page.on('request', (r) => { if (!r.url().startsWith(origin) && !r.url().startsWith('data:')) external.push(r.url()); });
        page.on('request', (r) => { if (isThreeDRequest(r.url(), viewerFiles)) threeD.push(r.url()); });
        page.on('requestfailed', (r) => failed.push(`${r.url()} ${r.failure()?.errorText}`));
        page.on('response', (r) => { if (r.status() >= 400 && r.request().resourceType() !== 'document') failed.push(`${r.url()} ${r.status()}`); });
        page.on('console', (m) => { if (m.type() === 'error' && !/the server responded with a status of (404|410)/.test(m.text())) consoleErrors.push(m.text()); });
        const response = await page.goto(base + path, { waitUntil: 'networkidle' });
        const entry = {
            path, width, status: response?.status(),
            overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth),
            h1: await page.locator('h1').count(), external, failed, consoleErrors, threeD,
        };

        if (width === 360 || width === 1440) {
            entry.axe = await runAxe(page);
        }
        if (width === 360) {
            entry.phoneBar = await page.evaluate(() => {
                const bar = [...document.querySelectorAll('div.fixed.inset-x-0.bottom-0')].pop();
                return bar ? [...bar.querySelectorAll('a')].map((a) => `${a.textContent.trim().replace(/\s+/g, ' ')} -> ${a.getAttribute('href')}`) : [];
            });
        }

        if (entry.status !== expected) fail(`${path} @${width}: status ${entry.status}, expected ${expected}`);
        if (entry.overflow > 0) fail(`${path} @${width}: horizontal overflow ${entry.overflow}px`);
        if (entry.h1 !== 1) fail(`${path} @${width}: ${entry.h1} level-1 headings`);
        if (external.length) fail(`${path} @${width}: external requests ${external.join(', ')}`);
        if (threeD.length) fail(`${path} @${width}: 3D requests ${threeD.join(', ')}`);
        if (failed.length) fail(`${path} @${width}: failed requests ${failed.join(', ')}`);
        if (consoleErrors.length) fail(`${path} @${width}: console errors ${consoleErrors.join(' | ')}`);
        if (entry.axe?.violations.length) fail(`${path} @${width}: axe ${entry.axe.violations.map((v) => v.id).join(', ')}`);
        report.pages.push(entry);
        await context.close();
    }

    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false });
    const page = await context.newPage();
    const response = await page.goto(base + path);
    const noJs = { path, status: response?.status(), h1: await page.locator('h1').allInnerTexts(), mainText: (await page.locator('main').innerText()).length, headerLinks: await page.locator('header a[href]').count() };
    if (noJs.h1.length !== 1 || noJs.mainText < 40) fail(`${path} without JavaScript: heading or content missing`);
    report.noJs.push(noJs);
    await context.close();
}

// Without JavaScript, on a phone: the menu, the workspace-type filter, the comparison and its shared address.
{
    const context = await browser.newContext({ viewport: { width: 360, height: 900 }, javaScriptEnabled: false });
    const page = await context.newPage();

    await page.goto(base + '/');
    await page.getByRole('button', { name: 'Menu' }).click();
    const menuLinks = await page.locator('#mobile-menu a:visible').count();
    report.flows.push({ step: 'phone menu opens without JavaScript', visibleLinks: menuLinks });
    if (menuLinks === 0) fail('phone menu shows no links without JavaScript');

    await page.goto(base + '/locations/noida');
    await page.getByRole('link', { name: 'Hot desks', exact: true }).first().click();
    await page.waitForLoadState();
    const filtered = { step: 'filter link without JavaScript', url: new URL(page.url()).pathname + new URL(page.url()).search, status: (await page.locator('[role=status]').innerText()).replace(/\s+/g, ' ').trim() };
    report.flows.push(filtered);
    if (filtered.url !== '/locations/noida?workspace=hot-desks') fail('filter link did not narrow the city page');

    await page.goto(base + '/locations/compare');
    await page.getByLabel(/SmartGro A-70/).check();
    await page.getByLabel(/SmartGro H-105/).check();
    await page.getByRole('button', { name: 'Compare' }).click();
    await page.waitForLoadState();
    const shared = page.url();
    const columns = await page.locator('thead th').allInnerTexts();
    report.flows.push({ step: 'comparison form without JavaScript', query: decodeURIComponent(new URL(shared).search), columns });
    if (columns.length !== 2) fail('comparison form did not compare two locations');

    const again = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const reopened = await again.newPage();
    await reopened.goto(shared);
    const reopenedColumns = await reopened.locator('thead th').allInnerTexts();
    report.flows.push({ step: 'shared comparison address reopened', columns: reopenedColumns });
    if (reopenedColumns.join() !== columns.join()) fail('shared comparison address did not reopen the same comparison');
    await again.close();
    await context.close();
}

// Keyboard on a phone: every focus stop shows a focus indicator and, once the (smooth) scroll has settled,
// is not hidden behind the phone bar (WCAG 2.2, 2.4.11: fully hidden fails; partly hidden is recorded).
{
    const context = await browser.newContext({ viewport: { width: 360, height: 740 } });
    const page = await context.newPage();
    await page.goto(base + '/locations/noida/a-70', { waitUntil: 'networkidle' });
    const stops = [];
    for (let i = 0; i < 45; i++) {
        await page.keyboard.press('Tab');
        await page.waitForFunction(() => new Promise((resolve) => {
            let last = scrollY;
            let still = 0;
            const tick = () => {
                still = scrollY === last ? still + 1 : 0;
                last = scrollY;
                still >= 5 ? resolve(true) : requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }));
        const stop = await page.evaluate(() => {
            const el = document.activeElement;
            if (!el || el === document.body || el === document.documentElement) return null;
            const style = getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            const bar = [...document.querySelectorAll('div.fixed.inset-x-0.bottom-0')].pop();
            const inBar = bar ? bar.contains(el) : false;
            const barTop = bar && !inBar ? bar.getBoundingClientRect().top : Infinity;
            return {
                label: (el.getAttribute('aria-label') || el.textContent || el.getAttribute('href') || el.tagName).trim().replace(/\s+/g, ' ').slice(0, 50),
                indicator: (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0) || style.boxShadow !== 'none',
                fullyHidden: rect.top >= barTop,
                partlyHidden: rect.bottom > barTop && rect.top < barTop,
                inBar,
            };
        });
        if (stop) stops.push(stop);
    }
    const noIndicator = stops.filter((s) => !s.indicator);
    const hidden = stops.filter((s) => s.fullyHidden);
    report.keyboard = {
        page: '/locations/noida/a-70', width: 360, height: 740, stops: stops.length, firstStop: stops[0]?.label, reachedBar: stops.some((s) => s.inBar),
        withoutIndicator: noIndicator.map((s) => s.label), fullyHiddenBehindBar: hidden.map((s) => s.label), partlyHiddenBehindBar: stops.filter((s) => s.partlyHidden).map((s) => s.label),
    };
    if (noIndicator.length) fail(`keyboard: no focus indicator on ${noIndicator.map((s) => s.label).join(', ')}`);
    if (hidden.length) fail(`keyboard: focus hidden behind the phone bar on ${hidden.map((s) => s.label).join(', ')}`);
    if (!report.keyboard.reachedBar) fail('keyboard: the phone bar was not reached');
    await context.close();
}

// Real answers: unknown address, removed address, and a form post without a CSRF token.
{
    const context = await browser.newContext();
    for (const [method, path, expected, title] of [['GET', '/no-such-page?token=fixture-secret', 404, 'Page not found'], ['GET', '/pin/old-page', 410, 'This page has been removed'], ['POST', '/enquire', 419, 'This page has expired'], ['POST', '/book-a-tour', 419, 'This page has expired']]) {
        const response = method === 'GET' ? await context.request.get(base + path) : await context.request.post(base + path, { form: { name: 'x' }, maxRedirects: 0 });
        const body = await response.text();
        const entry = { method, path, status: response.status(), branded: body.includes(`${title}</h1>`), noindex: body.includes('noindex, nofollow'), echoesQuery: body.includes('fixture-secret') };
        report.statuses.push(entry);
        if (entry.status !== expected || !entry.branded || !entry.noindex || entry.echoesQuery) fail(`${method} ${path}: ${JSON.stringify(entry)}`);
    }
    await context.close();
}

// Search engines (FR-SEO-3, OD-130): robots.txt and the sitemap as served, then every address the
// sitemap lists, fetched as a crawler would: each must answer 200, give exactly that address as its
// canonical URL and not say "noindex". This server is not production, so robots.txt must forbid crawling
// and every answer must carry "noindex" in its X-Robots-Tag header. Addresses that must never be listed
// are checked too.
{
    const context = await browser.newContext();
    const robots = await context.request.get(`${base}/robots.txt`);
    const sitemap = await context.request.get(`${base}/sitemap.xml`);
    const xml = await sitemap.text();
    const locs = [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1].replaceAll('&amp;', '&'));
    report.seo = {
        robots: { status: robots.status(), body: await robots.text(), xRobotsTag: robots.headers()['x-robots-tag'] ?? null },
        sitemap: { status: sitemap.status(), type: sitemap.headers()['content-type'], urls: locs.length, wellFormed: /^<\?xml version="1\.0" encoding="UTF-8"\?>\s*<urlset xmlns="http:\/\/www\.sitemaps\.org\/schemas\/sitemap\/0\.9">/.test(xml) && xml.trim().endsWith('</urlset>') },
        listed: [],
        neverListed: ['/locations/compare', '/enquire/received', '/book-a-tour/requested', '/design', '/test-fixture-draft', '/admin', '/admin/login'].filter((path) => locs.includes(base + path)),
    };
    for (const loc of locs) {
        // An address on another website is reported below and never fetched.
        if (!loc.startsWith(base)) continue;
        const response = await context.request.get(loc, { maxRedirects: 0 });
        const html = await response.text();
        const canonical = html.match(/<link rel="canonical" href="([^"]+)">/)?.[1]?.replaceAll('&amp;', '&') ?? null;
        // Structured data (FR-SEO-2, OD-131): one JSON-LD script with one schema.org graph, no type twice,
        // the breadcrumb's last step this address, and no rating, review, offer, price or availability.
        const scripts = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map((m) => m[1]);
        let graph = null;
        try { graph = JSON.parse(scripts[0] ?? 'null')?.['@graph'] ?? null; } catch { graph = null; }
        const types = (graph ?? []).map((node) => node['@type']);
        const breadcrumb = (graph ?? []).find((node) => node['@type'] === 'BreadcrumbList');
        const forbidden = /"(aggregateRating|review|reviews|offers|price|priceRange|availability|rating)"\s*:/.test(scripts[0] ?? '');
        const entry = {
            path: loc.slice(base.length) || '/', status: response.status(), canonical: canonical === loc, noindexMeta: /<meta name="robots" content="noindex/.test(html), xRobotsTag: response.headers()['x-robots-tag'] ?? null,
            jsonLd: { scripts: scripts.length, parsed: Array.isArray(graph), types, uniqueTypes: new Set(types).size === types.length, breadcrumbEndsHere: breadcrumb ? breadcrumb.itemListElement.at(-1).item === loc : null, forbidden },
        };
        report.seo.listed.push(entry);
        if (entry.status !== 200 || !entry.canonical || entry.noindexMeta) fail(`sitemap address ${entry.path}: ${JSON.stringify({ ...entry, canonicalFound: canonical })}`);
        if (entry.jsonLd.scripts !== 1 || !entry.jsonLd.parsed || !entry.jsonLd.uniqueTypes || entry.jsonLd.breadcrumbEndsHere === false || forbidden || !types.includes('Organization')) fail(`structured data on ${entry.path}: ${JSON.stringify(entry.jsonLd)}`);
        if (entry.xRobotsTag !== 'noindex, nofollow') fail(`sitemap address ${entry.path}: no "noindex" header outside production`);
    }
    if (report.seo.robots.status !== 200 || report.seo.robots.body !== 'User-agent: *\nDisallow: /\n' || report.seo.robots.body.includes('admin')) fail(`robots.txt: ${JSON.stringify(report.seo.robots)}`);
    if (report.seo.sitemap.status !== 200 || !/^application\/xml/.test(report.seo.sitemap.type ?? '') || !report.seo.sitemap.wellFormed || locs.length === 0) fail(`sitemap.xml: ${JSON.stringify(report.seo.sitemap)}`);
    if (new Set(locs).size !== locs.length) fail('sitemap.xml: an address is listed twice');
    if (locs.some((loc) => !loc.startsWith(base))) fail('sitemap.xml: an address is not on this website');
    if (report.seo.neverListed.length) fail(`sitemap.xml lists private or unpublished addresses: ${report.seo.neverListed.join(', ')}`);
    await context.close();
}

// The rendered error pages, served on this origin so their styles load.
if (errorPagesDir) {
    for (const status of [403, 419, 429, 500, 503]) {
        const html = fs.readFileSync(`${errorPagesDir}/error-${status}.html`, 'utf8');
        for (const width of [360, 1440]) {
            const page = await (await browser.newContext({ viewport: { width, height: 900 } })).newPage();
            await page.route(base + '/__rendered-error', (route) => route.fulfill({ status, contentType: 'text/html', body: html }));
            await page.goto(base + '/__rendered-error', { waitUntil: 'networkidle' });
            const entry = { status, width, h1: await page.locator('h1').allInnerTexts(), noindex: await page.locator('meta[name=robots][content="noindex, nofollow"]').count(), overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), axe: (await runAxe(page)).violations };
            report.errorPages.push(entry);
            if (entry.h1.length !== 1 || !entry.noindex || entry.overflow > 0 || entry.axe.length) fail(`rendered ${status} @${width}: ${JSON.stringify(entry)}`);
            await page.close();
        }
    }
}

await browser.close();
report.finishedAt = new Date().toISOString();
fs.writeFileSync(`${outDir}/public.json`, JSON.stringify(report, null, 1));

const loads = report.pages.length;
console.log(JSON.stringify({
    chromium: report.chromium, axeCore: report.axeCore, addresses: PAGES.length, pageLoads: loads,
    axeRuns: report.pages.filter((p) => p.axe).length + report.errorPages.length,
    axeViolations: report.pages.reduce((n, p) => n + (p.axe?.violations.length ?? 0), 0) + report.errorPages.reduce((n, p) => n + p.axe.length, 0),
    noJsPages: report.noJs.length, flows: report.flows.length, keyboardStops: report.keyboard.stops, statuses: report.statuses.length,
    threeDRequests: report.pages.reduce((n, p) => n + p.threeD.length, 0),
    sitemapUrls: report.seo?.sitemap.urls, sitemapChecked: report.seo?.listed.length,
    jsonLdTypes: [...new Set((report.seo?.listed ?? []).flatMap((entry) => entry.jsonLd.types))],
    failures: report.failures,
}, null, 1));
process.exit(report.failures.length ? 1 : 0);
