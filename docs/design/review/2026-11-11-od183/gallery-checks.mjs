import { chromium } from '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';

const base = process.argv[2];
const shots = process.argv[3];
const browser = await chromium.launch({ executablePath: '/snap/bin/chromium' });
const report = { failures: [], notes: [] };
const fail = (m) => report.failures.push(m);
const pages = { location: '/locations/noida/a-70', workspace: '/locations/noida/a-70/private-offices', space: null };

for (const [name, viewport] of [['desktop', { width: 1440, height: 900 }], ['phone', { width: 390, height: 844 }]]) {
    const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('requestfailed', (r) => errors.push('failed ' + r.url()));

    // A workspace page and a space page from the site's own links.
    await page.goto(base + pages.location, { waitUntil: 'networkidle' });
    const workspaceHref = await page.locator('main a[href^="/locations/noida/a-70/"]').first().getAttribute('href');

    for (const [kind, path] of [['location', pages.location], ['workspace', workspaceHref]]) {
        await page.goto(base + path, { waitUntil: 'networkidle' });
        const main = page.locator('[data-photo-main]');
        const thumbs = page.locator('[data-photo-thumb]');
        const count = await thumbs.count();
        report.notes.push(`${name} ${kind} ${path}: ${count} small photos`);
        if (count < 2) { fail(`${name} ${kind}: fewer than 2 small photos`); continue; }
        if ((await page.locator('a:has([data-photo-main]), [data-photo-main] a').count()) > 0) fail(`${name} ${kind}: main photo is a link`);

        const before = await main.getAttribute('src');
        const third = thumbs.last();
        await third.scrollIntoViewIfNeeded();
        await third.click();
        const after = await main.getAttribute('src');
        if (after !== (await third.getAttribute('data-src')) || after === before) fail(`${name} ${kind}: main photo did not switch (${before} -> ${after})`);
        if ((await third.getAttribute('aria-pressed')) !== 'true' || (await thumbs.nth(0).getAttribute('aria-pressed')) !== 'false') fail(`${name} ${kind}: pressed state wrong`);
        if (await page.locator('dialog[open]').count()) fail(`${name} ${kind}: a dialog opened`);
        if (page.url() !== base + path) fail(`${name} ${kind}: navigated to ${page.url()}`);
        await page.waitForTimeout(300);
        await page.locator('[data-photo-gallery]').screenshot({ path: `${shots}/${name}-${kind}-after-thumb.png` });

        // Keyboard: Tab to the second small photo and press Enter.
        await thumbs.nth(1).focus();
        await page.keyboard.press('Enter');
        if ((await main.getAttribute('src')) !== (await thumbs.nth(1).getAttribute('data-src'))) fail(`${name} ${kind}: Enter on a small photo did not switch`);

        // A photo in the "Photos" grid shows itself in the main frame and brings it into view.
        const show = page.locator('[data-photo-show]');
        if (await show.count()) {
            const last = show.last();
            await last.scrollIntoViewIfNeeded();
            await last.click();
            await page.waitForTimeout(400);
            const index = Number(await last.getAttribute('data-photo-show'));
            if ((await main.getAttribute('src')) !== (await thumbs.nth(index).getAttribute('data-src'))) fail(`${name} ${kind}: Photos grid item did not show in the main frame`);
            const box = await page.locator('[data-photo-gallery]').boundingBox();
            if (!box || box.y + box.height < 0 || box.y > viewport.height) fail(`${name} ${kind}: gallery not scrolled into view (${JSON.stringify(box)})`);
            const focused = await page.evaluate(() => document.activeElement?.hasAttribute('data-photo-thumb'));
            if (!focused) fail(`${name} ${kind}: focus did not move to the small photo`);
        } else {
            report.notes.push(`${name} ${kind}: no Photos grid`);
        }

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        if (overflow > 0) fail(`${name} ${kind}: horizontal overflow ${overflow}px`);
    }

    // A space page: one photo, no small photos, nothing to click.
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    const spaceHref = await page.locator('a[href^="/spaces/"]').first().getAttribute('href');
    await page.goto(base + spaceHref, { waitUntil: 'networkidle' });
    report.notes.push(`${name} space ${spaceHref}: main ${await page.locator('[data-photo-main]').count()}, small ${await page.locator('[data-photo-thumb]').count()}, links around main ${await page.locator('a:has([data-photo-main])').count()}`);

    if (errors.length) fail(`${name}: ${errors.join(' || ')}`);
    await context.close();
}

await browser.close();
console.log(JSON.stringify(report, null, 2));
process.exit(report.failures.length ? 1 : 0);
