// SmartGro browser checks: the 3D viewer with test-only models of different sizes (OD-119: "Test
// representative models and viewer performance on mobile and desktop"; OD-121: measure each stage), on a
// server that uses the TEST database.
//
//   node docs/operations/scripts/browser/verify-twin-performance.mjs <baseUrl> <outDir> <fixtures.json>
//   TWIN_PERF_MODELS=twin_model,twin_model_draco TWIN_PERF_PROFILES='desktop;phone, slow 4G' node …   (a subset)
//
// Models (seed-test-fixtures.php, Tests\Support\Twin\TestGlb): small with Meshopt, small with Draco, a
// typical size (100,000 triangles, 50 pieces drawn, four 1024-pixel textures, 2.72 MB) and one at the
// limits (500,000 triangles, 250 pieces drawn, eight 2048-pixel textures, 33,554,432 texture pixels, just
// under 15 MB). Their Meshopt data is stored raw (TestGlb), so they are larger than a supplier's
// compressed file with the same triangles would be. For each model and profile it opens the model's
// back-office page, presses "Show the 3D model" and records:
// - 3D requests before the press (there must be none);
// - each stage, from the press: the viewer module downloaded and ready, the renderer ready, the model
//   downloaded, the decoders downloaded and ready, the model decoded into a scene, the first render, the
//   first frame after it, and the viewer usable ("Model shown"), from the viewer's performance marks and
//   the page's resource timing; and the request waterfall (start, end and bytes of each 3D file);
// - the renders needed to show the model (one) and the draw calls and triangles it asked WebGL to draw
//   (counted at the WebGL calls, compared with the triangles counted at upload);
// - the renders and frame times while the view moves to a camera position, the JavaScript heap and the
//   WebGL renderer.
//
// Profiles. "desktop": 1440 × 900, no throttling. "phone, slow 4G": 360 × 800 at device pixel ratio 2,
// touch, the page's CPU slowed 4×, and the network throttled as Lighthouse's DevTools method does for its
// mobile run (562.5 ms request latency, 1,474.56 kbit/s down, 675 kbit/s up). "phone, slow 4G, gzip": the
// same, through a small proxy in this script that gzips HTML, CSS, JavaScript, JSON, SVG and WebAssembly as
// a production web server would be configured to (DEPLOYMENT_RUNBOOK); 3D model files are passed through
// unchanged. The local PHP server itself compresses nothing. The model at the limits is left out of that
// profile: through the proxy, headless Chromium's throttled 15 MB download stalled without an error in 5
// of 7 attempts on 2026-10-28 (at 6.4 to 12.3 MB), while the same download finished directly from the
// server and through the proxy with curl. The proxy compresses no model file, so the case would show
// nothing the smaller models do not.
//
// These are measurements in headless Chromium on this machine, which draws with a software renderer: not a
// phone, not a GPU, and the CPU slowdown does not apply to the renderer, which runs in the GPU process.
// They show where the time goes and how size drives it; they do not show that a model meets NFR-PERF-4 on a
// real mid-range phone, and the 5-second figure is printed for comparison only. A run fails only when a
// model is not shown (within 300 s), something 3D loads before the press, the model file is not loaded, it takes other
// than one render to show the model, the triangles drawn differ from the triangles counted at upload, or
// the page logs an error. Writes <outDir>/performance.json and prints a summary.
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import zlib from 'node:zlib';
import { isThreeDRequest, threeDFiles } from './three-d-files.mjs';

const PLAYWRIGHT = process.env.PLAYWRIGHT_MODULE ?? '/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs';
const { chromium } = await import(PLAYWRIGHT);

