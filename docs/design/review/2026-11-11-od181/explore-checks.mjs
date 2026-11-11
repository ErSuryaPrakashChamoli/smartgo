import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';

const base = process.argv[2];
const shots = process.argv[3];
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium', args: ['--enable-unsafe-swiftshader', '--use-angle=swiftshader', '--ignore-gpu-blocklist'] });
const report = { failures: [], notes: [] };
const fail = (m) => report.failures.push(m);

for (const [name, viewport] of [['desktop', { width: 1440, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
    const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const consoleErrors = [];
    const failed = [];
    const threeD = [];
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    page.on('pageerror', (e) => consoleErrors.push(String(e)));
    page.on('requestfailed', (r) => failed.push(r.url()));
    page.on('request', (r) => { if (/\.glb|\/3d-models\/|twin-viewer|three\.core|meshopt/.test(r.url())) threeD.push(r.url()); });

    // Home: the experience block links to a location's walkthrough.
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    const homeLink = page.locator('#experience-heading').locator('xpath=ancestor::section').getByRole('link', { name: /in 3D/ });
    const href = await homeLink.getAttribute('href').catch(() => null);
    if (!href || !href.endsWith('#explore')) fail(`${name}: home link missing (${href})`);
    if (await page.getByText('Not available yet').count()) fail(`${name}: home still says Not available yet`);
    await page.locator('#experience-heading').scrollIntoViewIfNeeded();
    await page.locator('#experience-heading').locator('xpath=ancestor::section').screenshot({ path: `${shots}/${name}-home-experience.png` });
    if (threeD.length) fail(`${name}: home requested 3D files: ${threeD.join(', ')}`);

    // Location page: nothing 3D until asked.
    await page.goto(base + href, { waitUntil: 'networkidle' });
    report.notes.push(`${name}: url ${page.url()}`);
    if (threeD.length) fail(`${name}: location page requested 3D before the button: ${threeD.join(', ')}`);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 0) fail(`${name}: horizontal overflow ${overflow}px`);
    const explore = page.locator('#explore');
    await explore.scrollIntoViewIfNeeded();
    await explore.locator('[data-twin-walkthrough]').screenshot({ path: `${shots}/${name}-walkthrough-before.png` });

    const load = page.getByRole('button', { name: 'Explore in 3D' });
    if (!(await load.isVisible())) fail(`${name}: Explore in 3D button not visible`);
    await load.click();
    await page.getByText('Drag to turn the floor').waitFor({ timeout: 60000 }).catch(() => fail(`${name}: viewer did not become usable`));
    await page.locator('[data-twin-stage] canvas').waitFor({ timeout: 10000 }).catch(() => fail(`${name}: no canvas`));
    await page.waitForTimeout(800);
    report.notes.push(`${name}: 3D requests after press: ${threeD.map((u) => u.replace(base, '')).join(', ')}`);
    await explore.locator('[data-twin-walkthrough]').screenshot({ path: `${shots}/${name}-walkthrough-whole.png` });

    const views = await page.locator('[data-twin-camera]').allTextContents();
    report.notes.push(`${name}: views ${views.map((v) => v.trim()).join(' | ')}`);
    const second = page.locator('[data-twin-camera]').nth(1);
    await second.click();
    await page.waitForTimeout(600);
    if ((await second.getAttribute('aria-pressed')) !== 'true') fail(`${name}: view button not pressed`);
    report.notes.push(`${name}: status after view: ${(await page.locator('[data-twin-status]').textContent()).trim()}`);
    await explore.locator('[data-twin-walkthrough]').screenshot({ path: `${shots}/${name}-walkthrough-view.png` });

    // Floor plans: tabs, one panel at a time, keyboard.
    const tabs = page.getByRole('tab');
    if ((await tabs.count()) !== 2) fail(`${name}: expected 2 floor tabs, got ${await tabs.count()}`);
    const panels = page.locator('[data-floor-plan]');
    if (!(await panels.nth(0).isVisible()) || (await panels.nth(1).isVisible())) fail(`${name}: wrong panel visible at start`);
    await tabs.nth(1).click();
    if ((await panels.nth(0).isVisible()) || !(await panels.nth(1).isVisible())) fail(`${name}: tab click did not switch`);
    await tabs.nth(1).press('ArrowLeft');
    const focused = await page.evaluate(() => document.activeElement?.textContent?.trim());
    if (!(await panels.nth(0).isVisible()) || focused !== (await tabs.nth(0).textContent()).trim()) fail(`${name}: ArrowLeft did not move to the first tab (${focused})`);
    const img = panels.nth(0).locator('img');
    await img.scrollIntoViewIfNeeded();
    await page.waitForTimeout(400);
    if (!(await img.evaluate((i) => i.complete && i.naturalWidth > 0))) fail(`${name}: floor plan image did not load`);
    await page.locator('[data-floor-plans]').screenshot({ path: `${shots}/${name}-floor-plans.png` });

    if (consoleErrors.length) fail(`${name}: console errors: ${consoleErrors.join(' || ')}`);
    if (failed.length) fail(`${name}: failed requests: ${failed.join(', ')}`);
    await context.close();
}

await browser.close();
console.log(JSON.stringify(report, null, 2));
process.exit(report.failures.length ? 1 : 0);
