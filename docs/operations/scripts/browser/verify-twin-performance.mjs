// SmartGro browser checks: the 3D viewer with test-only models of different sizes (OD-119: "Test
// representative models and viewer performance on mobile and desktop"), on a server that uses the TEST
// database.
//
//   node docs/operations/scripts/browser/verify-twin-performance.mjs <baseUrl> <outDir> <fixtures.json>
//
// Models (seed-test-fixtures.php, Tests\Support\Twin\TestGlb): small with Meshopt, small with Draco, a
// typical size (100,000 triangles, 50 pieces drawn, four 1024-pixel textures, 2.72 MB) and one at the
// limits (500,000 triangles, 250 pieces drawn, eight 2048-pixel textures, 33,554,432 texture pixels, just
// under 15 MB). For each model and profile it opens the model's back-office page, presses "Show the 3D
// model" and records: 3D requests before the press (there must be none); the time from the press to "Model
// shown"; the 3D files and bytes loaded (resource timing); the renders needed to show the model (one) and
// the draw calls and triangles it asked WebGL to draw (counted at the WebGL calls, compared with the
// triangles counted at upload); the renders and frame times while the view moves to a camera position;
// the JavaScript heap; and the WebGL renderer.
//
// Profiles. "desktop": 1440 × 900, no throttling. "phone, slow 4G": 360 × 800 at device pixel ratio 2,
// touch, the CPU slowed 4× and the network throttled as Lighthouse's DevTools method does for its mobile
// run (562.5 ms request latency, 1,474.56 kbit/s down, 675 kbit/s up).
//
// These are measurements in headless Chromium on this machine, which draws with a software renderer: not a
// phone, not a GPU. The local server does not compress responses (the viewer module is 725 kB here and
// about 193 kB with gzip). They show how size drives loading time; they do not show that a model meets
// NFR-PERF-4 on a real mid-range phone, and the 5-second figure is printed for comparison only. The CPU
// slowdown applies to the page, not to the software renderer, which runs in the GPU process. A run fails
// only when a model is not shown, something 3D loads before the press, the model file is not loaded, it
// takes other than one render to show the model, the triangles drawn differ from the triangles counted at
// upload, or the page logs an error. Writes <outDir>/performance.json and prints a
// summary.
import fs from 'node:fs';
import os from 'node:os';
import { isThreeDRequest, threeDFiles } from './three-d-files.mjs';

const PLAYWRIGHT = process.env.PLAYWRIGHT_MODULE ?? '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const { chromium } = await import(PLAYWRIGHT);

