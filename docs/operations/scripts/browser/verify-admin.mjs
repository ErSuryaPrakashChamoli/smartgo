// SmartGro browser checks: back-office screens that Phases 5 to 8 changed, on a server that uses the TEST database.
//
//   node docs/operations/scripts/browser/verify-admin.mjs <baseUrl> <outDir> <fixtures.json>
//
// <fixtures.json> is the output of seed-test-fixtures.php (fixture IDs and staff session cookies for the
// test database; keep it out of the repository). For each screen at 360 and 1440 px it records the final
// address (so a redirect to the sign-in page cannot pass as a check), the status, horizontal overflow,
// external requests and axe-core results (WCAG 2.0, 2.1 and 2.2, A and AA rules); any violation fails the
// run and is reported with its elements. No screen
// may make a 3D request before anyone asks (three-d-files.mjs). Then, on the test-only 3D models' pages
// (Meshopt, and Draco), it presses "Show the 3D model" and checks what loads, that the model is drawn,
// that the camera positions work, with and without reduced motion, and axe-core once the viewer is shown.
// Then the loading states: axe-core while loading, stopping a download, and a download the server refuses;
// stopping after the download, while a decoder loads, and 20 rounds of Show and Stop (graphics contexts
// given back); a decoder that cannot load; "What changed" for an approver (OD-126); and two uploads
// through the form: a model at the limits (stored) and a file over 15 MB (refused); and setting up a
// location's floors, 40 spaces and an allocation through the screens (FR-INV-1, OD-127).
// Writes <outDir>/admin.json and prints a summary. Performance is measured by verify-twin-performance.mjs.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { isThreeDRequest, modelColours, threeDFiles } from './three-d-files.mjs';

const PLAYWRIGHT = process.env.PLAYWRIGHT_MODULE ?? '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const AXE = process.env.AXE_CORE ?? '/home/administrator/.npm/_npx/8003d8991b0d346b/node_modules/axe-core/axe.min.js';
const { chromium } = await import(PLAYWRIGHT);
const axeSource = fs.readFileSync(AXE, 'utf8');

const [base, outDir, fixturesFile] = process.argv.slice(2);
const fixtures = JSON.parse(fs.readFileSync(fixturesFile, 'utf8'));
const viewerFiles = threeDFiles();
const origin = new URL(base).origin;
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const SCREENS = [
    ['marketing', `/admin/properties/website-profiles/${fixtures.ids.a70_profile}/edit`, 200, 'property profile editor ("Getting here" section)'],
    ['marketing', `/admin/website/pages/${fixtures.ids.contact_page}/edit`, 200, 'page editor with the contact details section'],
    ['marketing', '/admin/website/pages/create', 200, 'new page editor'],
    ['operations', `/admin/properties/cities/${fixtures.ids.noida}/edit`, 200, 'city editor (reserved address)'],
    ['admin', '/admin', 200, 'dashboard'],
    ['sales', '/admin/sales/closing-reasons', 403, 'back-office 403 page'],
    ['admin', '/admin/sales/enquiries/999999', 404, 'back-office 404 page'],
    ['marketing', '/admin/website/twin-models', 200, '3D models list'],
    ['marketing', '/admin/website/twin-models/create', 200, '3D model upload form'],
    ['operations', `/admin/website/twin-models/${fixtures.ids.twin_model}`, 200, '3D model page, viewer not opened'],
    // Screens that show Filament's text for empty values ("Unassigned", "Never", "Nothing in progress", "—").
    ['admin', '/admin/sales/enquiries', 200, 'enquiries list (empty values)'],
    ['admin', `/admin/sales/enquiries/${fixtures.ids.lead}`, 200, 'enquiry view (empty values)'],
    ['admin', '/admin/settings/users', 200, 'staff list (empty values)'],
    ['admin', '/admin/settings/activities', 200, 'activity log (empty values)'],
    ['marketing', '/admin/website/pages', 200, 'pages list (empty values)'],
    ['marketing', fixtures.preview, 200, 'staff preview of a draft page'],
    // Inventory (Phase 5, OD-127): floors, spaces, seats and allocations.
    ['operations', `/admin/properties/properties/${fixtures.ids.a70}/edit`, 200, 'property page with floors and spaces'],
    ['operations', '/admin/properties/spaces', 200, 'spaces list'],
    ['operations', `/admin/properties/spaces/${fixtures.ids.space_zone}`, 200, 'hybrid zone page with seats and allocations'],
    ['operations', `/admin/properties/spaces/${fixtures.ids.space_cabin}/edit`, 200, 'space editor'],
    ['marketing', `/admin/properties/spaces/${fixtures.ids.space_cabin}`, 200, 'space page seen by marketing (no allocations)'],
    ['operations', '/admin/properties/space-types', 200, 'space types list'],
    // Pricing and the inventory import (Phase 5, OD-128).
    ['admin', '/admin/properties/price-plans', 200, 'price plans list'],
    ['admin', '/admin/properties/price-plans/create', 200, 'price plan form'],
    ['admin', '/admin/properties/offers', 200, 'offers list'],
    ['admin', '/admin/properties/offers/create', 200, 'offer form'],
    ['operations', '/admin/properties/inventory-imports', 200, 'inventory imports list'],
    ['operations', '/admin/properties/inventory-imports/upload', 200, 'inventory workbook upload form'],
    // Tours (Phase 8, OD-129): the list, a visitor's request and an enquiry with its tours.
    ['sales', '/admin/sales/tours', 200, 'tours list'],
    ['admin', `/admin/sales/tours/${fixtures.ids.tour}`, 200, 'tour page (request waiting for confirmation)'],
    ['marketing', '/admin/sales/tours', 403, 'tours list refused to marketing'],
    // Search titles and descriptions (FR-SEO-1, OD-131).
    ['marketing', '/admin/website/search-titles', 200, 'search titles and descriptions report'],
    ['sales', '/admin/website/search-titles', 403, 'search titles report refused to sales'],
    ['marketing', '/admin/website/missing-pages', 200, 'missing pages (404 monitor)'],
    // Reports (Phase 12, OD-132): totals only.
    ['operations', '/admin/reports/enquiries-and-tours', 200, 'enquiry and tour totals'],
    ['marketing', '/admin/reports/content-freshness', 200, 'content freshness'],
    ['sales', '/admin/reports/content-freshness', 403, 'content freshness refused to sales'],
];

