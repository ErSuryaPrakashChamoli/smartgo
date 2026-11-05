// SmartGro browser checks: what the Content Security Policy would block (P14-16), on a server that uses the
// TEST database and sends the policy as report-only (CSP_MODE=report; run-checks.sh with CHECKS=csp).
//
//   node docs/operations/scripts/browser/verify-csp.mjs <baseUrl> <outDir> <fixtures.json>
//
// run-checks.sh publishes a fixture privacy notice (so the forms open) and a main menu with two drop-downs,
// one with sub-groups (seed-test-fixtures.php navigation-groups), before this runs.
//
// Public website (360, 768 and 1440 px): the pages verify-public.mjs checks, then what a visitor does next:
// the drop-downs at 1024 and 1440 px (each anchored under its own button, sub-group tabs, Escape, opening
// from the keyboard), the phone menu and its groups, the font faces, the images of the media page, an
// enquiry sent and one refused, and the back office framed by a public page.
// Back office (1440 px, fixture staff sessions): a cross-section of screens, then signing in with a wrong
// password, the redirect to the sign-in page, the user menu, a sidebar link, a table filter and sort, a
// search (Livewire requests), a form's validation, a saved form, an image upload and its preview, the media
// list's thumbnails, and the 3D viewer for the Meshopt and the Draco model.
//
// For every document it records its own report-only header and checks that every nonce on the page is
// that header's nonce, and that no two documents share one. Every report the browser makes (the
// documents' securitypolicyviolation events, in every frame, and console reports, which also cover
// workers) is listed and classified: an expected report names why (EXPECTED below); anything else is
// "unexpected". Console errors, uncaught errors and failed requests are recorded too.
// Writes <outDir>/csp.json and prints a summary. The exit code is non-zero when a page did not load as
// expected, a document had no report-only policy, any response had an enforcing policy, a nonce did not
// match, two documents shared a nonce, a follow-up step failed, or a page had a console error or an
// uncaught error. Reports, expected or not, are findings rather than failures.
import fs from 'node:fs';
import zlib from 'node:zlib';

const PLAYWRIGHT = process.env.PLAYWRIGHT_MODULE ?? '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const { chromium } = await import(PLAYWRIGHT);

const [base, outDir, fixturesFile] = process.argv.slice(2);
const fixtures = JSON.parse(fs.readFileSync(fixturesFile, 'utf8'));
const origin = new URL(base).origin;
const HEADER = 'content-security-policy-report-only';
const PUBLIC = [
    ['/', 200], ['/locations', 200], ['/locations/noida', 200], ['/locations/noida?workspace=hot-desks', 200],
    ['/locations/gurugram', 200], ['/locations/noida/area/sector-2', 200], ['/locations/noida/a-70', 200],
    ['/locations/noida/a-70/hot-desks', 200], ['/locations/noida/h-105', 200], ['/workspaces', 200], ['/workspaces/hot-desks', 200],
    ['/workspaces/hot-desks/noida', 200], ['/locations/compare', 200],
    ['/locations/compare?locations[]=noida/a-70&locations[]=noida/a-71&locations[]=noida/h-105', 200],
    ['/enquire', 200], ['/book-a-tour', 200], ['/contact', 200], ['/about', 200], ['/solutions', 200], ['/community', 200],
    ['/test-fixture-sections', 200], ['/test-fixture-media', 200], ['/legal/terms', 200], ['/legal/privacy', 200],
    ['/faqs', 200], ['/insights', 200], ['/insights/test-fixture-insight', 200], ['/insights/topic/test-fixture-topic', 200], ['/customers', 200],
    ['/customers/test-fixture-customer-story', 200], ['/no-such-page', 404], ['/pin/old-page', 410],
];
const WIDTHS = [360, 768, 1440];
const BACKOFFICE = [
    ['marketing', `/admin/properties/website-profiles/${fixtures.ids.a70_profile}/edit`, 'property profile editor'],
    ['marketing', `/admin/website/pages/${fixtures.ids.about_page}/edit`, 'page editor (blocks, rich text)'],
    ['marketing', fixtures.preview, 'staff preview of a draft page'],
    ['marketing', '/admin/website/twin-models', '3D models list'],
    ['marketing', '/admin/website/twin-models/create', '3D model upload form'],
    ['operations', `/admin/properties/properties/${fixtures.ids.a70}/edit`, 'property page with floors and spaces'],
    ['operations', `/admin/properties/spaces/${fixtures.ids.space_zone}`, 'hybrid zone page'],
    ['operations', '/admin/properties/space-types', 'space types list'],
    ['operations', '/admin/properties/inventory-imports/upload', 'inventory workbook upload form'],
    ['admin', '/admin/properties/price-plans/create', 'price plan form'],
    ['admin', '/admin/sales/enquiries', 'enquiries list'],
    ['admin', `/admin/sales/enquiries/${fixtures.ids.lead}`, 'enquiry view'],
    ['admin', `/admin/sales/tours/${fixtures.ids.tour}`, 'tour page'],
    ['admin', '/admin/settings/users', 'staff list'],
    ['admin', '/admin/settings/activities', 'activity log'],
    ['operations', '/admin/reports/enquiries-and-tours', 'enquiry and tour totals'],
    ['sales', '/admin/sales/closing-reasons', 'back-office 403 page', 403],
    ['admin', '/admin/sales/enquiries/999999', 'back-office 404 page', 404],
];

