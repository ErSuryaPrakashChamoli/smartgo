// SmartGro browser checks: the enquiry form with the form open, and its confirmation page, on the TEST
// database only (Phase 6 pack §11.4: the confirmation page was never checked). run-checks.sh first
// publishes a fixture privacy notice on that database (seed-test-fixtures.php privacy), which opens the
// form there; nothing changes anywhere else, and the form stays closed on every other database.
//
//   node docs/operations/scripts/browser/verify-enquiry.mjs <baseUrl> <outDir> [fixtures.json]
//
// At 360 and 1440 px: axe-core (WCAG 2.0 to 2.2, A and AA) on the open form, a submission with labelled
// fixture details, then the confirmation page's address, heading and reference, horizontal overflow and
// axe-core. Once more with JavaScript off at 360 px: the form submits and the confirmation page shows.
// With the fixtures file (staff sessions), the page-builder journey of FR-CMS-1 (OD-125): a Marketing
// Manager builds a page in the back office with a location list and an enquiry form section and submits
// it, the Super Admin approves it, the author publishes it; a visitor then sees the location cards and
// the form with the chosen location (axe-core, overflow, and without JavaScript), sends wrong details and
// is brought back to the form, then sends the enquiry from that page.
// Writes <outDir>/enquiry.json and prints a summary.
import fs from 'node:fs';

const PLAYWRIGHT = process.env.PLAYWRIGHT_MODULE ?? '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const AXE = process.env.AXE_CORE ?? '/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/axe-core/axe.min.js';
const { chromium } = await import(PLAYWRIGHT);
const axeSource = fs.readFileSync(AXE, 'utf8');
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
// SubmitEnquiry refuses a form sent sooner than EnquiryForm::MINIMUM_SECONDS (3) after it was shown.
const WAIT_BEFORE_SENDING_MS = 3500;
// The enquiry route allows three submissions a minute from one address (throttle:enquiries); the journey's
// two submissions wait until the first part's three have left that window.
const THROTTLE_WINDOW_MS = 61000;
let lastSubmissionAt = 0;

const [base, outDir, fixturesFile] = process.argv.slice(2);
const browser = await chromium.launch(process.env.CHROMIUM_EXECUTABLE ? { executablePath: process.env.CHROMIUM_EXECUTABLE } : {});
const report = { startedAt: new Date().toISOString(), base, chromium: browser.version(), runs: [], failures: [] };

async function runAxe(page) {
    await page.evaluate(axeSource);

    return page.evaluate(async (tags) => (await window.axe.run(document, { runOnly: { type: 'tag', values: tags } })).violations
        .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, target: v.nodes[0]?.target?.join(' ') })), AXE_TAGS);
}

for (const [width, javaScript] of [[360, true], [1440, true], [360, false]]) {
    const label = `@${width}${javaScript ? '' : ', JavaScript off'}`;
    // Reduced motion turns the site's smooth scrolling off (app.css), which otherwise keeps Playwright waiting
    // for a still-scrolling page before it can tick the consent box.
    const context = await browser.newContext({ viewport: { width, height: 900 }, javaScriptEnabled: javaScript, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const response = await page.goto(`${base}/enquire`, { waitUntil: 'networkidle' });
    const formOpen = await page.locator('#enquiry-name').count() === 1;
    const formAxe = javaScript && formOpen ? await runAxe(page) : null;

    if (formOpen) {
        await page.fill('#enquiry-name', 'TEST FIXTURE visitor');
        await page.fill('#enquiry-email', `visitor-${width}-${javaScript ? 'js' : 'nojs'}@fixture.test`);
        await page.fill('#enquiry-message', 'TEST FIXTURE enquiry from the browser checks. Not a real visitor.');
        await page.check('#enquiry-consent');
        await page.waitForTimeout(WAIT_BEFORE_SENDING_MS);
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.getByRole('button', { name: 'Send enquiry' }).click()]);
        lastSubmissionAt = Date.now();
    }

    const received = {
        path: new URL(page.url()).pathname,
        h1: await page.locator('h1').allInnerTexts(),
        reference: (await page.locator('main').innerText()).match(/ENQ-[A-Z0-9]{8}/)?.[0] ?? null,
        overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth),
        axe: javaScript ? await runAxe(page) : null,
    };
    report.runs.push({ width, javaScript, status: response?.status(), formOpen, formAxe, received });

    if (response?.status() !== 200 || !formOpen) report.failures.push(`${label}: the form is not open (status ${response?.status()})`);
    if (formAxe?.length) report.failures.push(`${label}: axe on the form: ${formAxe.map((v) => `${v.id} at ${v.target}`).join('; ')}`);
    if (received.path !== '/enquire/received' || received.h1.length !== 1 || !received.reference) report.failures.push(`${label}: no confirmation page (${received.path}, ${received.h1.join(' | ')})`);
    if (received.overflow > 0) report.failures.push(`${label}: horizontal overflow ${received.overflow}px on the confirmation page`);
    if (received.axe?.length) report.failures.push(`${label}: axe on the confirmation page: ${received.axe.map((v) => `${v.id} at ${v.target}`).join('; ')}`);
    await context.close();
}

