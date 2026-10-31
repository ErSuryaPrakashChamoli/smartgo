// OD-150 browser checks for the Solutions menu (13 offerings), its destinations, the 13 sample pages and the
// home page, on one server. node menu-checks.mjs <base> <screenshot dir>  → JSON report on stdout
import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
import fs from 'node:fs';

const [base, shots] = process.argv.slice(2);
fs.mkdirSync(shots, { recursive: true });
const axeSource = fs.readFileSync('/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/axe-core/axe.min.js', 'utf8');
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const report = { base, desktop: [], phone: [], pages: [], sitemap: null, home: [], failures: [] };
const fail = (message) => report.failures.push(message);
const tag = new URL(base).port;

// The menu as the sample content sets it on the demo and the site, where dedicated desks, hot desks and private
// offices have published offerings (OD-150 decision 2): label → destination, in order.
const slug = (label) => label.toLowerCase().replace(/ /g, '-');
const EXPECTED = {
    Workspaces: [['Dedicated Desks', '/workspaces/dedicated-desks'], ['Flexible Desks', '/workspaces/hot-desks'], ['Private Offices', '/workspaces/private-cabins'], ['Managed Workspaces', '/workspace-solutions/managed-workspaces']],
    'Business Solutions': ['Meeting Rooms', 'Day Pass', 'Business Address', 'GST Registration Support', 'Company Registration Support', 'Workspace Packages', 'Cabin Day Pass', 'Training Rooms', 'Virtual Office']
        .map((label) => [label, `/business-solutions/${slug(label)}`]),
};
const VISITABLE = ['Dedicated Desks', 'Flexible Desks', 'Private Offices', 'Managed Workspaces', 'Meeting Rooms', 'Day Pass', 'Workspace Packages', 'Cabin Day Pass', 'Training Rooms'];
const SAMPLE_PAGES = [
    ...['Dedicated Desks', 'Flexible Desks', 'Private Offices', 'Managed Workspaces'].map((label) => [label, `/workspace-solutions/${slug(label)}`]),
    ...EXPECTED['Business Solutions'],
];
const sameList = (links, expected) => JSON.stringify(links.map((l) => [l.label, l.href])) === JSON.stringify(expected);

async function axe(page, include) {
    await page.evaluate(axeSource);
    return page.evaluate(async ([tags, selector]) => {
        const r = await window.axe.run(selector ? { include: [selector] } : document, { runOnly: { type: 'tag', values: tags } });
        return r.violations.map((v) => `${v.id} (${v.nodes.length}) ${v.nodes[0]?.target?.join(' ')}`);
    }, [AXE_TAGS, include]);
}

const paneState = (page) => page.evaluate(() => {
    const panel = document.querySelector('[popover]:popover-open');
    if (!panel) return null;
    const pane = [...panel.querySelectorAll('[data-flyout-pane]')].find((x) => !x.hidden);
    const rect = panel.getBoundingClientRect();
    return {
        tabs: [...panel.querySelectorAll('[data-flyout-tab]')].map((t) => `${t.firstChild ? t.innerText.split('\n')[0].trim() : ''}:${t.getAttribute('aria-expanded')}`),
        links: [...pane.querySelectorAll('a')].map((a) => ({ label: a.innerText.split('\n')[0].trim(), href: a.getAttribute('href'), icon: a.querySelector('svg') !== null, description: a.getAttribute('aria-describedby') ? document.getElementById(a.getAttribute('aria-describedby'))?.textContent.trim() : null })),
        note: pane.querySelector('p')?.textContent.trim().replace(/\s+/g, ' ') ?? null,
        panel: { left: Math.round(rect.left), right: Math.round(rect.right), top: Math.round(rect.top), bottom: Math.round(rect.bottom), width: Math.round(rect.width), scrolls: panel.scrollHeight > panel.clientHeight },
        viewport: { width: innerWidth, height: innerHeight },
        overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    };
});

const allHrefs = new Set();