// Reports this check expects, each with the reason. Every other report is "unexpected".
const EXPECTED = [
    {
        area: 'public', directive: /^script-src/, sample: /const ENDPOINT/, kind: 'development only',
        why: "Laravel Boost's browser logger, which Boost injects only locally or with debug on; Boost is a development dependency, so production never sends it",
    },
    {
        area: 'public', directive: /^frame-src$/, label: /^framing test/, kind: 'caused by this check',
        why: 'the framing test itself puts frames on a public page, which allows none',
    },
    {
        area: 'backoffice', directive: /^style-src/, sample: /^(\[x-cloak=''\]|:root \{)/, kind: 'framework, owner decision',
        why: "Filament's layout writes four <style> elements without a nonce (x-cloak, theme colours, font and sidebar variables, an empty :root); they need hashes or 'unsafe-inline' for styles (A-32)",
    },
    {
        area: 'backoffice', directive: /^style-src/, sample: /^\.ProseMirror/, kind: 'framework, owner decision',
        why: "Filament's rich editor (Tiptap) injects its base style without a nonce; same decision as Filament's layout styles (A-32)",
    },
];

const browser = await chromium.launch(process.env.CHROMIUM_EXECUTABLE ? { executablePath: process.env.CHROMIUM_EXECUTABLE } : {});
const report = { startedAt: new Date().toISOString(), base, chromium: browser.version(), policies: {}, livewireBuild: null, visits: [], summary: [], failures: [] };
const fail = (what) => report.failures.push(what);
const where = (url) => { try { const u = new URL(url); return u.origin === origin ? u.pathname : u.origin + u.pathname; } catch { return url || ''; } };
const nonceOf = (policy) => /'nonce-([^']+)'/.exec(policy ?? '')?.[1] ?? null;
const policyShape = (policy) => (policy ?? '').replace(/'nonce-[^']+'/g, "'nonce-…'");

// Every nonce on the page must be the one in its document's policy.
async function nonceCheck(page, policy) {
    const expected = nonceOf(policy);
    const tagged = await page.evaluate(() => [...document.querySelectorAll('script, style, link')].filter((e) => e.nonce).map((e) => ({ tag: e.tagName.toLowerCase(), nonce: e.nonce })));
    return { expected, tagged: tagged.length, mismatched: tagged.filter((t) => t.nonce !== expected).map((t) => t.tag) };
}