const browser = await chromium.launch(process.env.CHROMIUM_EXECUTABLE ? { executablePath: process.env.CHROMIUM_EXECUTABLE } : {});
const report = { startedAt: new Date().toISOString(), base, chromium: browser.version(), threeDFiles: viewerFiles, screens: [], viewer: [], failures: [] };

for (const [who, path, expected, label] of SCREENS) {
    for (const width of [360, 1440]) {
        const context = await browser.newContext({ viewport: { width, height: 900 } });
        const [name, value] = fixtures.sessions[who].split('\t');
        await context.addCookies([{ name, value, url: base }]);
        const page = await context.newPage();
        const external = [];
        const threeD = [];
        page.on('request', (r) => { if (!r.url().startsWith(origin) && !r.url().startsWith('data:')) external.push(r.url()); });
        page.on('request', (r) => { if (isThreeDRequest(r.url(), viewerFiles)) threeD.push(r.url()); });
        const response = await page.goto(base + path, { waitUntil: 'networkidle' });
        await page.evaluate(axeSource);
        const axe = await page.evaluate(async (tags) => (await window.axe.run(document, { runOnly: { type: 'tag', values: tags } })).violations
            .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.slice(0, 3).map((n) => n.target.join(' ')) })), AXE_TAGS);
        const entry = {
            who, path, label, width, status: response?.status(), finalPath: new URL(page.url()).pathname,
            overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth),
            external, threeD, axe,
        };
        if (threeD.length) report.failures.push(`${label} @${width}: 3D requests before anyone asked: ${threeD.join(', ')}`);
        if (axe.length) report.failures.push(`${label} @${width}: axe ${axe.map((v) => `${v.id} at ${v.targets.join(', ')}`).join('; ')}`);
        if (entry.status !== expected || entry.finalPath !== path.split('?')[0]) report.failures.push(`${label} @${width}: status ${entry.status} at ${entry.finalPath}, expected ${expected} at ${path}`);
        report.screens.push(entry);
        await context.close();
    }
}