for (const [width, height] of [[1440, 900], [1280, 720], [1024, 768]]) {
    const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    const solutions = page.locator('nav.hidden[aria-label="Main"]').getByRole('button', { name: 'Solutions', exact: true });
    const entry = { width, height };

    await solutions.click();
    await page.waitForTimeout(250);
    entry.workspaces = await paneState(page);
    entry.axeOpenPanel = await axe(page, '[popover]:popover-open');
    await page.screenshot({ path: `${shots}/menu-workspaces-${width}-${tag}.png` });

    await page.locator('[popover]:popover-open [data-flyout-tab]').nth(1).hover();
    await page.waitForTimeout(350);
    entry.businessByHover = await paneState(page);
    await page.screenshot({ path: `${shots}/menu-business-${width}-${tag}.png` });

    await page.locator('[popover]:popover-open [data-flyout-tab]').nth(0).click();
    await page.waitForTimeout(100);
    entry.workspacesByClick = (await paneState(page))?.tabs;
    entry.namesExcludeDescriptions = await page.getByRole('link', { name: 'Dedicated Desks', exact: true }).count();

    await page.keyboard.press('Escape');
    await page.waitForTimeout(100);
    entry.escape = await page.evaluate(() => ({ open: !!document.querySelector('[popover]:popover-open'), focus: document.activeElement?.innerText?.trim() }));

    await solutions.click();
    await page.waitForTimeout(150);
    await page.mouse.click(width - 20, height - 20);
    await page.waitForTimeout(150);
    entry.outsideClickCloses = !(await page.evaluate(() => !!document.querySelector('[popover]:popover-open')));

    await solutions.focus();
    await page.keyboard.press('Enter');
    await page.waitForTimeout(150);
    await page.keyboard.press('Tab');
    const firstFocus = await page.evaluate(() => document.activeElement?.innerText.split('\n')[0].trim());
    await page.keyboard.press('ArrowDown');
    const afterArrow = await page.evaluate(() => document.activeElement?.innerText.split('\n')[0].trim());
    await page.keyboard.press('Enter');
    await page.waitForTimeout(100);
    entry.keyboard = { firstFocus, afterArrow, afterEnter: await page.evaluate(() => document.activeElement?.getAttribute('href')), tabs: (await paneState(page))?.tabs };
    await page.keyboard.press('Escape');

    entry.consoleErrors = errors;
    report.desktop.push(entry);

    for (const pane of [entry.workspaces, entry.businessByHover]) pane?.links.forEach((link) => allHrefs.add(`${link.href}|${link.label}`));
    if (!sameList(entry.workspaces?.links ?? [], EXPECTED.Workspaces)) fail(`${width}: Workspaces ${JSON.stringify(entry.workspaces?.links.map((l) => [l.label, l.href]))}`);
    if (!sameList(entry.businessByHover?.links ?? [], EXPECTED['Business Solutions'])) fail(`${width}: Business Solutions ${JSON.stringify(entry.businessByHover?.links.map((l) => [l.label, l.href]))}`);
    if (JSON.stringify(entry.workspaces?.tabs.map((t) => t.split(':')[0])) !== JSON.stringify(['Workspaces', 'Business Solutions'])) fail(`${width}: categories ${entry.workspaces?.tabs}`);
    if (!entry.businessByHover?.tabs[1]?.endsWith(':true')) fail(`${width}: hover did not switch category`);
    if (!entry.workspacesByClick?.[0]?.endsWith(':true')) fail(`${width}: click did not switch category`);
    if (entry.namesExcludeDescriptions !== 1) fail(`${width}: link name includes its description`);
    if (entry.escape.open || entry.escape.focus !== 'Solutions') fail(`${width}: Escape ${JSON.stringify(entry.escape)}`);
    if (!entry.outsideClickCloses) fail(`${width}: outside click left the menu open`);
    if (entry.keyboard.afterEnter !== entry.businessByHover?.links[0]?.href) fail(`${width}: keyboard ${JSON.stringify(entry.keyboard)}`);
    for (const pane of [entry.workspaces, entry.businessByHover]) {
        if (pane.panel.left < 0 || pane.panel.right > width || pane.panel.bottom > height) fail(`${width}: panel outside the viewport ${JSON.stringify(pane.panel)}`);
        if (pane.overflow > 0) fail(`${width}: page overflows by ${pane.overflow}px`);
        if (pane.links.some((link) => !link.description || !link.icon)) fail(`${width}: a link has no description or icon`);
        if (!pane.note?.startsWith('Sample pages')) fail(`${width}: no sample note`);
    }
    if (entry.axeOpenPanel.length) fail(`${width}: axe on open panel ${entry.axeOpenPanel.join('; ')}`);
    if (errors.length) fail(`${width}: console ${errors.join(' | ')}`);
    await context.close();
}