// One visit: a fresh context (with a staff session when `who` is given), the page and whatever `then`
// does on it, with every document's policy, every report and every error along the way.
async function visit({ area, who = null, path, expected = 200, label = path, width = 1440, finalPath = path.split('?')[0], then = null }) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
    if (who) {
        const [name, value] = fixtures.sessions[who].split('\t');
        await context.addCookies([{ name, value, url: base }]);
    }
    const entry = { area, who, path, label, width, documents: [], violations: [], consoleReports: [], consoleErrors: [], pageErrors: [], failedRequests: [], enforcing: [], nonces: [], then: null };
    await context.exposeBinding('__cspReport', (_source, violation) => { entry.violations.push(violation); });
    await context.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (e) => window.__cspReport({
            directive: e.effectiveDirective, blocked: e.blockedURI, source: e.sourceFile, line: e.lineNumber, sample: e.sample, document: location.pathname,
        }));
    });
    const page = await context.newPage();
    const errorStatus = expected >= 400;
    page.on('console', (m) => {
        const text = m.text();
        if (/Content Security Policy/.test(text)) {
            entry.consoleReports.push(text.replace(/'nonce-[^']+'/g, "'nonce-…'").replace(/'sha256-[^']+'/g, "'sha256-…'").slice(0, 400));
        } else if (m.type() === 'error' && !(errorStatus && /the server responded with a status of (403|404|410)/.test(text))) {
            entry.consoleErrors.push(text.slice(0, 300));
        }
    });
    page.on('pageerror', (error) => entry.pageErrors.push(error.message.split('\n')[0].slice(0, 300)));
    page.on('requestfailed', (r) => entry.failedRequests.push(`${r.failure()?.errorText} ${where(r.url())}`));
    page.on('response', (r) => {
        const headers = r.headers();
        if (headers['content-security-policy']) entry.enforcing.push(where(r.url()));
        if (/\/livewire[^/]*\/livewire(\.csp)?(\.min)?\.js/.test(r.url())) report.livewireBuild = /\.csp\.|[?&]id=[^&]*-csp\b/.test(r.url()) ? 'csp' : 'standard';
        if (r.request().isNavigationRequest() && r.frame() === page.mainFrame()) {
            entry.documents.push({ path: where(r.url()), status: r.status(), policy: headers[HEADER] ?? null });
        } else if (r.status() >= 400 && !r.request().isNavigationRequest()) {
            entry.failedRequests.push(`${r.status()} ${where(r.url())}`);
        }
    });
    const response = await page.goto(base + path, { waitUntil: 'networkidle' });
    entry.status = response?.status();
    entry.finalPath = new URL(page.url()).pathname;
    const firstPolicy = entry.documents.at(-1)?.policy ?? null;
    entry.nonces.push(await nonceCheck(page, firstPolicy));
    const documentsBefore = entry.documents.length;
    try {
        if (then) entry.then = await then(page);
    } catch (error) {
        fail(`${label} @${width}: the follow-up step failed: ${error.message.split('\n')[0]}`);
    }
    await page.waitForTimeout(400);
    if (entry.documents.length > documentsBefore) entry.nonces.push(await nonceCheck(page, entry.documents.at(-1).policy).catch(() => ({ expected: null, tagged: 0, mismatched: ['(page closed)'] })));

    for (const document of entry.documents) {
        if (!document.policy) fail(`${label} @${width}: ${document.path} has no ${HEADER} header`);
        report.policies[area] ??= policyShape(document.policy);
        if (document.policy && policyShape(document.policy) !== report.policies[area]) fail(`${label} @${width}: ${document.path} has a different ${area} policy: ${policyShape(document.policy)}`);
    }
    for (const check of entry.nonces) {
        if (check.mismatched.length) fail(`${label} @${width}: nonces that are not the document's: ${check.mismatched.join(', ')}`);
    }
    if (entry.enforcing.length) fail(`${label} @${width}: an enforcing Content-Security-Policy header on ${entry.enforcing.join(', ')}`);
    if (entry.consoleErrors.length) fail(`${label} @${width}: console errors ${entry.consoleErrors.join(' | ')}`);
    if (entry.pageErrors.length) fail(`${label} @${width}: uncaught errors ${entry.pageErrors.join(' | ')}`);
    if (entry.status !== expected || (finalPath !== null && entry.finalPath !== finalPath)) fail(`${label} @${width}: status ${entry.status} at ${entry.finalPath}, expected ${expected} at ${finalPath}`);
    report.visits.push(entry);
    await context.close();
}