const [base, outDir, fixturesFile] = process.argv.slice(2);
const fixtures = JSON.parse(fs.readFileSync(fixturesFile, 'utf8'));
const viewerFiles = threeDFiles();
const NFR_PERF_4_MS = 5000;
const SHOWN_TIMEOUT_MS = 300000;
const SLOW_4G = { offline: false, latency: 562.5, downloadThroughput: (1474.56 * 1024) / 8, uploadThroughput: (675 * 1024) / 8 };
const PHONE = { viewport: { width: 360, height: 800 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true };
const MODELS = [
    ['twin_model', 'small, Meshopt'],
    ['twin_model_draco', 'small, Draco'],
    ['twin_model_typical', 'typical size'],
    ['twin_model_limits', 'at the limits'],
].filter(([key]) => !process.env.TWIN_PERF_MODELS || process.env.TWIN_PERF_MODELS.split(',').includes(key));
const PROFILES = [
    { name: 'desktop', context: { viewport: { width: 1440, height: 900 } }, cpu: 1, network: null, gzip: false },
    { name: 'phone, slow 4G', context: PHONE, cpu: 4, network: SLOW_4G, gzip: false },
    { name: 'phone, slow 4G, gzip', context: PHONE, cpu: 4, network: SLOW_4G, gzip: true, skip: ['twin_model_limits'] },
].filter(({ name }) => !process.env.TWIN_PERF_PROFILES || process.env.TWIN_PERF_PROFILES.split(';').includes(name));

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

// A proxy to the test server that gzips text and WebAssembly responses, as a production web server would.
const COMPRESSIBLE = /^(text\/|application\/(javascript|json|wasm)|image\/svg\+xml)/;
function startCompressingProxy(upstream) {
    const target = new URL(upstream);
    const server = http.createServer((request, response) => {
        const forward = http.request({ host: target.hostname, port: target.port, method: request.method, path: request.url, headers: request.headers }, (answer) => {
            const compress = /\bgzip\b/.test(request.headers['accept-encoding'] ?? '') && COMPRESSIBLE.test(answer.headers['content-type'] ?? '') && !answer.headers['content-encoding'];
            if (!compress) {
                response.writeHead(answer.statusCode, answer.headers);
                answer.pipe(response);
                return;
            }
            const headers = { ...answer.headers, 'content-encoding': 'gzip', vary: 'Accept-Encoding' };
            delete headers['content-length'];
            response.writeHead(answer.statusCode, headers);
            answer.pipe(zlib.createGzip({ level: 6 })).pipe(response);
        });
        forward.on('error', () => {
            response.writeHead(502);
            response.end();
        });
        request.pipe(forward);
    });

    return new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(server)));
}

const proxy = PROFILES.some((profile) => profile.gzip) ? await startCompressingProxy(base) : null;
const proxyBase = proxy ? `http://127.0.0.1:${proxy.address().port}` : null;
const browser = await chromium.launch(process.env.CHROMIUM_EXECUTABLE ? { executablePath: process.env.CHROMIUM_EXECUTABLE } : {});
const machine = () => ({ cpus: os.cpus().length, cpu: os.cpus()[0]?.model, loadAverage: os.loadavg().map((load) => Math.round(load * 100) / 100) });
const report = { startedAt: new Date().toISOString(), base, chromium: browser.version(), machineAtStart: machine(), profiles: PROFILES, uploadChecks: fixtures.twin_checks, runs: [], failures: [] };