// The viewer on the test-only models' pages: nothing 3D before the button, then only the viewer module, the
// model file and the decoder the model needs (none for Meshopt, which is part of the viewer module; Draco's
// for the Draco model; never KTX2's); the model is drawn; a camera position changes the view. The Meshopt
// model at 360 px with reduced motion (a cut) and at 1440 px without (an animated move); the Draco model at
// 1440 px.
for (const [modelId, compression, width, reducedMotion] of [
    [fixtures.ids.twin_model, 'meshopt', 360, 'reduce'],
    [fixtures.ids.twin_model, 'meshopt', 1440, 'no-preference'],
    [fixtures.ids.twin_model_draco, 'draco', 1440, 'no-preference'],
]) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion });
    const [name, value] = fixtures.sessions.operations.split('\t');
    await context.addCookies([{ name, value, url: base }]);
    const page = await context.newPage();
    const threeD = [];
    const consoleErrors = [];
    // Recorded when each request starts; the size is added when known (the lookup is asynchronous).
    page.on('request', (r) => { if (isThreeDRequest(r.url(), viewerFiles)) threeD.push({ path: new URL(r.url()).pathname, bytes: null }); });
    page.on('requestfinished', async (r) => {
        if (!isThreeDRequest(r.url(), viewerFiles)) return;
        const entry = threeD.find((e) => e.path === new URL(r.url()).pathname && e.bytes === null);
        if (entry) entry.bytes = (await r.sizes().catch(() => ({ responseBodySize: null }))).responseBodySize;
    });
    page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });
    await page.goto(`${base}/admin/website/twin-models/${modelId}`, { waitUntil: 'networkidle' });
    const beforeAsking = threeD.length;
    const started = Date.now();
    await page.getByRole('button', { name: /Show the 3D model/ }).click();
    await page.waitForFunction(() => /^(Model shown|This browser|The model could not)/.test(document.querySelector('[data-twin-status]').textContent), null, { timeout: 60000 });
    const status = (await page.locator('[data-twin-status]').innerText()).trim();
    const shownAfterMs = Date.now() - started;
    await page.waitForTimeout(500);
    const stage = page.locator('[data-twin-stage]');
    const whole = modelColours(await stage.screenshot());
    await page.getByRole('button', { name: 'Camera position 1' }).click();
    await page.waitForTimeout(reducedMotion === 'reduce' ? 300 : 1200);
    const cameraStatus = (await page.locator('[data-twin-status]').innerText()).trim();
    const camera = modelColours(await stage.screenshot());
    await page.evaluate(axeSource);
    const axe = await page.evaluate(async (tags) => (await window.axe.run(document, { runOnly: { type: 'tag', values: tags } })).violations
        .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.slice(0, 3).map((n) => n.target.join(' ')) })), AXE_TAGS);
    const canvas = await page.locator('[data-twin-stage] canvas').evaluate((c) => ({ role: c.getAttribute('role'), label: c.getAttribute('aria-label'), width: c.width, height: c.height, tabIndex: c.tabIndex }));
    const entry = { compression, width, reducedMotion, beforeAsking, status, shownAfterMs, loaded: threeD, whole, cameraStatus, camera, canvas, axe, consoleErrors, overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) };
    report.viewer.push(entry);

    const label = `3D viewer, ${compression} model @${width} (${reducedMotion})`;
    if (beforeAsking) report.failures.push(`${label}: ${beforeAsking} 3D requests before the button was pressed`);
    if (!status.startsWith('Model shown')) report.failures.push(`${label}: ${status}`);
    const decoders = threeD.filter((r) => /draco|basis/.test(r.path));
    const expectedDecoders = compression === 'draco' ? decoders.length > 0 && decoders.every((r) => /draco/.test(r.path)) : decoders.length === 0;
    if (!expectedDecoders || !threeD.some((r) => /twin-viewer/.test(r.path)) || !threeD.some((r) => r.path.endsWith('/file'))) report.failures.push(`${label}: unexpected 3D requests ${JSON.stringify(threeD)}`);
    if (whole.green < 100 || whole.amber < 100) report.failures.push(`${label}: the test model is not drawn ${JSON.stringify(whole)}`);
    if (cameraStatus !== 'Camera position 1 shown.' || camera.drawn === whole.drawn) report.failures.push(`${label}: the camera position did not change the view (${cameraStatus})`);
    if (canvas.role !== 'img' || !canvas.label?.includes('3D model of SmartGro')) report.failures.push(`${label}: the view has no text alternative`);
    if (consoleErrors.length) report.failures.push(`${label}: console errors ${consoleErrors.join(' | ')}`);
    if (axe.length) report.failures.push(`${label}: axe ${axe.map((v) => `${v.id} at ${v.targets.join(', ')}`).join('; ')}`);
    if (entry.overflow > 0) report.failures.push(`${label}: horizontal overflow ${entry.overflow}px`);
    await context.close();
}