const [base, outDir, fixturesFile] = process.argv.slice(2);
const fixtures = JSON.parse(fs.readFileSync(fixturesFile, 'utf8'));
const viewerFiles = threeDFiles();
const NFR_PERF_4_MS = 5000;
const MODELS = [
    ['twin_model', 'small, Meshopt'],
    ['twin_model_draco', 'small, Draco'],
    ['twin_model_typical', 'typical size'],
    ['twin_model_limits', 'at the limits'],
];
const PROFILES = [
    { name: 'desktop', context: { viewport: { width: 1440, height: 900 } }, cpu: 1, network: null },
    {
        name: 'phone, slow 4G',
        context: { viewport: { width: 360, height: 800 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
        cpu: 4,
        network: { offline: false, latency: 562.5, downloadThroughput: (1474.56 * 1024) / 8, uploadThroughput: (675 * 1024) / 8 },
    },
];

// Counts each render (three.js clears the canvas once at its start) with its draw calls and the
// triangles it asks WebGL to draw.
const COUNT_DRAWS = () => {
    const renders = [];
    let current = null;
    const TRIANGLES = 4;
    window.__twinRenders = () => [...renders, ...(current ? [current] : [])].filter((render) => render.calls > 0);
    for (const proto of [window.WebGLRenderingContext?.prototype, window.WebGL2RenderingContext?.prototype].filter(Boolean)) {
        const clear = proto.clear;
        proto.clear = function (mask) {
            if (current) renders.push(current);
            current = { at: performance.now(), calls: 0, triangles: 0 };
            return clear.call(this, mask);
        };
        const drawElements = proto.drawElements;
        proto.drawElements = function (mode, count, ...rest) {
            if (current) {
                current.calls++;
                if (mode === TRIANGLES) current.triangles += count / 3;
            }
            return drawElements.call(this, mode, count, ...rest);
        };
        const drawArrays = proto.drawArrays;
        proto.drawArrays = function (mode, first, count) {
            if (current) {
                current.calls++;
                if (mode === TRIANGLES) current.triangles += count / 3;
            }
            return drawArrays.call(this, mode, first, count);
        };
    }
};

const browser = await chromium.launch();
const machine = () => ({ cpus: os.cpus().length, cpu: os.cpus()[0]?.model, loadAverage: os.loadavg().map((load) => Math.round(load * 100) / 100) });
const report = { startedAt: new Date().toISOString(), base, chromium: browser.version(), machineAtStart: machine(), profiles: PROFILES, uploadChecks: fixtures.twin_checks, runs: [], failures: [] };

for (const profile of PROFILES) {
    for (const [key, label] of MODELS) {
        const context = await browser.newContext({ ...profile.context, reducedMotion: 'no-preference' });
        const [name, value] = fixtures.sessions.operations.split('\t');
        await context.addCookies([{ name, value, url: base }]);
        await context.addInitScript(COUNT_DRAWS);
        const page = await context.newPage();
        const cdp = await context.newCDPSession(page);
        await cdp.send('Performance.enable');
        if (profile.cpu > 1) await cdp.send('Emulation.setCPUThrottlingRate', { rate: profile.cpu });
        if (profile.network) {
            await cdp.send('Network.enable');
            await cdp.send('Network.emulateNetworkConditions', profile.network);
        }

        const consoleErrors = [];
        let beforePress = 0;
        let pressed = false;
        page.on('request', (r) => { if (!pressed && isThreeDRequest(r.url(), viewerFiles)) beforePress++; });
        page.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text()); });

        await page.goto(`${base}/admin/website/twin-models/${fixtures.ids[key]}`, { waitUntil: 'networkidle', timeout: 180000 });
        pressed = true;
        const shown = await page.evaluate(() => new Promise((resolve) => {
            const status = document.querySelector('[data-twin-status]');
            const started = performance.now();
            const observer = new MutationObserver(() => {
                if (/^(Model shown|This browser|The model could not)/.test(status.textContent.trim())) {
                    observer.disconnect();
                    resolve({ ms: Math.round(performance.now() - started), status: status.textContent.trim(), pressedAt: started });
                }
            });
            observer.observe(status, { childList: true, characterData: true, subtree: true });
            document.querySelector('[data-twin-load]').click();
        }));
        await page.waitForTimeout(1000);

        // The 3D files, from the page's resource timing (encoded bytes, as sent; this server does not compress).
        const loaded = (await page.evaluate(() => performance.getEntriesByType('resource').map((entry) => ({ url: entry.name, bytes: entry.encodedBodySize, ms: Math.round(entry.duration) }))))
            .filter((entry) => isThreeDRequest(entry.url, viewerFiles))
            .map((entry) => ({ path: new URL(entry.url).pathname.split('/').pop(), bytes: entry.bytes, ms: entry.ms }));

        // Renders from the press until the move: the viewer should draw the whole model exactly once.
        const moveStartedAt = await page.evaluate(() => performance.now());
        const beforeMove = (await page.evaluate(() => window.__twinRenders())).filter((render) => render.at >= shown.pressedAt - 1 && render.at < moveStartedAt);
        const whole = beforeMove.reduce((most, render) => (render.triangles > most.triangles ? render : most), { calls: 0, triangles: 0 });

        // The move to a camera position (700 ms when motion is not reduced): one render per frame.
        if (shown.status.startsWith('Model shown')) {
            await page.evaluate(() => document.querySelector('[data-twin-camera="SG_CAM_1"]').click());
            await page.waitForTimeout(3000);
        }
        const moveRenders = (await page.evaluate(() => window.__twinRenders())).filter((render) => render.at >= moveStartedAt).map((render) => render.at);
        const intervals = moveRenders.slice(1).map((at, i) => at - moveRenders[i]).sort((a, b) => a - b);
        const metrics = Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map((m) => [m.name, m.value]));
        const renderer = await page.evaluate(() => {
            const gl = document.createElement('canvas').getContext('webgl2');
            const info = gl?.getExtension('WEBGL_debug_renderer_info');
            return info ? gl.getParameter(info.UNMASKED_RENDERER_WEBGL) : gl?.getParameter(gl.RENDERER) ?? 'no WebGL 2';
        });

        const uploaded = fixtures.twin_checks[key];
        const entry = {
            model: label, profile: profile.name, fileBytes: uploaded.bytes, trianglesCounted: uploaded.triangles, beforePress,
            status: shown.status, shownMs: shown.ms, loaded, threeDBytes: loaded.reduce((sum, r) => sum + r.bytes, 0),
            rendersBeforeMove: beforeMove.length, drawnWhole: { calls: whole.calls, triangles: whole.triangles },
            move: { renders: moveRenders.length, medianFrameMs: Math.round(intervals[Math.floor(intervals.length / 2)] ?? 0), worstFrameMs: Math.round(intervals.at(-1) ?? 0) },
            jsHeapMB: Math.round((metrics.JSHeapUsedSize ?? 0) / 1048576), renderer, consoleErrors,
        };
        report.runs.push(entry);

        const where = `${label} (${profile.name})`;
        if (beforePress) report.failures.push(`${where}: ${beforePress} 3D requests before the press`);
        if (!shown.status.startsWith('Model shown')) report.failures.push(`${where}: ${shown.status}`);
        if (entry.rendersBeforeMove !== 1) report.failures.push(`${where}: ${entry.rendersBeforeMove} renders to show the model, expected 1`);
        if (entry.drawnWhole.triangles !== uploaded.triangles) report.failures.push(`${where}: ${entry.drawnWhole.triangles} triangles drawn, ${uploaded.triangles} counted at upload`);
        if (!loaded.some((r) => r.path === 'file')) report.failures.push(`${where}: the model file is not among the files loaded`);
        if (consoleErrors.length) report.failures.push(`${where}: console errors ${consoleErrors.join(' | ')}`);
        await context.close();
    }
}