for (const [width, height] of [[768, 1024], [390, 844], [360, 740]]) {
    const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce', hasTouch: width < 768, isMobile: width < 768 });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Menu', exact: true }).click();
    const sheet = page.locator('#mobile-menu');
    await sheet.locator('summary', { hasText: 'Solutions' }).first().click();
    await sheet.locator('summary', { hasText: 'Workspaces' }).click();
    await sheet.locator('summary', { hasText: 'Business Solutions' }).click();
    await page.waitForTimeout(200);
    const state = await page.evaluate(() => {
        const sheet = document.getElementById('mobile-menu');
        const lists = [...sheet.querySelectorAll('details details')].map((d) => ({
            category: d.querySelector('summary').innerText.split('\n')[0].trim(),
            open: d.open,
            links: [...d.querySelectorAll('a')].map((a) => ({ label: a.innerText.split('\n')[0].trim(), href: a.getAttribute('href'), height: Math.round(a.getBoundingClientRect().height), visible: a.getBoundingClientRect().width > 0 })),
            note: d.querySelector('p')?.textContent.trim().replace(/\s+/g, ' ') ?? null,
        }));
        return { lists, sheetOverflow: sheet.scrollWidth - sheet.clientWidth, pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
    });
    const axeSheet = await axe(page, '#mobile-menu');
    await sheet.screenshot({ path: `${shots}/phone-menu-${width}-${tag}.png` });
    await sheet.getByRole('button', { name: 'Close' }).click();
    await page.waitForTimeout(150);
    const closed = !(await page.evaluate(() => document.getElementById('mobile-menu').matches(':popover-open')));
    report.phone.push({ width, ...state, axeSheet, closed, consoleErrors: errors });
    const links = state.lists.flatMap((l) => l.links);
    if (links.length !== 13 || links.some((l) => !l.visible)) fail(`phone ${width}: ${links.length} links, hidden ${links.filter((l) => !l.visible).length}`);
    if (!sameList(state.lists[0]?.links ?? [], EXPECTED.Workspaces) || !sameList(state.lists[1]?.links ?? [], EXPECTED['Business Solutions'])) fail(`phone ${width}: lists differ from the expected menu`);
    if (links.some((l) => l.height < 44)) fail(`phone ${width}: a link is shorter than 44 px`);
    if (state.sheetOverflow > 0 || state.pageOverflow > 0) fail(`phone ${width}: overflow ${state.sheetOverflow}/${state.pageOverflow}`);
    if (state.lists.some((l) => !l.note?.startsWith('Sample pages'))) fail(`phone ${width}: missing sample note`);
    if (axeSheet.length) fail(`phone ${width}: axe ${axeSheet.join('; ')}`);
    if (!closed) fail(`phone ${width}: Close did not close the menu`);
    if (errors.length) fail(`phone ${width}: console ${errors.join(' | ')}`);
    await context.close();
}