// Public follow-ups.
const checkNavigation = async (page) => {
    const triggers = page.locator('nav[aria-label="Main"] button[popovertarget^="nav-group-"]');
    const count = await triggers.count();
    if (!count) throw new Error('no drop-down in the main menu: the navigation-groups fixture is missing');
    const groups = [];
    for (let index = 0; index < count; index++) {
        const trigger = triggers.nth(index);
        const id = await trigger.getAttribute('popovertarget');
        const panel = page.locator(`[id="${id}"]`);
        await trigger.click();
        await panel.waitFor({ state: 'visible' });
        const [button, box] = [await trigger.boundingBox(), await panel.boundingBox()];
        const styles = { anchorName: await trigger.evaluate((el) => getComputedStyle(el).anchorName), positionAnchor: await panel.evaluate((el) => getComputedStyle(el).positionAnchor) };
        const anchored = Math.abs(box.y - (button.y + button.height + 12)) <= 2;
        let tabs = null;
        const tabButtons = panel.locator('[data-flyout-tab]');
        if (await tabButtons.count() > 1) {
            await tabButtons.nth(1).click();
            tabs = await panel.locator('[data-flyout-pane]').evaluateAll((panes) => panes.map((pane) => !pane.hidden));
        }
        await page.keyboard.press('Escape');
        const closedByEscape = !(await panel.evaluate((el) => el.matches(':popover-open')));
        await trigger.focus();
        await page.keyboard.press('Enter');
        const opensFromKeyboard = await panel.evaluate((el) => el.matches(':popover-open'));
        await page.keyboard.press('Escape');
        const group = { id, ...styles, anchored, buttonBottom: Math.round(button.y + button.height), panelTop: Math.round(box.y), tabs, closedByEscape, opensFromKeyboard };
        if (styles.anchorName !== `--${id}` || styles.positionAnchor !== `--${id}`) throw new Error(`${id}: anchor ${styles.anchorName} / ${styles.positionAnchor}`);
        if (!anchored) throw new Error(`${id}: the drop-down is not under its button (${group.panelTop} vs ${group.buttonBottom} + 12)`);
        if (tabs && !(tabs[0] === false && tabs[1] === true)) throw new Error(`${id}: the second sub-group tab did not show its links (${JSON.stringify(tabs)})`);
        if (!closedByEscape || !opensFromKeyboard) throw new Error(`${id}: Escape closed it ${closedByEscape}, Enter opened it ${opensFromKeyboard}`);
        groups.push(group);
    }
    return groups;
};
const fontFaces = (page) => page.evaluate(async () => {
    await document.fonts.ready;
    return [...document.fonts].map((font) => `${font.family} ${font.weight} ${font.status}`);
});
const checkHome = async (page) => {
    const fonts = await fontFaces(page);
    if (!fonts.some((font) => font.endsWith('loaded')) || fonts.some((font) => font.endsWith('error'))) throw new Error(`font faces ${fonts.join(', ')}`);
    return { fonts, navigation: await checkNavigation(page) };
};
const checkPhoneMenu = async (page) => {
    await page.locator('button[popovertarget="mobile-menu"]:visible').first().click();
    const menu = page.locator('#mobile-menu');
    await menu.waitFor({ state: 'visible' });
    const groups = menu.locator('details');
    const count = await groups.count();
    if (!count) throw new Error('the phone menu has no group: the navigation-groups fixture is missing');
    const opened = [];
    for (let index = 0; index < count; index++) {
        const group = groups.nth(index);
        const summary = group.locator('summary').first();
        if (!(await summary.isVisible())) continue;
        await summary.click();
        opened.push({ label: (await summary.innerText()).trim(), open: await group.evaluate((el) => el.open), visibleLinks: await group.locator('a:visible').count() });
    }
    await page.keyboard.press('Escape');
    const closedByEscape = !(await menu.evaluate((el) => el.matches(':popover-open')));
    if (!opened.length || opened.some((group) => !group.open) || !closedByEscape) throw new Error(`phone menu ${JSON.stringify({ opened, closedByEscape })}`);
    return { groups: opened, closedByEscape };
};
const checkImages = async (page) => {
    await page.evaluate(async () => {
        for (let y = 0; y <= document.body.scrollHeight; y += 400) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 50));
        }
    });
    await page.waitForLoadState('networkidle');
    const images = await page.evaluate(() => [...document.querySelectorAll('main img')].map((image) => ({ src: new URL(image.currentSrc || image.src).pathname, loaded: image.complete && image.naturalWidth > 0 })));
    if (!images.length || images.some((image) => !image.loaded)) throw new Error(`images ${JSON.stringify(images)}`);
    return { images: images.length };
};
const sendEnquiry = (valid) => async (page) => {
    await page.fill('#enquiry-name', 'TEST FIXTURE visitor');
    await page.fill('#enquiry-email', valid ? 'csp-check@fixture.test' : 'not an email');
    await page.fill('#enquiry-message', 'TEST FIXTURE enquiry from the CSP check. Not a real visitor.');
    await page.check('#enquiry-consent');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.getByRole('button', { name: 'Send enquiry' }).click()]);
    const at = new URL(page.url()).pathname;
    if (valid === (at === '/enquire')) throw new Error(`after sending, at ${at}`);
    return valid ? `sent; confirmation at ${at}` : `refused; back at ${at} with ${await page.locator('[id$="-error"], .text-danger, [role=alert]').count()} error messages`;
};
const frameTheBackOffice = async (page) => {
    const framing = [];
    page.on('console', (m) => { if (/frame-ancestors/.test(m.text())) framing.push(m.text()); });
    await page.evaluate((paths) => {
        for (const path of paths) {
            const frame = document.createElement('iframe');
            frame.src = path;
            frame.title = `framing test ${path}`;
            document.body.append(frame);
        }
    }, ['/admin/login', '/about']);
    await page.waitForTimeout(3000);
    // Chrome reports frame-ancestors in the console only (no event in either document).
    const backOfficeRefused = framing.some((text) => /frame-ancestors 'none'/.test(text));
    const publicRefused = framing.some((text) => /frame-ancestors 'self'/.test(text));
    if (!backOfficeRefused || publicRefused) throw new Error(`frame-ancestors reports ${JSON.stringify(framing)}`);
    return "framed /admin/login (reported: frame-ancestors 'none') and /about (allowed: frame-ancestors 'self')";
};

