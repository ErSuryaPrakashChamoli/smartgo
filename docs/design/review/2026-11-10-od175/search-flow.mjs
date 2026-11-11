import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const page = await (await browser.newContext({ viewport: { width: 1455, height: 748 }, reducedMotion: 'reduce' })).newPage();
for (const choice of ['hot-desks', 'virtual-office', '']) {
  await page.goto('http://127.0.0.1:8767/', { waitUntil: 'networkidle' });
  await page.selectOption('#finder-workspace', choice);
  await Promise.all([page.waitForURL(u => !u.toString().endsWith('/') ), page.click('#find button[type=submit]')]);
  await page.waitForLoadState('networkidle');
  const info = await page.evaluate(() => {
    const t = location.hash ? document.getElementById(location.hash.slice(1)) : null; const card = t?.querySelector('article');
    const header = document.querySelector('header').getBoundingClientRect().bottom;
    return { url: location.pathname + location.search + location.hash, title: document.title,
      target: t ? { top: Math.round(t.getBoundingClientRect().top), headerBottom: Math.round(header), ring: card && getComputedStyle(card).boxShadow !== 'none' } : null };
  });
  console.log(JSON.stringify({ choice, ...info }));
  if (choice === 'hot-desks') { await page.screenshot({ path: 'flow-hot-desks.png' }); }
}
await browser.close();