// Loading states (OD-121), on the model at the limits over a throttled connection, so loading takes long
// enough to watch: axe-core while loading (progress bar and "Stop loading" shown); stopping cancels the
// download and puts everything back; a download the server refuses gives the plain-language reason.
{
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    const [name, value] = fixtures.sessions.operations.split('\t');
    await context.addCookies([{ name, value, url: base }]);
    const page = await context.newPage();
    const aborted = [];
    page.on('requestfailed', (r) => { if (r.url().endsWith('/file')) aborted.push(r.failure()?.errorText); });
    await page.goto(`${base}/admin/website/twin-models/${fixtures.ids.twin_model_limits}`, { waitUntil: 'networkidle' });
    const cdp = await context.newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 256 * 1024, uploadThroughput: 256 * 1024 });

    await page.getByRole('button', { name: /Show the 3D model/ }).click();
    await page.waitForTimeout(2000);
    const whileLoading = {
        status: (await page.locator('[data-twin-status]').innerText()).trim(),
        progressVisible: await page.locator('[data-twin-progress]').isVisible(),
        progressValue: await page.locator('[data-twin-progress]').evaluate((p) => p.value),
        stopVisible: await page.getByRole('button', { name: 'Stop loading' }).isVisible(),
    };
    await page.evaluate(axeSource);
    const loadingAxe = await page.evaluate(async (tags) => (await window.axe.run(document, { runOnly: { type: 'tag', values: tags } })).violations
        .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.slice(0, 3).map((n) => n.target.join(' ')) })), AXE_TAGS);
    await page.getByRole('button', { name: 'Stop loading' }).click();
    await page.waitForTimeout(500);
    const afterStop = {
        status: (await page.locator('[data-twin-status]').innerText()).trim(),
        stageHidden: await page.locator('[data-twin-stage]').isHidden(),
        progressHidden: await page.locator('[data-twin-progress]').isHidden(),
        focusOnShow: await page.evaluate(() => document.activeElement?.matches('[data-twin-load]') ?? false),
        downloadAborted: aborted.length > 0,
    };

    await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    await page.route('**/twin-models/*/file', (route) => route.fulfill({ status: 500, body: '' }));
    await page.getByRole('button', { name: /Show the 3D model/ }).click();
    await page.waitForFunction(() => /^(Model shown|This browser|The model could not)/.test(document.querySelector('[data-twin-status]').textContent.trim()), null, { timeout: 60000 });
    const refused = { status: (await page.locator('[data-twin-status]').innerText()).trim(), showEnabled: await page.getByRole('button', { name: /Show the 3D model/ }).isEnabled() };
    report.loadingStates = { whileLoading, loadingAxe, afterStop, refused };

    if (!whileLoading.status.startsWith('Loading the viewer and the model') || !whileLoading.progressVisible || !whileLoading.stopVisible) report.failures.push(`loading state not shown: ${JSON.stringify(whileLoading)}`);
    if (loadingAxe.length) report.failures.push(`axe while loading: ${loadingAxe.map((v) => `${v.id} at ${v.targets.join(', ')}`).join('; ')}`);
    if (!afterStop.status.startsWith('Loading stopped') || !afterStop.stageHidden || !afterStop.progressHidden || !afterStop.focusOnShow || !afterStop.downloadAborted) report.failures.push(`stopping the download: ${JSON.stringify(afterStop)}`);
    if (!refused.status.startsWith('The model could not be downloaded') || !refused.showEnabled) report.failures.push(`a refused download: ${JSON.stringify(refused)}`);
    await context.close();
}

