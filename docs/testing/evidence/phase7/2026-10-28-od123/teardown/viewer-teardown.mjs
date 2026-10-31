// Scratch: reproduce (1) Stop pressed while decoders load / the model parses, and (2) WebGL contexts left
// alive across Show/Stop cycles. Usage: node viewer-teardown.mjs <base> <fixtures.json> <out.json>
import fs from 'node:fs';
const { chromium } = await import('/home/administrator/.npm/_npx/705bc6b22212b352/node_modules/playwright/index.mjs');
const [base, fixturesFile, outFile] = process.argv.slice(2);
const fixtures = JSON.parse(fs.readFileSync(fixturesFile, 'utf8'));
const browser = await chromium.launch(process.env.CHROMIUM_EXECUTABLE ? { executablePath: process.env.CHROMIUM_EXECUTABLE } : {});
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const result = {};
const open = async (modelId) => {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    const [name, value] = fixtures.sessions.operations.split('\t');
    await context.addCookies([{ name, value, url: base }]);
    const page = await context.newPage();
    const consoleLines = [];
    page.on('console', (m) => consoleLines.push(`${m.type()}: ${m.text()}`));
    await page.goto(`${base}/admin/website/twin-models/${modelId}`, { waitUntil: 'networkidle' });
    return { context, page, consoleLines };
};
const state = (page) => page.evaluate(() => ({
    status: document.querySelector('[data-twin-status]').textContent.trim(),
    stageHidden: document.querySelector('[data-twin-stage]').hidden,
    canvases: document.querySelectorAll('[data-twin-stage] canvas').length,
    showVisible: !document.querySelector('[data-twin-start]').hidden,
    controlsHidden: document.querySelector('[data-twin-controls]').hidden,
}));

// (1) Draco model: hold the Draco loader chunk back, press Stop after the model's file has arrived.
{
    const { context, page, consoleLines } = await open(fixtures.ids.twin_model_draco);
    let release;
    const held = new Promise((r) => { release = r; });
    let fileDone = false;
    page.on('requestfinished', (r) => { if (r.url().endsWith('/file')) fileDone = true; });
    await page.route(/DRACOLoader-[^/]+\.js$/, async (route) => { await held; await route.continue(); });
    await page.getByRole('button', { name: /Show the 3D model/ }).click();
    for (let i = 0; i < 100 && !fileDone; i++) await sleep(100);
    await sleep(300);
    const beforeStop = await state(page);
    await page.getByRole('button', { name: 'Stop loading' }).click();
    await sleep(300);
    const afterStop = await state(page);
    release();
    await sleep(4000);
    const afterDecoderArrived = await state(page);
    result.stopWhileDecoding = { fileDownloadedBeforeStop: fileDone, beforeStop, afterStop, afterDecoderArrived, consoleErrors: consoleLines.filter((l) => l.startsWith('error')) };
    await context.close();
}

// (2) Meshopt model: 20 rounds of Show then Stop while the file is held back, then Show to the end.
{
    const { context, page, consoleLines } = await open(fixtures.ids.twin_model);
    let holdFile = true;
    await page.route('**/twin-models/*/file', async (route) => { if (holdFile) { await sleep(3000); } await route.continue().catch(() => {}); });
    const log = [];
    try {
        for (let round = 0; round < 20; round++) {
            const show = page.getByRole('button', { name: /Show the 3D model/ });
            if (!(await show.isVisible()) || !(await show.isEnabled())) { log.push({ round, step: 'show not available', ...(await state(page)) }); await sleep(1000); continue; }
            await show.click();
            await sleep(500);
            const during = await state(page);
            const stop = page.getByRole('button', { name: 'Stop loading' });
            const stopVisible = await stop.isVisible();
            if (stopVisible) { await stop.click(); }
            await sleep(200);
            log.push({ round, during: during.status, stopVisible, after: (await state(page)).status });
        }
        holdFile = false;
        const show = page.getByRole('button', { name: /Show the 3D model/ });
        if (await show.isVisible()) { await show.click(); }
        await page.waitForFunction(() => /^(Model shown|This browser|The model could not|The 3D view stopped)/.test(document.querySelector('[data-twin-status]').textContent.trim()), null, { timeout: 60000 }).catch(() => {});
        await sleep(1500);
    } catch (error) { log.push({ error: String(error).slice(0, 200) }); }
    result.showStopCycles = {
        log,
        contextLimitWarnings: consoleLines.filter((l) => /Too many active WebGL contexts/i.test(l)).length,
        graphicsMemoryMessages: log.filter((e) => JSON.stringify(e).includes('graphics memory')).length,
        final: await state(page),
        sampleConsole: consoleLines.filter((l) => /WebGL|context/i.test(l)).slice(0, 5),
    };
    await context.close();
}
await browser.close();
fs.writeFileSync(outFile, JSON.stringify(result, null, 2));
console.log(JSON.stringify(result, null, 2));
