import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';

const out = '/tmp/claude-1000/-home-administrator-Documents-website-smartGrow/ca7ac759-3e82-49da-a28c-4d4b424b87f3/scratchpad/shots177';
const base = 'http://127.0.0.1:8767';
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const problems = [];
const page = await browser.newPage({ viewport: { width: 1440, height: 820 }, reducedMotion: 'reduce' });
page.on('console', (message) => message.type() === 'error' && problems.push(`console: ${message.text()}`));
page.on('response', (response) => response.status() >= 400 && problems.push(`${response.status()}: ${response.url()}`));

await page.goto(base + '/');
await page.locator('nav[aria-label="Main"]').getByRole('button', { name: 'Spaces' }).click();
const panel = page.locator('.nav-group-panel:popover-open');
const tabs = panel.locator('[data-flyout-tab]');
await tabs.first().focus();
const focused = async () => (await page.evaluate(() => document.activeElement?.textContent ?? '')).replace(/\s+/g, ' ').trim();
const steps = [];
for (const key of ['ArrowRight', 'ArrowRight', 'ArrowLeft', 'ArrowLeft', 'ArrowDown']) {
    await page.keyboard.press(key);
    steps.push(`${key} → ${await focused()}`);
}
await page.keyboard.press('Enter');
await page.waitForTimeout(200);
console.log('keys', steps);
console.log('after Enter, open tab:', (await panel.locator('[data-flyout-tab][aria-expanded="true"]').textContent()).replace(/\s+/g, ' ').trim());

await page.goto(base + '/spaces/a-70-cabin-c1');
const image = page.locator('figure img').first();
await image.waitFor();
console.log('photo loaded', await image.evaluate((img) => img.complete && img.naturalWidth > 0), 'label', (await page.locator('figure').first().textContent()).replace(/\s+/g, ' ').trim().slice(0, 80));
console.log('overflow', await page.evaluate(() => document.documentElement.scrollWidth > innerWidth));
await page.screenshot({ path: `${out}/space-page-photo-1440.png` });

const phone = await browser.newPage({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
await phone.goto(base + '/spaces/a-23-meeting-room-m2');
console.log('phone overflow', await phone.evaluate(() => document.documentElement.scrollWidth > innerWidth));
await phone.screenshot({ path: `${out}/space-page-photo-390.png`, fullPage: false });

const sitemap = await (await page.goto(base + '/sitemap.xml')).text();
console.log('sitemap space urls', (sitemap.match(/\/spaces\//g) || []).length);
console.log('problems', problems);
await browser.close();