await browser.close();
report.finishedAt = new Date().toISOString();
report.machineAtEnd = machine();
fs.writeFileSync(`${outDir}/performance.json`, JSON.stringify(report, null, 1));
console.log(JSON.stringify({
    chromium: report.chromium,
    renderer: report.runs[0]?.renderer,
    machine: { start: report.machineAtStart, end: report.machineAtEnd },
    uploadChecks: Object.fromEntries(Object.entries(report.uploadChecks).map(([key, c]) => [key, `${c.bytes} B, ${c.triangles} triangles, upload with checks ${c.upload_ms} ms`])),
    runs: report.runs.map((r) => `${r.model} | ${r.profile}: shown in ${r.shownMs} ms${r.profile.startsWith('phone') ? (r.shownMs <= NFR_PERF_4_MS ? ' (under 5 s here)' : ' (over 5 s here)') : ''}; ${r.threeDBytes} B loaded (${r.loaded.map((l) => `${l.path} ${l.ms} ms`).join(', ')}); ${r.rendersBeforeMove} render(s) of ${r.drawnWhole.calls} calls, ${r.drawnWhole.triangles} triangles; move: ${r.move.renders} renders, median frame ${r.move.medianFrameMs} ms, worst ${r.move.worstFrameMs} ms; heap ${r.jsHeapMB} MB`),
    failures: report.failures,
}, null, 1));
process.exit(report.failures.length ? 1 : 0);
