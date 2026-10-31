// OD-154 empty-state sweep on a replica of smart_go (test database): every back-office screen without a
// parameter, signed in as a Super Admin, and the public pages, at several widths. Read-only apart from the
// sign-in session. node sweep.mjs <base> <routes file> <password file> <screenshots dir>  → JSON on stdout
import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
import fs from 'node:fs';

const [base, routesFile, passwordFile, shots] = process.argv.slice(2);
fs.mkdirSync(shots, { recursive: true });
const adminRoutes = fs.readFileSync(routesFile, 'utf8').split('\n').filter(Boolean);
const password = fs.readFileSync(passwordFile, 'utf8').trim();
const axeSource = fs.readFileSync('/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/axe-core/axe.min.js', 'utf8');
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const report = { base, admin: [], public: [], links: {}, failures: [] };
const fail = (message) => report.failures.push(message);

async function axe(page) {
    await page.evaluate(axeSource);
    return page.evaluate(async () => {
        const r = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
        return r.violations.map((v) => `${v.id} (${v.nodes.length}) ${v.nodes[0]?.target?.join(' ')}`);
    });
}

const state = (page) => page.evaluate(() => ({
    title: document.title,
    h1: document.querySelector('h1')?.innerText.trim() ?? null,
    emptyState: document.querySelector('.fi-ta-empty-state-heading')?.innerText.trim() ?? null,
    emptyDescription: document.querySelector('.fi-ta-empty-state-description')?.innerText.trim() ?? null,
    overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    wideElements: [...document.querySelectorAll('body *')].filter((el) => el.getBoundingClientRect().right > document.documentElement.clientWidth + 1 && getComputedStyle(el).position !== 'fixed').slice(0, 3).map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 2).join('.')}`),
    serverError: /Internal Server Error|Whoops|Symfony\\Component|SQLSTATE/.test(document.body.innerText),
    robots: document.querySelector('meta[name=robots]')?.content ?? null,
}));

// Back office, signed in once per width.
for (const [width, height] of [[1440, 900], [390, 844]]) {
    const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(`${page.url()}: ${m.text()}`); });
    page.on('pageerror', (e) => errors.push(`${page.url()}: ${e.message}`));
    await page.goto(base + '/admin/login', { waitUntil: 'networkidle' });
    await page.locator('input[type=email]').fill('empty-state@example.test');
    await page.locator('input[type=password]').fill(password);
    await page.locator('form button[type=submit]').click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 30000 });
    for (const path of adminRoutes) {
        const response = await page.goto(base + path, { waitUntil: 'networkidle' });
        await page.waitForTimeout(150);
        const s = await state(page);
        const entry = { width, path, status: response.status(), finalPath: new URL(page.url()).pathname, ...s };
        if (width === 1440 && response.status() === 200) {
            entry.axe = await axe(page);
        }
        report.admin.push(entry);
        if (response.status() >= 500 || s.serverError) fail(`admin ${width} ${path}: server error ${response.status()}`);
        if (s.overflow > 0) fail(`admin ${width} ${path}: overflow ${s.overflow}px ${s.wideElements.join(', ')}`);
        if (entry.axe?.length) fail(`admin ${width} ${path}: axe ${entry.axe.join('; ')}`);
        if (width === 1440 && ['/admin/properties/spaces', '/admin/properties/space-types', '/admin/website/pages', '/admin'].includes(path)) {
            await page.screenshot({ path: `${shots}/admin${path.replaceAll('/', '-')}-${width}.png` });
        }
    }
    report.adminConsoleErrors = [...(report.adminConsoleErrors ?? []), ...errors];
    await context.close();
}

// Public pages in the owner's empty state, and every internal link they show.
const publicPaths = ['/', '/workspaces', '/locations', '/insights', '/faqs', '/customers', '/search', '/search?q=desk', '/enquire', '/book-a-tour', '/find-a-workspace', '/no-such-page'];
const found = new Set();
for (const [width, height] of [[1440, 900], [768, 1024], [390, 844]]) {
    const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(`${page.url()}: ${m.text()}`); });
    for (const path of publicPaths) {
        const response = await page.goto(base + path, { waitUntil: 'networkidle' });
        const s = await state(page);
        const links = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => h.startsWith('/') && !h.startsWith('//')));
        links.forEach((l) => found.add(l.split('#')[0] || '/'));
        const entry = { width, path, status: response.status(), ...s, links: links.length };
        if (width !== 768) entry.axe = await axe(page);
        report.public.push(entry);
        if (response.status() >= 500 || s.serverError) fail(`public ${width} ${path}: server error ${response.status()}`);
        if (s.overflow > 0) fail(`public ${width} ${path}: overflow ${s.overflow}px ${s.wideElements.join(', ')}`);
        if (entry.axe?.length) fail(`public ${width} ${path}: axe ${entry.axe.join('; ')}`);
        if (width !== 768 && ['/', '/workspaces', '/locations', '/insights'].includes(path)) {
            await page.screenshot({ path: `${shots}/public${path === '/' ? '-home' : path.replaceAll('/', '-')}-${width}.png`, fullPage: true });
        }
    }
    report.publicConsoleErrors = [...(report.publicConsoleErrors ?? []), ...errors];
    await context.close();
}
const request = (await browser.newContext()).request;
for (const link of [...found].sort()) {
    const status = (await request.get(base + link, { maxRedirects: 0 })).status();
    report.links[link] = status;
    if (status >= 400 && link !== '/no-such-page') fail(`link ${link}: ${status}`);
}
await browser.close();
console.log(JSON.stringify(report, null, 1));
