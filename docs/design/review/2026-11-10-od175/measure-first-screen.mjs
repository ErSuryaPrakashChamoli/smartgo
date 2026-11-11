import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const [base, shotDir, tag] = process.argv.slice(2);
const sizes = [[1455, 748], [1836, 935], [1536, 730], [1440, 790], [1366, 657], [1280, 720], [1024, 768], [768, 1024], [390, 844]];
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
for (const [w, h] of sizes) {
  const ctx = await browser.newContext({ viewport: { width: w, height: h }, reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  const errors = []; page.on('console', m => m.type() === 'error' && errors.push(m.text())); page.on('pageerror', e => errors.push(String(e)));
  const res = await page.goto(base + '/', { waitUntil: 'networkidle' });
  const m = await page.evaluate(() => {
    const top = (el) => Math.round(el.getBoundingClientRect().top + scrollY), bot = (el) => Math.round(el.getBoundingClientRect().bottom + scrollY);
    const r = (el) => el ? top(el) + '→' + bot(el) : null;
    const form = document.querySelector('#find'); const section = form?.closest('section');
    const slides = [...document.querySelectorAll('[data-carousel-slide]')].map(s => {
      const col = s.querySelector('.lg\\:col-span-6') ?? s.firstElementChild; const hd = s.querySelector('h1,h2');
      return { content: Math.round(col.getBoundingClientRect().height), headLines: Math.round(hd.getBoundingClientRect().height / parseFloat(getComputedStyle(hd).lineHeight)), font: getComputedStyle(hd).fontSize };
    });
    return { header: r(document.querySelector('header')), carousel: r(document.querySelector('[data-carousel]')), highlights: r(document.querySelector('[data-marquee]')),
      finder: r(section), form: r(form), note: r(section?.querySelector('p.text-sm')), heading: r(section?.querySelector('h2')), slides,
      overflowX: document.documentElement.scrollWidth > innerWidth };
  });
  const fb = m.form ? +m.form.split('→')[1] : 1e9, nb = m.note ? +m.note.split('→')[1] : 1e9;
  console.log(`${w}x${h} status=${res.status()} formVisible=${fb <= h} noteVisible=${nb <= h} errors=${errors.length}`, JSON.stringify(m));
  if (shotDir) { await page.screenshot({ path: `${shotDir}/${tag}-${w}x${h}.png` }); }
  await ctx.close();
}
await browser.close();
