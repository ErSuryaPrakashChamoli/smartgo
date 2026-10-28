// SmartGro browser checks: which requests are 3D (Phase 7 AC 3, BR-3D-6). From the Vite manifest, every
// file the viewer entry (resources/js/twin-viewer.js) can load: its chunk, the chunks it imports and the
// decoders (Draco, KTX2) it references; plus any .glb or .wasm file and the back-office model file route.
// Run from the project root, or set VITE_MANIFEST.
import fs from 'node:fs';
import zlib from 'node:zlib';

const VIEWER_ENTRY = 'resources/js/twin-viewer.js';

export function threeDFiles() {
    const manifest = JSON.parse(fs.readFileSync(process.env.VITE_MANIFEST ?? 'public/build/manifest.json', 'utf8'));
    const seen = new Set();
    const files = new Set();
    const walk = (key) => {
        if (seen.has(key) || !manifest[key]) return;
        seen.add(key);
        files.add(manifest[key].file);
        (manifest[key].assets ?? []).forEach((asset) => files.add(asset));
        [...(manifest[key].imports ?? []), ...(manifest[key].dynamicImports ?? [])].forEach(walk);
    };
    walk(VIEWER_ENTRY);
    if (!files.size) throw new Error(`${VIEWER_ENTRY} is not in the Vite manifest: run npm run build`);

    return [...files];
}

export function isThreeDRequest(url, files) {
    const { pathname } = new URL(url);

    return files.some((file) => pathname === `/build/${file}`) || /\.(glb|wasm)$/.test(pathname) || /\/twin-models\/\d+\/file$/.test(pathname);
}

// Decodes an 8-bit, non-interlaced RGB or RGBA PNG (what Playwright's screenshots are) into pixels.
export function decodePng(buffer) {
    let offset = 8;
    let width = 0;
    let height = 0;
    let channels = 0;
    const data = [];

    while (offset < buffer.length) {
        const length = buffer.readUInt32BE(offset);
        const type = buffer.toString('ascii', offset + 4, offset + 8);
        const chunk = buffer.subarray(offset + 8, offset + 8 + length);
        if (type === 'IHDR') {
            width = chunk.readUInt32BE(0);
            height = chunk.readUInt32BE(4);
            channels = { 2: 3, 6: 4 }[chunk[9]] ?? 0;
            if (chunk[8] !== 8 || chunk[12] !== 0 || !channels) throw new Error('Unsupported PNG');
        }
        if (type === 'IDAT') data.push(chunk);
        if (type === 'IEND') break;
        offset += 12 + length;
    }

    const raw = zlib.inflateSync(Buffer.concat(data));
    const stride = width * channels;
    const pixels = Buffer.alloc(height * stride);

    for (let y = 0; y < height; y++) {
        const filter = raw[y * (stride + 1)];
        for (let x = 0; x < stride; x++) {
            const left = x >= channels ? pixels[y * stride + x - channels] : 0;
            const up = y > 0 ? pixels[(y - 1) * stride + x] : 0;
            const upLeft = x >= channels && y > 0 ? pixels[(y - 1) * stride + x - channels] : 0;
            let value = raw[y * (stride + 1) + 1 + x];
            if (filter === 1) value += left;
            if (filter === 2) value += up;
            if (filter === 3) value += (left + up) >> 1;
            if (filter === 4) {
                const estimate = left + up - upLeft;
                const [toLeft, toUp, toUpLeft] = [Math.abs(estimate - left), Math.abs(estimate - up), Math.abs(estimate - upLeft)];
                value += toLeft <= toUp && toLeft <= toUpLeft ? left : toUp <= toUpLeft ? up : upLeft;
            }
            pixels[y * stride + x] = value & 0xff;
        }
    }

    return { width, height, channels, pixels };
}

// Counts the test model's colours in a screenshot of the viewer: the green test space, the amber test
// lounge, and everything that is not the light-grey background.
export function modelColours(png) {
    const { width, height, channels, pixels } = decodePng(png);
    const counts = { green: 0, amber: 0, drawn: 0, total: width * height };

    for (let i = 0; i < pixels.length; i += channels) {
        const [r, g, b] = [pixels[i], pixels[i + 1], pixels[i + 2]];
        if (g > r + 30 && g > b + 30) counts.green++;
        if (r > g + 15 && g > b + 40) counts.amber++;
        if (Math.abs(r - 243) + Math.abs(g - 244) + Math.abs(b - 246) > 12) counts.drawn++;
    }

    return counts;
}
