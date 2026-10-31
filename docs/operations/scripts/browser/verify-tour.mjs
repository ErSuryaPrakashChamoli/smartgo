// SmartGro browser checks: the tour journey (FR-BK-2, the approved tour journey, OD-129) on the TEST
// database only. run-checks.sh publishes a fixture privacy notice there first (seed-test-fixtures.php
// privacy); the fixtures give A-70 TEST FIXTURE tour hours. Nothing changes anywhere else, and tour
// requests stay closed on every other database.
//
//   node docs/operations/scripts/browser/verify-tour.mjs <baseUrl> <outDir> <fixtures.json>
//
// At 1440 px: from the property page's "Book a tour", a free time on a later day than the first offered
// and labelled fixture details are sent;
// the "Tour requested" page shows the reference and the private link; the visitor's tour page opens; the
// Super Admin confirms the request in the back office; the visitor's page then shows the confirmation
// and a calendar file; the visitor moves the tour, which waits for staff again, then cancels it through
// the same signed link (FR-BK-2), after which it can no longer be moved or cancelled. At 360 px: the
// picker and the pages are checked with axe-core; with JavaScript off, a request is sent and its pages open.
// axe-core (WCAG 2.0 to 2.2, A and AA) and horizontal overflow on every page. Writes <outDir>/tour.json.
import fs from 'node:fs';

const PLAYWRIGHT = process.env.PLAYWRIGHT_MODULE ?? '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const AXE = process.env.AXE_CORE ?? '/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/axe-core/axe.min.js';
const { chromium } = await import(PLAYWRIGHT);
const axeSource = fs.readFileSync(AXE, 'utf8');
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
// RequestTour refuses a form sent sooner than EnquiryForm::MINIMUM_SECONDS (3) after it was shown.
const WAIT_BEFORE_SENDING_MS = 3500;
// Tour requests share the enquiry route limit (three a minute from one address); the enquiry checks
// run just before, so the journey waits for their window to pass.
const THROTTLE_WINDOW_MS = 61000;

const [base, outDir, fixturesFile] = process.argv.slice(2);
const fixtures = JSON.parse(fs.readFileSync(fixturesFile, 'utf8'));
const browser = await chromium.launch(process.env.CHROMIUM_EXECUTABLE ? { executablePath: process.env.CHROMIUM_EXECUTABLE } : {});
const report = { startedAt: new Date().toISOString(), base, chromium: browser.version(), pages: [], steps: [], failures: [] };

async function runAxe(page) {
    await page.evaluate(axeSource);

    return page.evaluate(async (tags) => (await window.axe.run(document, { runOnly: { type: 'tag', values: tags } })).violations
        .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, target: v.nodes[0]?.target?.join(' ') })), AXE_TAGS);
}

async function check(page, label, javaScript) {
    const entry = {
        label,
        path: new URL(page.url()).pathname,
        h1: await page.locator('h1').allInnerTexts(),
        overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth),
        axe: javaScript ? await runAxe(page) : null,
    };
    report.pages.push(entry);
    if (entry.h1.length !== 1) report.failures.push(`${label}: ${entry.h1.length} h1 headings`);
    if (entry.overflow > 0) report.failures.push(`${label}: horizontal overflow ${entry.overflow}px`);
    if (entry.axe?.length) report.failures.push(`${label}: axe ${entry.axe.map((v) => `${v.id} at ${v.target}`).join('; ')}`);

    return entry;
}

// The pickers open on the first day with free times, usually today. The journey picks another day, so it
// never depends on the time of day: late in the day, today may have one free time left, starting within
// minutes, which cannot then be moved.
async function chooseDay(page, which) {
    const days = page.locator('[aria-label="Days with tour times"] a:not([aria-current])').filter({ hasNotText: 'fully booked' });
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), (which === 'last' ? days.last() : days.first()).click()]);
}

