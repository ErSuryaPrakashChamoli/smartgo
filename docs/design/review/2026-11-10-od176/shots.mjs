import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const base = 'http://127.0.0.1:8771';
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const out = [];
for (const [w, h] of [[1455, 748], [390, 844]]) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  const errors = []; page.on('console', m => m.type() === 'error' && errors.push(m.text())); page.on('requestfailed', r => errors.push('failed ' + r.url()));
  for (const [name, path] of [['home', '/'], ['location', '/locations/noida/a-70'], ['workspace', '/locations/noida/a-70/private-cabins'], ['privacy', '/legal/privacy'], ['enquire', '/enquire'], ['tour', '/book-a-tour'], ['customer', '/customers/example-design-studio'], ['type', '/workspaces/hot-desks']]) {
    const res = await page.goto(base + path, { waitUntil: 'networkidle' });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
    const brokenImages = await page.evaluate(() => [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.loading !== 'lazy').map(i => i.src));
    out.push({ w, name, status: res.status(), overflow, brokenImages: brokenImages.length });
    if (w === 1455 || ['home', 'location'].includes(name)) { await page.screenshot({ path: `${name}-${w}.png`, fullPage: name !== 'home' }); }
  }
  out.push({ w, errors });
  await ctx.close();
}
console.log(JSON.stringify(out));
await browser.close();
