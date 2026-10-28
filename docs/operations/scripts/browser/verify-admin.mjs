// SmartGro browser checks: back-office screens that Phases 6 and 7 changed, on a server that uses the TEST database.
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
// Writes <outDir>/admin.json and prints a summary. Performance is measured by verify-twin-performance.mjs.
import fs from 'node:fs';
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
];

const browser = await chromium.launch();
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
    page.on('requestfinished', async (r) => { if (isThreeDRequest(r.url(), viewerFiles)) threeD.push({ path: new URL(r.url()).pathname, bytes: (await r.sizes()).responseBodySize }); });
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

await browser.close();
report.finishedAt = new Date().toISOString();
fs.writeFileSync(`${outDir}/admin.json`, JSON.stringify(report, null, 1));
console.log(JSON.stringify({
    chromium: report.chromium,
    screens: report.screens.map((s) => `${s.label} @${s.width}: ${s.status} ${s.finalPath} overflow=${s.overflow} external=${s.external.length} axe=${s.axe.map((v) => `${v.id}(${v.nodes})`).join(',') || 'none'}`),
    viewer: report.viewer.map((v) => `${v.compression} @${v.width} ${v.reducedMotion}: before=${v.beforeAsking} "${v.status}" in ${v.shownAfterMs} ms, loaded ${v.loaded.map((r) => `${r.path.split('/').pop()} ${r.bytes} B`).join(', ')}; drawn ${v.whole.drawn}/${v.whole.total} px (green ${v.whole.green}, amber ${v.whole.amber}); camera "${v.cameraStatus}" drawn ${v.camera.drawn}; axe=${v.axe.map((a) => `${a.id}(${a.nodes})`).join(',') || 'none'}`),
    failures: report.failures,
}, null, 1));
process.exit(report.failures.length ? 1 : 0);