// Stopping after the download (OD-123): on the Draco model, with its decoder held back, "Stop loading" is
// pressed once the file has arrived; when the decoder then arrives, nothing may appear. Then 20 rounds of
// "Show" and "Stop loading": each round's graphics context must be given back, or the browser drops the
// oldest (a console warning) and its handler stops the round on screen with a false "graphics memory".
// Then a decoder that cannot be loaded, and a browser without WebGL, each give the plain-language failure
// and put everything back.
{
    const viewerState = (page) => page.evaluate(() => ({
        status: document.querySelector('[data-twin-status]').textContent.trim(),
        stageHidden: document.querySelector('[data-twin-stage]').hidden,
        canvases: document.querySelectorAll('[data-twin-stage] canvas').length,
    }));
    const openModel = async (modelId) => {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
        const [name, value] = fixtures.sessions.operations.split('\t');
        await context.addCookies([{ name, value, url: base }]);
        const page = await context.newPage();
        const consoleLines = [];
        page.on('console', (m) => consoleLines.push(m.text()));
        await page.goto(`${base}/admin/website/twin-models/${modelId}`, { waitUntil: 'networkidle' });

        return { context, page, consoleLines };
    };

    const decoding = await openModel(fixtures.ids.twin_model_draco);
    let releaseDecoder;
    const decoderHeld = new Promise((resolve) => { releaseDecoder = resolve; });
    let fileArrived = false;
    decoding.page.on('requestfinished', (r) => { if (r.url().endsWith('/file')) fileArrived = true; });
    await decoding.page.route(/DRACOLoader-[^/]+\.js$/, async (route) => { await decoderHeld; await route.continue(); });
    await decoding.page.getByRole('button', { name: /Show the 3D model/ }).click();
    await decoding.page.waitForFunction(() => document.querySelector('[data-twin-progress]').hidden === false);
    // Up to 30 s for the model file to arrive first (a loaded machine is slower), so Stop is pressed while the decoder loads.
    for (let i = 0; i < 300 && !fileArrived; i++) await decoding.page.waitForTimeout(100);
    await decoding.page.getByRole('button', { name: 'Stop loading' }).click();
    releaseDecoder();
    await decoding.page.waitForTimeout(3000);
    const stopWhileDecoding = { fileArrived, ...(await viewerState(decoding.page)) };
    await decoding.context.close();

    const cycling = await openModel(fixtures.ids.twin_model);
    let holdFile = true;
    await cycling.page.route('**/twin-models/*/file', async (route) => { if (holdFile) await new Promise((r) => setTimeout(r, 3000)); await route.continue().catch(() => {}); });
    const rounds = [];
    for (let round = 0; round < 20; round++) {
        await cycling.page.getByRole('button', { name: /Show the 3D model/ }).click();
        await cycling.page.waitForTimeout(400);
        const during = (await viewerState(cycling.page)).status;
        const stop = cycling.page.getByRole('button', { name: 'Stop loading' });
        const stopShown = await stop.isVisible();
        if (stopShown) await stop.click();
        rounds.push({ during, stopShown });
        if (!stopShown) break;
    }
    holdFile = false;
    await cycling.page.getByRole('button', { name: /Show the 3D model/ }).click();
    await cycling.page.waitForFunction(() => /^(Model shown|This browser|The model could not|The 3D view stopped)/.test(document.querySelector('[data-twin-status]').textContent.trim()), null, { timeout: 60000 }).catch(() => {});
    const showStopRounds = {
        rounds: rounds.length,
        stoppedCleanly: rounds.filter((r) => r.stopShown && r.during.startsWith('Loading the viewer')).length,
        contextLimitWarnings: cycling.consoleLines.filter((l) => /Too many active WebGL contexts/i.test(l)).length,
        afterwards: await viewerState(cycling.page),
    };
    await cycling.context.close();

    // A decoder that cannot be loaded: its module, or the WebAssembly file it loads in turn. Each in its own
    // page, since a failed module stays failed for the page's life.
    const decoderFailures = {};
    for (const [part, pattern] of [['module', /DRACOLoader-[^/]+\.js$/], ['WebAssembly', /draco_decoder-[^/]+\.wasm$/]]) {
        const failing = await openModel(fixtures.ids.twin_model_draco);
        await failing.page.route(pattern, (route) => route.abort('failed'));
        await failing.page.getByRole('button', { name: /Show the 3D model/ }).click();
        await failing.page.waitForFunction(() => /^(Model shown|This browser|The model could not)/.test(document.querySelector('[data-twin-status]').textContent.trim()), null, { timeout: 60000 }).catch(() => {});
        decoderFailures[part] = { ...(await viewerState(failing.page)), showEnabled: await failing.page.getByRole('button', { name: /Show the 3D model/ }).isEnabled() };
        await failing.context.close();
    }
    // A browser without WebGL.
    const noWebgl = await openModel(fixtures.ids.twin_model);
    await noWebgl.page.evaluate(() => {
        const getContext = HTMLCanvasElement.prototype.getContext;
        HTMLCanvasElement.prototype.getContext = function (type, ...rest) { return /webgl/i.test(type) ? null : getContext.call(this, type, ...rest); };
    });
    await noWebgl.page.getByRole('button', { name: /Show the 3D model/ }).click();
    await noWebgl.page.waitForFunction(() => /^(Model shown|This browser|The model could not)/.test(document.querySelector('[data-twin-status]').textContent.trim()), null, { timeout: 60000 }).catch(() => {});
    const withoutWebgl = await viewerState(noWebgl.page);
    await noWebgl.context.close();
    report.stopping = { stopWhileDecoding, showStopRounds, decoderFailures, withoutWebgl };

    for (const [part, outcome] of Object.entries(decoderFailures)) {
        if (!outcome.status.startsWith('The model could not be shown') || !outcome.stageHidden || outcome.canvases !== 0 || !outcome.showEnabled) report.failures.push(`a decoder ${part} that cannot load: ${JSON.stringify(outcome)}`);
    }
    if (!withoutWebgl.status.startsWith('This browser or device cannot show 3D') || !withoutWebgl.stageHidden || withoutWebgl.canvases !== 0) report.failures.push(`a browser without WebGL: ${JSON.stringify(withoutWebgl)}`);

    if (!stopWhileDecoding.fileArrived || !stopWhileDecoding.status.startsWith('Loading stopped') || !stopWhileDecoding.stageHidden || stopWhileDecoding.canvases !== 0) report.failures.push(`stopping while the decoder loads: ${JSON.stringify(stopWhileDecoding)}`);
    if (showStopRounds.stoppedCleanly !== 20 || showStopRounds.contextLimitWarnings || !showStopRounds.afterwards.status.startsWith('Model shown')) report.failures.push(`20 rounds of Show and Stop: ${JSON.stringify(showStopRounds)}`);
}

