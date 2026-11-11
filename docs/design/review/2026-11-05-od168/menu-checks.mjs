// OD-168: open each main-menu drop-down on a running site, at desktop width and in the phone menu, and record
// what each shows. Usage: node menus.mjs <base url> <screenshot folder>
import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
import { mkdirSync, writeFileSync } from 'node:fs';

const [base, shots] = process.argv.slice(2);
mkdirSync(shots, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_EXECUTABLE || undefined });
const report = { base, desktop: [], phone: [], consoleErrors: [], failedRequests: [], external: [] };

const watch = (page) => {
    page.on('console', (m) => m.type() === 'error' && report.consoleErrors.push(m.text()));
    page.on('requestfailed', (r) => report.failedRequests.push(r.url()));
    page.on('request', (r) => !r.url().startsWith(base) && !r.url().startsWith('data:') && report.external.push(r.url()));
};

// Desktop: each drop-down button in the main navigation.
for (const width of [1440, 1024]) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    watch(page);
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    const buttons = page.locator('nav[aria-label="Main"].hidden button[popovertarget]');
    const count = await buttons.count();
    for (let i = 0; i < count; i++) {
        const button = buttons.nth(i);
        const label = (await button.innerText()).trim();
        await button.click();
        const panel = page.locator('#' + (await button.getAttribute('popovertarget')));
        await panel.waitFor({ state: 'visible' });
        const links = await panel.locator('a').evaluateAll((as) => as.map((a) => ({
            text: a.innerText.replace(/\s+/g, ' ').trim(), href: a.getAttribute('href'), icon: !!a.querySelector('svg'),
            described: a.hasAttribute('aria-describedby'), box: (({ width, height }) => ({ width: Math.round(width), height: Math.round(height) }))(a.getBoundingClientRect()),
        })));
        const box = await panel.boundingBox();
        const sampleNote = await panel.getByText('Sample pages: example text').count();
        report.desktop.push({ width, label, panelWidth: Math.round(box.width), inViewport: box.x >= 0 && box.x + box.width <= width, links, sampleNote });
        await page.screenshot({ path: `${shots}/menu-${label.toLowerCase()}-${width}.png` });
        await page.keyboard.press('Escape');
    }
    report.desktop.push({ width, overflow: await page.evaluate(() => document.documentElement.scrollWidth - innerWidth) });
    await context.close();
}

// Phone: the Menu button, then every group opened.
const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce', isMobile: true, hasTouch: true });
const page = await context.newPage();
watch(page);
await page.goto(base + '/', { waitUntil: 'networkidle' });
await page.getByRole('button', { name: 'Menu' }).click();
const sheet = page.locator('#mobile-menu');
await sheet.waitFor({ state: 'visible' });
const summaries = sheet.locator('nav > details > summary');
for (let i = 0; i < await summaries.count(); i++) {
    const summary = summaries.nth(i);
    const label = (await summary.innerText()).trim();
    await summary.click();
    const group = sheet.locator('nav > details').nth(i);
    report.phone.push({ label, links: await group.locator(':scope > ul > li > a').evaluateAll((as) => as.map((a) => a.innerText.replace(/\s+/g, ' ').trim())), sampleNote: await group.locator(':scope > p').count() });
    await group.screenshot({ path: `${shots}/phone-${label.toLowerCase()}-390.png` });
    await summary.click();
}
report.phoneOverflow = await page.evaluate(() => document.documentElement.scrollWidth - innerWidth);
await context.close();
await browser.close();

writeFileSync(`${shots}/menus.json`, JSON.stringify(report, null, 1));
console.log(JSON.stringify({ desktopPanels: report.desktop.filter((d) => d.label).map((d) => `${d.width} ${d.label}: ${d.links.length} links, ${d.panelWidth}px, inViewport=${d.inViewport}, note=${d.sampleNote}`), phone: report.phone.map((p) => `${p.label}: ${p.links.length} direct links, note=${p.sampleNote}`), consoleErrors: report.consoleErrors.length, failed: report.failedRequests.length, external: report.external.length, phoneOverflow: report.phoneOverflow }, null, 1));