for (const profile of PROFILES) {
    for (const [key, label] of MODELS.filter(([model]) => !(profile.skip ?? []).includes(model))) {
        const origin = profile.gzip ? proxyBase : base;
        const context = await browser.newContext({ ...profile.context, reducedMotion: 'no-preference' });
        const [name, value] = fixtures.sessions.operations.split('\t');
        await context.addCookies([{ name, value, url: origin }]);
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

        await page.goto(`${origin}/admin/website/twin-models/${fixtures.ids[key]}`, { waitUntil: 'networkidle', timeout: 180000 });
        pressed = true;
        // A case that is not shown within SHOWN_TIMEOUT_MS fails instead of holding up the run.
        const timedOut = new Promise((resolve) => setTimeout(() => resolve({ ms: SHOWN_TIMEOUT_MS, status: `Not shown within ${SHOWN_TIMEOUT_MS / 1000} s`, pressedAt: null }), SHOWN_TIMEOUT_MS));
        const shown = await Promise.race([timedOut, page.evaluate(() => new Promise((resolve) => {
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
        }))]);

        if (shown.pressedAt === null) {
            // Where it stopped: the status line and the bytes of the model received.
            const stoppedAt = await Promise.race([
                page.evaluate(() => ({ status: document.querySelector('[data-twin-status]').textContent.trim(), modelBytesReceived: document.querySelector('[data-twin-progress]')?.value ?? null })),
                new Promise((resolve) => setTimeout(() => resolve({ status: 'the page did not answer', modelBytesReceived: null }), 10000)),
            ]);
            report.runs.push({ model: label, profile: profile.name, status: shown.status, stoppedAt, shownMs: null, stages: {}, waterfall: [] });
            report.failures.push(`${label} (${profile.name}): ${shown.status}`);
            await context.close();
            continue;
        }

        await page.waitForTimeout(1000);

        // Marks and resource timing, from the press ("twin:requested").
        const timeline = await page.evaluate(() => ({
            marks: Object.fromEntries(performance.getEntriesByType('mark').filter((mark) => mark.name.startsWith('twin:')).map((mark) => [mark.name.slice(5), mark.startTime])),
            resources: performance.getEntriesByType('resource').map((entry) => ({ url: entry.name, start: entry.startTime, end: entry.responseEnd, bytes: entry.encodedBodySize, decoded: entry.decodedBodySize })),
        }));
        const pressedAt = timeline.marks.requested ?? shown.pressedAt;
        const at = (mark) => (timeline.marks[mark] === undefined ? null : Math.round(timeline.marks[mark] - pressedAt));
        const files = timeline.resources
            .filter((resource) => isThreeDRequest(resource.url, viewerFiles))
            .map((resource) => ({ path: new URL(resource.url).pathname.split('/').pop(), startMs: Math.round(resource.start - pressedAt), endMs: Math.round(resource.end - pressedAt), bytes: resource.bytes, decodedBytes: resource.decoded }));
        const viewerFile = files.find((file) => /^twin-viewer/.test(file.path));
        const modelFile = files.find((file) => file.path === 'file');
        const decoderFiles = files.filter((file) => /draco|basis/.test(file.path));
        const decoded = at('model-parsed') ?? at('model-loaded');
        const downloadsDone = Math.max(modelFile?.endMs ?? 0, ...decoderFiles.map((file) => file.endMs), at('decoders-ready') ?? 0);
        const stages = {
            viewerJsDownloadedMs: viewerFile?.endMs ?? null,
            viewerJsReadyMs: at('module-ready'),
            rendererReadyMs: at('renderer-ready'),
            rendererInitMs: at('renderer-ready') !== null && at('renderer-started') !== null ? at('renderer-ready') - at('renderer-started') : null,
            modelDownloadMs: modelFile ? [modelFile.startMs, modelFile.endMs] : null,
            decoderDownloadMs: decoderFiles.length ? [Math.min(...decoderFiles.map((file) => file.startMs)), Math.max(...decoderFiles.map((file) => file.endMs))] : null,
            decodersReadyMs: at('decoders-ready'),
            modelDecodedMs: decoded,
            decodingAfterDownloadsMs: decoded !== null ? Math.max(0, decoded - downloadsDone) : null,
            firstRenderMs: at('first-render'),
            firstFrameMs: at('first-frame'),
            usableMs: at('usable'),
        };

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
        const intervals = moveRenders.slice(1).map((time, i) => time - moveRenders[i]).sort((a, b) => a - b);
        const metrics = Object.fromEntries((await cdp.send('Performance.getMetrics')).metrics.map((m) => [m.name, m.value]));
        const renderer = await page.evaluate(() => {
            const gl = document.createElement('canvas').getContext('webgl2');
            const info = gl?.getExtension('WEBGL_debug_renderer_info');
            return info ? gl.getParameter(info.UNMASKED_RENDERER_WEBGL) : gl?.getParameter(gl.RENDERER) ?? 'no WebGL 2';
        });

        const uploaded = fixtures.twin_checks[key];
        const entry = {
            model: label, profile: profile.name, fileBytes: uploaded.bytes, trianglesCounted: uploaded.triangles, beforePress,
            status: shown.status, shownMs: shown.ms, stages, waterfall: files, threeDBytes: files.reduce((sum, file) => sum + file.bytes, 0),
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
        if (!modelFile) report.failures.push(`${where}: the model file is not among the files loaded`);
        if (consoleErrors.length) report.failures.push(`${where}: console errors ${consoleErrors.join(' | ')}`);
        await context.close();
    }
}

await browser.close();
proxy?.close();
report.finishedAt = new Date().toISOString();
report.machineAtEnd = machine();
fs.writeFileSync(`${outDir}/performance.json`, JSON.stringify(report, null, 1));
const range = (pair) => (pair ? `${pair[0]}–${pair[1]}` : '–');
console.log(JSON.stringify({
    chromium: report.chromium,
    renderer: report.runs[0]?.renderer,
    machine: { start: report.machineAtStart, end: report.machineAtEnd },
    uploadChecks: Object.fromEntries(Object.entries(report.uploadChecks).map(([key, c]) => [key, `${c.bytes} B, ${c.triangles} triangles, upload with checks ${c.upload_ms} ms`])),
    runs: report.runs.map((r) => (r.shownMs === null ? `${r.model} | ${r.profile}: ${r.status}` : `${r.model} | ${r.profile}: usable at ${r.stages.usableMs ?? r.shownMs} ms${r.profile.startsWith('phone') ? (r.shownMs <= NFR_PERF_4_MS ? ' (under 5 s here)' : ' (over 5 s here)') : ''} | viewer JS downloaded ${r.stages.viewerJsDownloadedMs}, ready ${r.stages.viewerJsReadyMs}; renderer ready ${r.stages.rendererReadyMs}; model ${range(r.stages.modelDownloadMs)}; decoders ${range(r.stages.decoderDownloadMs)}${r.stages.decodersReadyMs !== null ? `, ready ${r.stages.decodersReadyMs}` : ''}; decoded ${r.stages.modelDecodedMs} (+${r.stages.decodingAfterDownloadsMs} after downloads); first render ${r.stages.firstRenderMs}, first frame ${r.stages.firstFrameMs} | ${r.threeDBytes} B | ${r.rendersBeforeMove} render(s), ${r.drawnWhole.calls} calls, ${r.drawnWhole.triangles} triangles | move: ${r.move.renders} renders, median frame ${r.move.medianFrameMs} ms`)),
    failures: report.failures,
}, null, 1));
process.exit(report.failures.length ? 1 : 0);