// Back-office follow-ups.
const signInWrongly = async (page) => {
    await page.locator('input[type=email]').fill('admin@fixture.test');
    await page.locator('input[type=password]').fill('not the password');
    await page.getByRole('button', { name: 'Sign in' }).click();
    const message = page.getByText(/These credentials do not match/);
    await message.waitFor({ timeout: 15000 });
    return 'refused: "These credentials do not match our records"';
};
const useDashboard = async (page) => {
    await page.locator('.fi-user-menu-trigger').first().click();
    const menu = page.locator('.fi-user-menu .fi-dropdown-panel');
    await menu.waitFor({ state: 'visible' });
    const items = await menu.locator('.fi-dropdown-list-item').count();
    await page.keyboard.press('Escape');
    // A cluster's link redirects to its first page, so wait for leaving the dashboard, not for the link's address.
    const link = page.locator('.fi-sidebar a.fi-sidebar-item-btn:visible').nth(1);
    const href = new URL(await link.getAttribute('href'), origin).pathname;
    await Promise.all([page.waitForURL((url) => url.pathname !== '/admin', { waitUntil: 'networkidle' }), link.click()]);
    return { userMenuItems: items, sidebarLink: href, arrivedAt: new URL(page.url()).pathname };
};
const filterAndSort = async (page) => {
    await page.locator('.fi-ta-filters-dropdown .fi-dropdown-trigger').first().click();
    await page.locator('.fi-ta-filters-dropdown .fi-dropdown-panel').first().waitFor({ state: 'visible' });
    await page.keyboard.press('Escape');
    const waiting = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST', { timeout: 15000 });
    await page.locator('.fi-ta-header-cell-sort-btn').first().click();
    return `filters opened; sorted (Livewire ${(await waiting).status()})`;
};
const searchList = async (page) => {
    const waiting = page.waitForResponse((r) => r.url().includes('/livewire') && r.request().method() === 'POST', { timeout: 15000 });
    await page.getByPlaceholder(/Search/).first().fill('TF');
    return `Livewire update ${(await waiting).status()}`;
};
const createEmpty = async (page) => {
    const nativelyRequired = await page.locator('form :invalid').count();
    await page.evaluate(() => document.querySelectorAll('form').forEach((form) => { form.noValidate = true; }));
    await page.getByRole('button', { name: /^Create$/ }).click();
    const errors = page.locator('.fi-fo-field-wrp-error-message');
    await errors.first().waitFor({ timeout: 15000 });
    return `browser validation first (${nativelyRequired} required fields empty); then the server's: ${await errors.count()} messages, still at ${new URL(page.url()).pathname}`;
};
const saveForm = async (page) => {
    await page.getByRole('button', { name: 'Save changes' }).click();
    await page.getByText('Saved').first().waitFor({ timeout: 15000 });
    return 'saved';
};
const png = (size) => {
    const chunk = (type, data) => {
        const body = Buffer.concat([Buffer.from(type), data]);
        const length = Buffer.alloc(4);
        length.writeUInt32BE(data.length);
        const crc = Buffer.alloc(4);
        crc.writeUInt32BE(zlib.crc32(body) >>> 0);
        return Buffer.concat([length, body, crc]);
    };
    const header = Buffer.alloc(13);
    header.writeUInt32BE(size, 0);
    header.writeUInt32BE(size, 4);
    header.set([8, 2, 0, 0, 0], 8);
    const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(size * 3, 0x40)]);
    return Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', header), chunk('IDAT', zlib.deflateSync(Buffer.concat(Array(size).fill(row)))), chunk('IEND', Buffer.alloc(0))]);
};
const uploadImage = async (page) => {
    await page.locator('input[type=file]').first().setInputFiles({ name: 'test-fixture-csp.png', mimeType: 'image/png', buffer: png(640) });
    await page.locator('.filepond--item[data-filepond-item-state="processing-complete"], .filepond--file-status-main:has-text("Upload complete")').first().waitFor({ timeout: 30000 });
    await page.locator('.filepond--image-preview canvas, .filepond--image-preview img').first().waitFor({ timeout: 15000 }).catch(() => null);
    return { uploaded: true, previews: await page.locator('.filepond--image-preview canvas, .filepond--image-preview img').count() };
};
const thumbnails = async (page) => {
    const images = await page.evaluate(() => [...document.querySelectorAll('.fi-ta-content-ctn img')].map((image) => ({ src: image.getAttribute('src'), alt: image.alt, loaded: image.complete && image.naturalWidth > 0 })));
    // A thumbnail without an address is a known defect, not a policy matter (P14-17): it is recorded, not failed.
    const shown = images.filter((image) => image.src);
    if (!images.length || shown.some((image) => !image.loaded)) throw new Error(`thumbnails ${JSON.stringify(images)}`);
    return { thumbnails: shown.length, withoutAddress: images.length - shown.length };
};
const showModel = async (page) => {
    await page.getByRole('button', { name: /Show the 3D model/ }).click();
    await page.waitForFunction(() => /^(Model shown|This browser|The model could not)/.test(document.querySelector('[data-twin-status]').textContent), null, { timeout: 60000 });
    const status = (await page.locator('[data-twin-status]').innerText()).trim();
    if (!status.startsWith('Model shown')) throw new Error(status);
    return status;
};