// "What changed" (UJ-8, OD-126): the approver opens the about page, whose second version waits for approval,
// and sees what it changes compared with the live version, at 360 and 1440 px, with axe-core on the open window.
report.changes = [];
for (const width of [360, 1440]) {
    const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
    const [name, value] = fixtures.sessions.admin.split('\t');
    await context.addCookies([{ name, value, url: base }]);
    const page = await context.newPage();
    await page.goto(`${base}/admin/website/pages/${fixtures.ids.about_page}/edit`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'What changed' }).click();
    const dialog = page.locator('.fi-modal-window:visible').last();
    await dialog.waitFor();
    await page.waitForTimeout(300);
    const text = (await dialog.innerText()).replace(/\s+/g, ' ');
    await page.evaluate(axeSource);
    const axe = await page.evaluate(async (tags) => (await window.axe.run(document, { runOnly: { type: 'tag', values: tags } })).violations
        .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.slice(0, 3).map((n) => n.target.join(' ')) })), AXE_TAGS);
    const entry = {
        width,
        heading: (await dialog.locator('.fi-modal-heading').innerText()).trim(),
        listsTitle: text.includes('Title') && text.includes('TEST FIXTURE about page, revised'),
        listsSection: text.includes('Section 1 (Text section): body') && text.includes('TEST FIXTURE text, revised.'),
        overflow: await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth),
        axe,
    };
    report.changes.push(entry);
    if (entry.heading !== 'What version 2 changes' || !entry.listsTitle || !entry.listsSection) report.failures.push(`"What changed" @${width}: ${JSON.stringify(entry)}`);
    if (entry.overflow > 0) report.failures.push(`"What changed" @${width}: horizontal overflow ${entry.overflow}px`);
    if (axe.length) report.failures.push(`"What changed" @${width}: axe ${axe.map((v) => `${v.id} at ${v.targets.join(', ')}`).join('; ')}`);
    await context.close();
}

