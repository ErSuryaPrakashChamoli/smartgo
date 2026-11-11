import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
import { readFileSync } from 'node:fs';
const [base, out] = process.argv.slice(2);
const axe = readFileSync('/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/axe-core/axe.min.js', 'utf8');
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const report = [];
for (const [name, width, height] of [['1440', 1440, 900], ['1280', 1280, 800], ['1024', 1024, 768], ['768', 768, 1024], ['390', 390, 844], ['360', 360, 740]]) {
  const page = await browser.newPage({ viewport: { width, height } });
  const errors = [];
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('response', (r) => r.status() >= 400 && errors.push(`${r.status()} ${r.url()}`));
  await page.goto(base + '/', { waitUntil: 'networkidle' });
  await page.mouse.move(width - 5, height - 5);
  await page.waitForTimeout(1200);
  await page.screenshot({ path: `${out}/home-${name}.png` });
  const facts = await page.evaluate(() => ({
    overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    hero: Math.round(document.querySelector('[data-carousel]')?.getBoundingClientRect().height ?? -1),
    slides: document.querySelectorAll('[data-carousel-slide]').length,
    firstImageLoaded: (() => { const i = document.querySelector('[data-carousel-slide] img'); return i ? i.complete && i.naturalWidth > 0 : false; })(),
    firstImageSrc: document.querySelector('[data-carousel-slide] img')?.currentSrc,
    labels: [...document.querySelectorAll('[data-carousel-slide] figcaption')].map((f) => f.textContent.trim()),
    h1: document.querySelector('h1')?.textContent.trim(),
    marquee: !!document.querySelector('[data-marquee]'),
    example: document.querySelector('[data-marquee]')?.textContent.includes('Example highlights'),
    mainNavPad: getComputedStyle(document.querySelector('nav[aria-label="Main"] ul') ?? document.body).backgroundColor,
  }));
  let axeResult = null;
  if (['1440', '390'].includes(name)) {
    await page.addScriptTag({ content: axe });
    axeResult = await page.evaluate(async () => (await axe.run(document, { resultTypes: ['violations'] })).violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.length} — ${v.nodes.slice(0, 3).map((n) => n.target.join(' ')).join(' | ')}`));
  }
  if (name === '1440') {
    // Every slide's image loads once shown.
    const loaded = [];
    for (let i = 0; i < facts.slides; i++) {
      if (i > 0) { await page.click('[data-carousel-next]'); await page.waitForTimeout(1300); }
      loaded.push(await page.evaluate((index) => { const img = document.querySelectorAll('[data-carousel-slide] img')[index]; return img.complete && img.naturalWidth; }, i));
      await page.screenshot({ path: `${out}/home-1440-slide${i + 1}.png` });
    }
    facts.slideImageWidths = loaded;
  }
  report.push({ name, ...facts, axe: axeResult, errors });
  await page.close();
}
console.log(JSON.stringify(report, null, 1));
await browser.close();