for (const [path, expected] of PUBLIC) {
    for (const width of WIDTHS) {
        await visit({ area: 'public', path, expected, width, then: path === '/' && width === 1440 ? checkHome : (path === '/' && width === 360 ? checkPhoneMenu : null) });
    }
}
await visit({ area: 'public', path: '/', label: 'drop-downs at 1024 px', width: 1024, then: checkNavigation });
await visit({ area: 'public', path: '/test-fixture-media', label: 'media page images', then: checkImages });
await visit({ area: 'public', path: '/enquire', label: 'enquiry refused (invalid email)', width: 360, then: sendEnquiry(false) });
await visit({ area: 'public', path: '/enquire', label: 'enquiry sent', then: sendEnquiry(true) });
await visit({ area: 'public', path: '/about', label: 'framing test: a public page frames /admin/login and /about', then: frameTheBackOffice });

await visit({ area: 'backoffice', path: '/admin/login', label: 'sign-in with a wrong password', then: signInWrongly });
await visit({ area: 'backoffice', path: '/admin', label: 'redirect to sign-in without a session', finalPath: '/admin/login' });
await visit({ area: 'backoffice', who: 'admin', path: '/admin', label: 'dashboard: user menu, then a sidebar link', finalPath: null, then: useDashboard });
await visit({ area: 'backoffice', who: 'marketing', path: '/admin/website/pages', label: 'pages list: filters and sort', then: filterAndSort });
await visit({ area: 'backoffice', who: 'operations', path: '/admin/properties/spaces', label: 'spaces list: search', then: searchList });
await visit({ area: 'backoffice', who: 'marketing', path: '/admin/website/pages/create', label: 'new page: create with nothing filled in', then: createEmpty });
await visit({ area: 'backoffice', who: 'operations', path: `/admin/properties/cities/${fixtures.ids.noida}/edit`, label: 'city editor: save', then: saveForm });
await visit({ area: 'backoffice', who: 'marketing', path: '/admin/website/media-assets/create', label: 'media upload: image and preview', then: uploadImage });
await visit({ area: 'backoffice', who: 'marketing', path: '/admin/website/media-assets', label: 'media list thumbnails', then: thumbnails });
for (const [who, path, label, expected = 200] of BACKOFFICE) {
    await visit({ area: 'backoffice', who, path, label, expected });
}
for (const key of ['twin_model', 'twin_model_draco']) {
    await visit({ area: 'backoffice', who: 'operations', path: `/admin/website/twin-models/${fixtures.ids[key]}`, label: `3D viewer shown (${key})`, then: showModel });
}