// Setting up a location in one session (FR-INV-1, OD-127): operations staff add three floors to A-71 (which
// has none), then 40 cabins in one step, then record an allocation of the first cabin; a second allocation
// of the same cabin for the same dates and taking it out of use are both refused with the reason. All
// through the screens at 1440 px, with axe-core on the space's page afterwards.
report.setup = {};
{
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    const [name, value] = fixtures.sessions.operations.split('\t');
    await context.addCookies([{ name, value, url: base }]);
    const page = await context.newPage();
    const started = Date.now();
    const modal = () => page.locator('.fi-modal-window:visible').last();
    const submit = async () => { await modal().getByRole('button', { name: /^(Submit|Confirm)$/ }).click(); await page.waitForLoadState('networkidle'); await page.waitForTimeout(400); };
    // Filament stacks notifications; read them all, newest included. With a pattern, wait (up to 10 s) until
    // a notice matching it is shown, so a slow response is not read before its notice appears.
    const lastNotice = async (pattern = null) => {
        const read = async () => (await page.locator('.fi-no-notification').allInnerTexts().catch(() => [])).join(' | ').replace(/\s+/g, ' ').trim();
        for (let waited = 0; pattern && !pattern.test(await read()) && waited < 10000; waited += 250) await page.waitForTimeout(250);
        return read();
    };
    const showLazy = async (locator) => { await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight)); await locator.waitFor({ timeout: 20000 }); };
    await page.goto(`${base}/admin/properties/properties/${fixtures.ids.a71}/edit`, { waitUntil: 'networkidle' });
    // Relation managers load lazily, and several are shown as tabs (Floors first, then Spaces).
    report.setup.emptyState = await showLazy(page.getByText('No floors recorded')).then(() => true, () => false);
    for (const [level, label] of [[0, 'TEST FIXTURE ground'], [1, 'TEST FIXTURE first'], [2, 'TEST FIXTURE second']]) {
        await page.getByRole('button', { name: 'Add floor' }).click();
        await modal().waitFor();
        await modal().getByLabel('Level').fill(String(level));
        await modal().getByLabel('Name').fill(label);
        await modal().getByLabel('Reason for the change').fill('TEST FIXTURE floor list');
        await submit();
    }
    report.setup.floorsListed = await page.getByText('TEST FIXTURE second').isVisible();
    await page.getByRole('tab', { name: 'Spaces' }).click();
    await page.waitForLoadState('networkidle');
    await showLazy(page.getByRole('button', { name: 'Add several spaces' }));
    await page.getByRole('button', { name: 'Add several spaces' }).click();
    await modal().waitFor();
    await modal().getByLabel('Floor').selectOption({ label: 'Level 1 (TEST FIXTURE first)' });
    await modal().getByLabel('Space type').selectOption({ label: 'Private cabin or office' });
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    report.setup.modeSuggested = await modal().getByLabel('Counted as').inputValue();
    await modal().getByLabel('How many spaces').fill('40');
    await modal().getByLabel('Code prefix').fill('TF71-C');
    await modal().getByLabel('Seats').fill('2');
    await modal().getByLabel('Reason for the change').fill('TEST FIXTURE inventory');
    await submit();
    await page.locator('.fi-no-notification', { hasText: 'Spaces added' }).waitFor({ timeout: 60000 }).catch(() => {});
    report.setup.spacesNotice = await lastNotice();
    report.setup.spacesListed = await page.getByText('TF71-C10').first().isVisible().catch(() => false);
    report.setup.setupMs = Date.now() - started;
    await page.getByText('TF71-C01').first().click();
    await page.waitForURL(/\/admin\/properties\/spaces\/\d+$/);
    await page.waitForLoadState('networkidle');
    await showLazy(page.getByRole('button', { name: 'Record allocation' }));
    const today = new Date().toISOString().slice(0, 10);
    for (const reference of ['TF71-AG-1', 'TF71-AG-2']) {
        await page.getByRole('button', { name: 'Record allocation' }).click();
        await modal().waitFor();
        await modal().getByLabel('Agreement reference').fill(reference);
        await modal().getByLabel('From').fill(today);
        await modal().getByLabel('Reason').fill('TEST FIXTURE agreement');
        await submit();
        report.setup[reference] = await lastNotice(reference === 'TF71-AG-1' ? /Allocation recorded|Not saved|Not allowed/ : /Not saved|Not allowed/);
        if (reference === 'TF71-AG-2') { await modal().getByRole('button', { name: 'Cancel' }).click(); await page.waitForTimeout(400); }
    }
    report.setup.allocationListed = await page.getByText('TF71-AG-1').first().isVisible();
    await page.getByRole('button', { name: 'Change status' }).click();
    await modal().waitFor();
    await modal().getByLabel('New status').selectOption({ label: 'Under maintenance' });
    await modal().getByLabel('Reason').fill('TEST FIXTURE repairs');
    await submit();
    report.setup.statusRefusal = await lastNotice(/allocations now or later|Status changed|Not allowed/);
    await modal().getByRole('button', { name: 'Cancel' }).click();
    await page.reload({ waitUntil: 'networkidle' });
    report.setup.availability = ((await page.locator('main').innerText().catch(() => '')).match(/(Occupied|Free)[^\n]*/) ?? [''])[0].trim();
    await page.evaluate(axeSource);
    report.setup.axe = await page.evaluate(async (tags) => (await window.axe.run(document, { runOnly: { type: 'tag', values: tags } })).violations
        .map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, targets: v.nodes.slice(0, 3).map((n) => n.target.join(' ')) })), AXE_TAGS);
    report.setup.overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    await page.goto(`${base}/admin/properties/spaces?search=TF71-C40`, { waitUntil: 'networkidle' });
    report.setup.fortiethFound = await page.getByText('TF71-C40').first().isVisible().catch(() => false);
    const s = report.setup;
    if (!s.emptyState || !s.floorsListed || s.modeSuggested !== 'exclusive' || !s.spacesListed || !s.spacesNotice.includes('Spaces added')
        || !s['TF71-AG-1'].includes('Allocation recorded') || !s['TF71-AG-2'].includes('already allocated') || !s.statusRefusal.includes('allocations now or later')
        || !s.allocationListed || !s.fortiethFound || !s.availability.startsWith('Occupied')) report.failures.push(`location set-up: ${JSON.stringify(s)}`);
    if (s.overflow > 0) report.failures.push(`location set-up: horizontal overflow ${s.overflow}px`);
    if (s.axe.length) report.failures.push(`location set-up: axe ${s.axe.map((v) => `${v.id} at ${v.targets.join(', ')}`).join('; ')}`);
    await context.close();
}

