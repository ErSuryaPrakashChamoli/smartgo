import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
import fs from 'node:fs';
const [base, passwordFile] = process.argv.slice(2);
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
await page.goto(base + '/admin/login', { waitUntil: 'networkidle' });
await page.locator('input[type=email]').fill('empty-state@example.test');
await page.locator('input[type=password]').fill(fs.readFileSync(passwordFile, 'utf8').trim());
await page.locator('form button[type=submit]').click();
await page.waitForURL((u) => !u.pathname.endsWith('/login'));
const links = new Set();
for (const path of ['/admin', '/admin/properties', '/admin/website', '/admin/sales', '/admin/reports', '/admin/settings']) {
    await page.goto(base + path, { waitUntil: 'networkidle' });
    (await page.evaluate(() => [...document.querySelectorAll('aside a[href], nav a[href], .fi-sidebar a[href], .fi-topbar a[href], [class*="sub-navigation"] a[href]')].map((a) => new URL(a.href).pathname))).forEach((l) => links.add(l));
}
const statuses = {};
for (const l of [...links].filter((l) => l.startsWith('/admin')).sort()) statuses[l] = (await page.goto(base + l, { waitUntil: 'domcontentloaded' })).status();
console.log(JSON.stringify(statuses, null, 1));
await browser.close();