// Nonces are per response: no two documents may share one.
const nonces = report.visits.flatMap((v) => v.documents.map((d) => nonceOf(d.policy))).filter(Boolean);
if (new Set(nonces).size !== nonces.length) fail(`${nonces.length - new Set(nonces).size} documents shared a nonce`);

// Grouped and classified: the same directive blocking the same thing from the same file counts once.
const groups = new Map();
for (const v of report.visits) {
    for (const x of v.violations) {
        const blocked = /^https?:/.test(x.blocked) ? where(x.blocked) : x.blocked;
        // A report belongs to the policy of the document it happened in: a framed back-office page has its own.
        const area = (x.document ?? '').startsWith('/admin') ? 'backoffice' : v.area;
        const rule = EXPECTED.find((r) => r.area === area && r.directive.test(x.directive) && (!r.sample || r.sample.test(x.sample ?? '')) && (!r.label || r.label.test(v.label)));
        const key = [area, x.directive, blocked, where(x.source), rule?.why ?? ''].join(' | ');
        const group = groups.get(key) ?? { area, directive: x.directive, blocked, source: where(x.source), kind: rule?.kind ?? 'unexpected', why: rule?.why ?? null, samples: new Set(), pages: new Set(), count: 0 };
        group.count += 1;
        group.pages.add(`${v.label} @${v.width}${x.document && x.document !== v.path.split('?')[0] ? ` (in ${x.document})` : ''}`);
        if (x.sample) group.samples.add(x.sample.slice(0, 80));
        groups.set(key, group);
    }
}
report.summary = [...groups.values()].map((g) => ({ ...g, samples: [...g.samples].slice(0, 5), pages: [...g.pages].slice(0, 8), pageCount: g.pages.size }));
report.consoleOnly = [...new Set(report.visits.flatMap((v) => v.consoleReports.map((text) => `${v.area}: ${text.slice(0, 300)}`)))];
report.finishedAt = new Date().toISOString();

fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(`${outDir}/csp.json`, JSON.stringify(report, null, 2));
const count = (kind) => report.summary.filter((g) => g.kind === kind).reduce((n, g) => n + g.count, 0);
console.log(JSON.stringify({
    visits: report.visits.length,
    documents: nonces.length,
    livewireBuild: report.livewireBuild,
    policies: report.policies,
    reports: { total: report.visits.reduce((n, v) => n + v.violations.length, 0), unexpected: count('unexpected') },
    groups: report.summary.map((g) => `${g.kind}: ${g.area} ${g.directive} ${g.blocked} from ${g.source || '(page)'} x${g.count} on ${g.pageCount}`),
    followUps: report.visits.filter((v) => v.then).map((v) => `${v.label} @${v.width}: ${typeof v.then === 'string' ? v.then : JSON.stringify(v.then).slice(0, 300)}`),
    failedRequests: [...new Set(report.visits.flatMap((v) => v.failedRequests))].slice(0, 20),
    failures: report.failures,
}, null, 2));
process.exit(report.failures.length ? 1 : 0);