// Uploads through the back-office form (OD-122 D): the test-only model at the limits (14.85 MB, 500,000
// triangles) is stored and opens on its page; a file 4 bytes over 15 MB is refused with the plain-language
// reason. Real browser uploads, through Livewire's temporary upload, the checks and the media library.
report.uploads = [];
for (const [kind, file, expect] of [['at the limits', fixtures.upload_files.at_limits, 'stored'], ['4 bytes over 15 MB', fixtures.upload_files.oversized, 'refused']]) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    const [name, value] = fixtures.sessions.marketing.split('\t');
    await context.addCookies([{ name, value, url: base }]);
    const page = await context.newPage();
    await page.goto(`${base}/admin/website/twin-models/create`, { waitUntil: 'networkidle' });
    // A copy in the temporary folder: a sandboxed (snap) browser reads only its own area (TMPDIR there).
    const folder = fs.mkdtempSync(path.join(os.tmpdir(), 'upload-'));
    const copy = path.join(folder, path.basename(file));
    fs.copyFileSync(file, copy);
    const started = Date.now();
    await page.locator('input[type=file]').setInputFiles(copy);
    const create = page.getByRole('button', { name: 'Create', exact: true });
    // FilePond marks the file once Livewire's temporary upload is complete.
    await page.locator('.filepond--item[data-filepond-item-state="processing-complete"]').waitFor({ timeout: 120000 });
    const uploadedMs = Date.now() - started;
    await page.getByLabel('Property').selectOption({ index: 1 });
    await page.getByLabel('How it was made').selectOption('conceptual_model');
    await page.getByLabel('Title').fill(`TEST FIXTURE upload through the form (${kind})`);
    await create.click();
    const outcome = await Promise.race([
        page.waitForURL(/\/admin\/website\/twin-models\/\d+$/, { timeout: 120000 }).then(() => 'stored'),
        page.getByText(/The file is [\d.]+ MB; the limit is/).waitFor({ timeout: 120000 }).then(() => 'refused'),
    ]).catch(() => 'neither');
    const entry = { kind, expect, outcome, uploadedMs, finalPath: new URL(page.url()).pathname };
    if (outcome === 'stored') entry.checksShown = await page.getByText('Checks passed').isVisible();
    if (outcome === 'refused') entry.reason = (await page.getByText(/The file is [\d.]+ MB; the limit is/).first().innerText()).trim();
    report.uploads.push(entry);
    fs.rmSync(folder, { recursive: true, force: true });
    if (outcome !== expect || (outcome === 'stored' && !entry.checksShown)) report.failures.push(`upload through the form (${kind}): ${JSON.stringify(entry)}`);
    await context.close();
}

await browser.close();
report.finishedAt = new Date().toISOString();
fs.writeFileSync(`${outDir}/admin.json`, JSON.stringify(report, null, 1));
console.log(JSON.stringify({
    chromium: report.chromium,
    screens: report.screens.map((s) => `${s.label} @${s.width}: ${s.status} ${s.finalPath} overflow=${s.overflow} external=${s.external.length} axe=${s.axe.map((v) => `${v.id}(${v.nodes})`).join(',') || 'none'}`),
    viewer: report.viewer.map((v) => `${v.compression} @${v.width} ${v.reducedMotion}: before=${v.beforeAsking} "${v.status}" in ${v.shownAfterMs} ms, loaded ${v.loaded.map((r) => `${r.path.split('/').pop()} ${r.bytes} B`).join(', ')}; drawn ${v.whole.drawn}/${v.whole.total} px (green ${v.whole.green}, amber ${v.whole.amber}); camera "${v.cameraStatus}" drawn ${v.camera.drawn}; axe=${v.axe.map((a) => `${a.id}(${a.nodes})`).join(',') || 'none'}`),
    loadingStates: report.loadingStates,
    stopping: report.stopping,
    changes: report.changes,
    setup: report.setup,
    uploads: report.uploads,
    failures: report.failures,
}, null, 1));
process.exit(report.failures.length ? 1 : 0);