// Every destination answers and its links answer. The 13 sample pages (the ten the menu links to and the three
// Workspaces pages kept for the CMS) carry the sample notice and noindex, show no price, and have every section.
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
const page = await context.newPage();
const pageErrors = [];
page.on('console', (m) => { if (m.type() === 'error') pageErrors.push(`${page.url()}: ${m.text()}`); });
const checkedLinks = new Map();
const destinations = new Set([...allHrefs, ...SAMPLE_PAGES.map(([label, href]) => `${href}|${label}`)]);
for (const entry of destinations) {
    const [href, label] = entry.split('|');
    const isSample = SAMPLE_PAGES.some(([, sampleHref]) => sampleHref === href);
    const response = await page.goto(base + href, { waitUntil: 'networkidle' });
    const info = await page.evaluate(() => ({
        h1: document.querySelector('h1')?.innerText.trim(),
        headings: [...document.querySelectorAll('main h2')].map((h) => h.innerText.trim()),
        questions: document.querySelectorAll('main details').length,
        heroLink: document.querySelector('main section a')?.getAttribute('href') ?? null,
        bookTour: document.querySelector('main a[href="/book-a-tour"]') !== null,
        robots: document.querySelector('meta[name=robots]')?.content ?? null,
        title: document.title,
        description: document.querySelector('meta[name=description]')?.content ?? null,
        notice: document.querySelector('[role=note]')?.innerText.trim().replace(/\s+/g, ' ') ?? null,
        links: [...document.querySelectorAll('main a[href^="/"]')].map((a) => a.getAttribute('href')),
        fixture: /TEST FIXTURE|fixture\.test|0000 000/.test(document.documentElement.innerHTML),
        price: /₹|\bRs\.?\s?\d|\bINR\b|per month|\/month|% off/i.test(document.querySelector('main')?.innerText ?? ''),
    }));
    for (const link of new Set(info.links)) {
        if (!checkedLinks.has(link)) checkedLinks.set(link, (await context.request.get(base + link, { maxRedirects: 0 })).status());
    }
    const badLinks = [...new Set(info.links)].filter((link) => checkedLinks.get(link) !== 200);
    report.pages.push({ href, label, sample: isSample, status: response.status(), ...info, links: info.links.length, badLinks });
    if (response.status() !== 200) fail(`${href}: ${response.status()}`);
    if (info.price) fail(`${href}: price-like text`);
    if (badLinks.length) fail(`${href}: links ${badLinks.map((l) => `${l}=${checkedLinks.get(l)}`).join(', ')}`);
    if (!isSample) {
        if (!href.startsWith('/workspaces/') || !info.h1) fail(`${href}: unexpected destination, h1 ${info.h1}`);
        continue;
    }
    const sections = [`About ${label}`, 'At a glance', 'Possible uses', 'Before this becomes a SmartGro service', 'Related offerings', `Questions about ${label}`, `Ask SmartGro about ${label}`];
    if (info.h1 !== label) fail(`${href}: h1 ${info.h1}`);
    if (JSON.stringify(info.headings) !== JSON.stringify(sections)) fail(`${href}: sections ${JSON.stringify(info.headings)}`);
    if (info.questions !== 3) fail(`${href}: ${info.questions} questions`);
    if (info.bookTour !== VISITABLE.includes(label)) fail(`${href}: book a tour ${info.bookTour}`);
    if (info.title !== `${label} (sample) · SmartGro` || !info.description) fail(`${href}: title ${info.title} description ${info.description}`);
    if (!info.robots?.includes('noindex')) fail(`${href}: indexable`);
    if (!info.notice?.startsWith('Sample content.')) fail(`${href}: no sample notice`);
    const typePage = EXPECTED.Workspaces.find(([workspace]) => workspace === label)?.[1];
    if (typePage?.startsWith('/workspaces/') && info.heroLink !== typePage) fail(`${href}: hero links to ${info.heroLink}, not ${typePage}`);
}
if (pageErrors.length) fail(`console on landing pages: ${pageErrors.join(' | ')}`);
for (const [name, href] of [['virtual-office', '/business-solutions/virtual-office'], ['dedicated-desks', '/workspace-solutions/dedicated-desks']]) {
    await page.goto(base + href, { waitUntil: 'networkidle' });
    await page.screenshot({ path: `${shots}/landing-${name}-1440-${tag}.png`, fullPage: true });
}

const sitemap = await (await context.request.get(base + '/sitemap.xml')).text();
report.sitemap = { listsSolutionPages: /workspace-solutions|business-solutions/.test(sitemap), urls: (sitemap.match(/<loc>/g) ?? []).length };
if (report.sitemap.listsSolutionPages) fail('sitemap lists a sample solution page');

// Home page regressions: hero image and its label, search, the header button and the other menu links.
for (const [width, height] of [[1440, 900], [390, 844]]) {
    const ctx = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
    const home = await ctx.newPage();
    const errors = [];
    home.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    await home.goto(base + '/', { waitUntil: 'networkidle' });
    const state = await home.evaluate(() => {
        const img = document.querySelector('#home-heading')?.closest('section')?.querySelector('figure img');
        return {
            image: img ? new URL(img.currentSrc).pathname : null,
            loaded: img?.complete && img.naturalWidth > 0,
            label: document.querySelector('#home-heading')?.closest('section')?.querySelector('figcaption')?.innerText.trim(),
            search: document.querySelector('header a[href="/search"]') !== null,
            headerButtons: [...document.querySelectorAll('header .hidden.lg\\:flex a')].map((a) => `${a.innerText.trim()} ${a.getAttribute('href')}`),
            topLinks: [...document.querySelectorAll('nav.hidden[aria-label="Main"] > ul > li > a')].map((a) => a.getAttribute('href')),
            overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
    });
    const statuses = {};
    for (const href of ['/search', ...state.topLinks, ...state.headerButtons.map((b) => b.split(' ').pop())]) statuses[href] = (await ctx.request.get(base + href)).status();
    report.home.push({ width, ...state, statuses, consoleErrors: errors });
    if (!state.loaded || !state.image?.includes('coworking-interior')) fail(`home ${width}: hero image ${state.image}`);
    if (state.label !== 'AI-generated image, not a photo of a SmartGro location') fail(`home ${width}: label ${state.label}`);
    if (Object.values(statuses).some((s) => s !== 200)) fail(`home ${width}: ${JSON.stringify(statuses)}`);
    if (state.overflow > 0) fail(`home ${width}: overflow ${state.overflow}`);
    if (errors.length) fail(`home ${width}: console ${errors.join(' | ')}`);
    await ctx.close();
}

await browser.close();
console.log(JSON.stringify(report, null, 1));