async function requestTour(page, email) {
    await chooseDay(page, 'first');
    await page.locator('label:has(input[name="starts_at"]:not([disabled]))').first().click();
    const chosen = await page.locator('input[name="starts_at"]:checked').getAttribute('value');
    await page.fill('#tour-name', 'TEST FIXTURE visitor');
    await page.fill('#tour-email', email);
    await page.check('#tour-consent');
    await page.waitForTimeout(WAIT_BEFORE_SENDING_MS);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle' }), page.getByRole('button', { name: 'Request a tour' }).click()]);

    return { chosen, at: new URL(page.url()).pathname, reference: (await page.locator('main').innerText()).match(/TOUR-[A-Z0-9]{8}/)?.[0] ?? null, text: await page.locator('main').innerText() };
}

try {
    await new Promise((resolve) => setTimeout(resolve, THROTTLE_WINDOW_MS));

    // 1440 px, JavaScript on: the whole journey.
    const visitorContext = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const visitor = await visitorContext.newPage();
    await visitor.goto(`${base}/locations/noida/a-70`, { waitUntil: 'networkidle' });
    // The location page's own button (it preselects A-70), not the header's "Book a Tour".
    await Promise.all([visitor.waitForNavigation({ waitUntil: 'networkidle' }), visitor.locator('main').getByRole('link', { name: 'Book a tour' }).first().click()]);
    await check(visitor, 'picker @1440', true);
    const sent = await requestTour(visitor, 'tour-1440@fixture.test');
    report.steps.push({ step: 'requested @1440', at: sent.at, reference: sent.reference, waiting: /Tour requested: not yet confirmed/.test(sent.text) });
    await check(visitor, 'requested @1440', true);
    await Promise.all([visitor.waitForNavigation({ waitUntil: 'networkidle' }), visitor.getByRole('link', { name: 'Your tour page' }).click()]);
    const tourPage = visitor.url();
    await check(visitor, 'tour page @1440 (requested)', true);

    const staffContext = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    const [name, value] = fixtures.sessions.admin.split('\t');
    await staffContext.addCookies([{ name, value, url: base }]);
    const staff = await staffContext.newPage();
    await staff.goto(`${base}/admin/sales/tours?search=${sent.reference}`, { waitUntil: 'networkidle' });
    await Promise.all([staff.waitForURL(/\/admin\/sales\/tours\/\d+$/), staff.getByRole('link', { name: sent.reference }).first().click()]);
    await staff.waitForLoadState('networkidle');
    await staff.getByRole('button', { name: 'Confirm' }).first().click();
    const dialog = staff.locator('.fi-modal-window:visible').last();
    await dialog.waitFor();
    await staff.waitForTimeout(300);
    await dialog.locator('.fi-modal-footer-actions button').last().click();
    await staff.waitForTimeout(1500);
    const notices = (await staff.locator('.fi-no-notification').allInnerTexts()).join(' | ');
    report.steps.push({ step: 'confirmed by the Super Admin', notices });
    await check(staff, 'staff tour page @1440', true);
    await staffContext.close();

    await visitor.goto(tourPage, { waitUntil: 'networkidle' });
    const confirmedText = await visitor.locator('main').innerText();
    const calendarHref = await visitor.getByRole('link', { name: 'Add to calendar' }).getAttribute('href');
    const calendar = await visitor.request.get(calendarHref);
    const ics = await calendar.text();
    report.steps.push({ step: 'confirmed, seen by the visitor', confirmed: /Tour confirmed/.test(confirmedText), calendar: calendar.status(), type: calendar.headers()['content-type'], dtstart: ics.match(/DTSTART:\S+/)?.[0] ?? null, visitorDetailsInFile: /TEST FIXTURE visitor|fixture\.test/.test(ics) });
    await check(visitor, 'tour page @1440 (confirmed)', true);
    await chooseDay(visitor, 'last');
    await visitor.locator('form:has(button:text("Move my tour")) label:has(input[name="starts_at"]:not([disabled]))').first().click();
    await Promise.all([visitor.waitForNavigation({ waitUntil: 'networkidle' }), visitor.getByRole('button', { name: 'Move my tour' }).click()]);
    const movedText = await visitor.locator('main').innerText();
    report.steps.push({ step: 'moved by the visitor', message: /Your new time is requested/.test(movedText), waitingAgain: /Tour requested: not yet confirmed/.test(movedText) });
    await Promise.all([visitor.waitForNavigation({ waitUntil: 'networkidle' }), visitor.getByRole('button', { name: 'Cancel my tour' }).click()]);
    const cancelledText = await visitor.locator('main').innerText();
    report.steps.push({
        step: 'cancelled by the visitor',
        message: /Your tour is cancelled/.test(cancelledText),
        status: /Cancelled/.test(cancelledText),
        changesOffered: await visitor.getByRole('button', { name: /Move my tour|Cancel my tour/ }).count(),
    });
    await check(visitor, 'tour page @1440 (cancelled)', true);
    await visitorContext.close();

    // 360 px, JavaScript on: the picker, axe-core only.
    const phoneContext = await browser.newContext({ viewport: { width: 360, height: 800 }, reducedMotion: 'reduce' });
    const phone = await phoneContext.newPage();
    await phone.goto(`${base}/book-a-tour?property=${fixtures.ids.a70}`, { waitUntil: 'networkidle' });
    await check(phone, 'picker @360', true);
    await phone.goto(tourPage, { waitUntil: 'networkidle' });
    await check(phone, 'tour page @360', true);
    await phoneContext.close();

    // 360 px, JavaScript off: a request is sent and its pages open. The request, the move and the cancel
    // above used this minute's three sends, so the journey waits for the window to pass.
    // Reduced motion turns the site's smooth scrolling off, which otherwise keeps Playwright waiting for a
    // still-scrolling page before it can tick the consent box (as in verify-enquiry.mjs).
    await new Promise((resolve) => setTimeout(resolve, THROTTLE_WINDOW_MS));
    const noScriptContext = await browser.newContext({ viewport: { width: 360, height: 800 }, javaScriptEnabled: false, reducedMotion: 'reduce' });
    const noScript = await noScriptContext.newPage();
    await noScript.goto(`${base}/locations/noida/a-70`);
    await noScript.goto(new URL(await noScript.locator('main').getByRole('link', { name: 'Book a tour' }).first().getAttribute('href'), base).href);
    const sentWithoutScript = await requestTour(noScript, 'tour-nojs@fixture.test');
    report.steps.push({ step: 'requested @360 without JavaScript', at: sentWithoutScript.at, reference: sentWithoutScript.reference });
    await check(noScript, 'requested @360, JavaScript off', false);
    await noScriptContext.close();
} catch (error) {
    report.error = String(error).slice(0, 500);
    report.failures.push(`journey: ${report.error}`);
}

