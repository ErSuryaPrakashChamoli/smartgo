import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';

const out = '/tmp/claude-1000/-home-administrator-Documents-website-smartGrow/ca7ac759-3e82-49da-a28c-4d4b424b87f3/scratchpad/shots177';
const base = 'http://127.0.0.1:8767';
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const problems = [];
const watch = (page, label) => {
    page.on('console', (message) => message.type() === 'error' && problems.push(`${label} console: ${message.text()}`));
    page.on('requestfailed', (request) => problems.push(`${label} failed: ${request.url()}`));
    page.on('response', (response) => response.status() >= 400 && problems.push(`${label} ${response.status()}: ${response.url()}`));
};
const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth > innerWidth);

for (const width of [1440, 1024]) {
    const page = await browser.newPage({ viewport: { width, height: 820 }, reducedMotion: 'reduce' });
    watch(page, `${width}`);
    await page.goto(base + '/');
    await page.locator('nav[aria-label="Main"]').getByRole('button', { name: 'Spaces' }).click();
    await page.waitForTimeout(300);
    const panel = page.locator('.nav-group-panel:popover-open');
    const box = await panel.boundingBox();
    const tabs = await panel.locator('[data-flyout-tab]').allTextContents();
    console.log(width, 'panel', JSON.stringify(box), 'tabs', tabs.map((t) => t.replace(/\s+/g, ' ').trim()), 'overflow', await overflow(page));
    await page.screenshot({ path: `${out}/spaces-${width}-cabins.png` });
    await panel.locator('[data-flyout-tab]', { hasText: 'Meeting rooms' }).click();
    await page.waitForTimeout(200);
    const visible = await panel.locator('[data-flyout-pane]:not([hidden]) a').allTextContents();
    console.log(width, 'meeting rooms pane', visible.length, visible.slice(0, 4).map((t) => t.trim()));
    await page.screenshot({ path: `${out}/spaces-${width}-meeting.png` });
    if (width === 1440) {
        await panel.locator('[data-flyout-pane]:not([hidden]) a', { hasText: 'Meeting room M2' }).first().click();
        await page.waitForLoadState();
        console.log('navigated to', new URL(page.url()).pathname, 'h1', (await page.locator('h1').textContent()).trim(), 'overflow', await overflow(page));
        await page.screenshot({ path: `${out}/space-page-1440.png`, fullPage: true });
    }
    await page.close();
}

const phone = await browser.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
watch(phone, '390');
await phone.goto(base + '/');
await phone.getByRole('button', { name: /menu/i }).first().click();
await phone.waitForTimeout(300);
await phone.locator('#mobile-menu').getByText('Spaces', { exact: true }).click();
await phone.locator('#mobile-menu summary', { hasText: 'Dedicated desks' }).click();
await phone.waitForTimeout(300);
console.log('phone overflow', await overflow(phone));
await phone.screenshot({ path: `${out}/spaces-390.png`, fullPage: false });
await phone.goto(base + '/spaces/a-70-cabin-c1');
console.log('phone space page overflow', await overflow(phone));
await phone.screenshot({ path: `${out}/space-page-390.png`, fullPage: true });

console.log('problems', problems);
await browser.close();