// The page-builder journey (OD-125): built, approved and published in the back office, then used on the website.
if (fixturesFile) {
    const fixtures = JSON.parse(fs.readFileSync(fixturesFile, 'utf8'));
    const slug = 'test-fixture-locations-and-enquiry';
    const journey = { steps: [], visits: [] };
    const staff = async (who) => {
        const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
        const [name, value] = fixtures.sessions[who].split('\t');
        await context.addCookies([{ name, value, url: base }]);
        return { context, page: await context.newPage() };
    };
    // Waits (up to 10 s) for the step's notice or a refusal, so a slow response is not read before it appears.
    const notices = async (page, expected) => {
        const read = async () => (await page.locator('.fi-no-notification').allInnerTexts()).join(' | ');
        for (let waited = 0; !(expected.test(await read()) || /Not saved|Not allowed/.test(await read())) && waited < 10000; waited += 250) await page.waitForTimeout(250);
        return read();
    };
    // Filament's confirmation lists Cancel first, then the confirming button.
    const confirm = async (page) => {
        const dialog = page.locator('.fi-modal-window:visible').last();
        await dialog.waitFor();
        await page.waitForTimeout(300);
        await dialog.locator('.fi-modal-footer-actions button').last().click();
        await page.waitForTimeout(1500);
    };

    try {
        const author = await staff('marketing');
        await author.page.goto(`${base}/admin/website/pages/create`, { waitUntil: 'networkidle' });
        await author.page.getByLabel('Page title').fill('TEST FIXTURE locations and enquiry');
        await author.page.getByLabel('Page address').fill(slug);
        let offered = '';
        for (const label of ['Location list', 'Enquiry form']) {
            await author.page.getByRole('button', { name: 'Add section' }).last().click();
            offered ||= await author.page.locator('.fi-fo-builder-block-picker .fi-dropdown-panel:visible').innerText();
            await author.page.locator('.fi-fo-builder-block-picker .fi-dropdown-panel:visible').getByText(label, { exact: true }).click();
            await author.page.waitForTimeout(800);
        }
        const sections = author.page.locator('.fi-fo-builder-item');
        await sections.nth(0).getByLabel('Title').fill('TEST FIXTURE where to find us');
        await sections.nth(0).getByLabel('Locations in').selectOption({ label: 'Noida' });
        await sections.nth(1).getByLabel('Title').fill('TEST FIXTURE ask about A-70');
        await sections.nth(1).getByLabel('Location already chosen').selectOption({ label: 'SmartGro A-70' });
        await author.page.getByRole('button', { name: /^Create$/ }).click();
        await author.page.waitForURL(/\/admin\/website\/pages\/\d+\/edit/, { timeout: 30000 });
        const editUrl = author.page.url();
        await author.page.getByRole('button', { name: 'Submit for approval' }).click();
        await confirm(author.page);
        journey.steps.push({ step: 'built and submitted by the Marketing Manager', offered: /Location list/.test(offered) && /Enquiry form/.test(offered), notices: await notices(author.page, /Submitted for approval/) });

        const approver = await staff('admin');
        await approver.page.goto(editUrl, { waitUntil: 'networkidle' });
        await approver.page.getByRole('button', { name: 'Approve' }).click();
        await confirm(approver.page);
        journey.steps.push({ step: 'approved by the Super Admin', notices: await notices(approver.page, /Version approved/) });
        await approver.context.close();

        await author.page.goto(editUrl, { waitUntil: 'networkidle' });
        await author.page.getByRole('button', { name: 'Publish now' }).click();
        await confirm(author.page);
        journey.steps.push({ step: 'published by the author', notices: await notices(author.page, /Page published/) });
        await author.context.close();

        for (const [width, javaScript] of [[360, true], [1440, true], [360, false]]) {
            const context = await browser.newContext({ viewport: { width, height: 900 }, javaScriptEnabled: javaScript, reducedMotion: 'reduce' });
            const page = await context.newPage();
            const response = await page.goto(`${base}/${slug}?utm_source=test-fixture`, { waitUntil: 'networkidle' });
            const visit = {
                width, javaScript, status: response?.status(),
                ...(await page.evaluate(() => ({
                    headings: [...document.querySelectorAll('main h2')].map((h) => h.textContent.trim()),
                    cards: [...document.querySelectorAll('main article a[href^="/locations/"]')].map((a) => a.getAttribute('href')),
                    chosen: document.querySelector('#enquiry-property')?.selectedOptions[0]?.textContent.trim() ?? null,
                    from: document.querySelector('#enquiry-form input[name="from"]')?.value ?? null,
                    utm: document.querySelector('#enquiry-form input[name="utm_source"]')?.value ?? null,
                    overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                }))),
                axe: javaScript ? await runAxe(page) : null,
            };
            if (width === 1440) {
                await page.waitForTimeout(Math.max(0, lastSubmissionAt + THROTTLE_WINDOW_MS - Date.now()));
                await page.fill('#enquiry-name', 'TEST FIXTURE visitor');
                await page.fill('#enquiry-email', 'not an email');
                await page.check('#enquiry-consent');
                await page.waitForTimeout(WAIT_BEFORE_SENDING_MS);
                await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.getByRole('button', { name: 'Send enquiry' }).click()]);
                visit.wrongDetails = {
                    at: new URL(page.url()).pathname + new URL(page.url()).hash,
                    error: (await page.locator('#enquiry-email-error').innerText().catch(() => '')).trim(),
                    formInView: await page.locator('#enquiry-form').evaluate((form) => form.getBoundingClientRect().top < window.innerHeight),
                };
                await page.fill('#enquiry-email', 'page-section@fixture.test');
                await page.check('#enquiry-consent');
                await page.waitForTimeout(WAIT_BEFORE_SENDING_MS);
                await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.getByRole('button', { name: 'Send enquiry' }).click()]);
                visit.sent = { at: new URL(page.url()).pathname, reference: (await page.locator('main').innerText()).match(/ENQ-[A-Z0-9]{8}/)?.[0] ?? null };
            }
            journey.visits.push(visit);
            await context.close();
        }
    } catch (error) {
        journey.error = String(error).slice(0, 400);
    }
    report.pageJourney = journey;

    const [built, approved, published] = journey.steps;
    if (journey.error) report.failures.push(`page journey: ${journey.error}`);
    if (!built?.offered || !/Submitted for approval/.test(built?.notices ?? '')) report.failures.push(`page journey: not built and submitted (${JSON.stringify(built)})`);
    if (!/Version approved/.test(approved?.notices ?? '')) report.failures.push(`page journey: not approved (${JSON.stringify(approved)})`);
    if (!/Page published/.test(published?.notices ?? '')) report.failures.push(`page journey: not published (${JSON.stringify(published)})`);
    for (const visit of journey.visits) {
        const label = `page journey @${visit.width}${visit.javaScript ? '' : ', JavaScript off'}`;
        if (visit.status !== 200 || visit.headings.join('|') !== 'TEST FIXTURE where to find us|TEST FIXTURE ask about A-70') report.failures.push(`${label}: status ${visit.status}, sections ${visit.headings.join(' | ')}`);
        if (visit.cards.length === 0 || !visit.cards.every((path) => path.startsWith('/locations/noida/'))) report.failures.push(`${label}: location cards ${visit.cards.join(', ')}`);
        if (!visit.chosen?.startsWith('SmartGro A-70') || visit.from !== `/${slug}` || visit.utm !== 'test-fixture') report.failures.push(`${label}: form context ${JSON.stringify({ chosen: visit.chosen, from: visit.from, utm: visit.utm })}`);
        if (visit.overflow > 0) report.failures.push(`${label}: horizontal overflow ${visit.overflow}px`);
        if (visit.axe?.length) report.failures.push(`${label}: axe ${visit.axe.map((v) => `${v.id} at ${v.target}`).join('; ')}`);
        if (visit.wrongDetails && (visit.wrongDetails.at !== `/${slug}#enquiry-form` || !visit.wrongDetails.error || !visit.wrongDetails.formInView)) report.failures.push(`${label}: wrong details not brought back to the form ${JSON.stringify(visit.wrongDetails)}`);
        if (visit.sent && (visit.sent.at !== '/enquire/received' || !visit.sent.reference)) report.failures.push(`${label}: enquiry not sent ${JSON.stringify(visit.sent)}`);
    }
}

await browser.close();
report.finishedAt = new Date().toISOString();
fs.writeFileSync(`${outDir}/enquiry.json`, JSON.stringify(report, null, 1));
console.log(JSON.stringify({
    runs: report.runs.map((r) => `@${r.width}${r.javaScript ? '' : ' no JS'}: form ${r.formOpen ? 'open' : 'closed'}, form axe=${r.formAxe?.length ?? 'n/a'}; ${r.received.path} "${r.received.h1.join(' | ')}" ${r.received.reference ?? 'no reference'}, overflow ${r.received.overflow}, axe=${r.received.axe?.length ?? 'n/a'}`),
    pageJourney: report.pageJourney,
    failures: report.failures,
}, null, 1));
process.exit(report.failures.length ? 1 : 0);