const [requested, confirmed, seen, moved, cancelled, withoutScript] = report.steps;
if (requested?.at !== '/book-a-tour/requested' || !requested?.reference || !requested?.waiting) report.failures.push(`not requested: ${JSON.stringify(requested)}`);
if (!/Tour confirmed/.test(confirmed?.notices ?? '')) report.failures.push(`not confirmed in the back office: ${JSON.stringify(confirmed)}`);
if (!seen?.confirmed || seen?.calendar !== 200 || !/^text\/calendar/.test(seen?.type ?? '') || !seen?.dtstart || seen?.visitorDetailsInFile) report.failures.push(`confirmation or calendar file wrong: ${JSON.stringify(seen)}`);
if (!moved?.message || !moved?.waitingAgain) report.failures.push(`not moved: ${JSON.stringify(moved)}`);
if (!cancelled?.message || !cancelled?.status || cancelled?.changesOffered !== 0) report.failures.push(`not cancelled: ${JSON.stringify(cancelled)}`);
if (withoutScript?.at !== '/book-a-tour/requested' || !withoutScript?.reference) report.failures.push(`not requested without JavaScript: ${JSON.stringify(withoutScript)}`);

await browser.close();
report.finishedAt = new Date().toISOString();
fs.writeFileSync(`${outDir}/tour.json`, JSON.stringify(report, null, 1));
console.log(JSON.stringify({ pages: report.pages.map((p) => `${p.label}: ${p.path} "${p.h1.join(' | ')}" overflow=${p.overflow} axe=${p.axe?.length ?? 'n/a'}`), steps: report.steps, failures: report.failures }, null, 1));
process.exit(report.failures.length ? 1 : 0);
