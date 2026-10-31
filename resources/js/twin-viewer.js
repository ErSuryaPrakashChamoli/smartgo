// SmartGro 3D viewer (ADR-0009, FR-3D-3). A separate Vite entry that a page imports only when a person
// asks to see a model (BR-3D-6), so pages without a viewer load no 3D code. The page starts the model's
// download at the same moment (viewer.blade.php), so the two overlap. Compressed geometry (Draco, Meshopt)
// and KTX2 textures are decoded here; each decoder is its own file, loaded only for a model that needs it.
// The scene is drawn once to show the model and then once per change of view, to spare batteries; with
// reduced motion, moving to a camera position is a cut, not an animation. Performance marks ("twin:…")
// time each stage for the browser checks.
import { Box3, Color, DirectionalLight, HemisphereLight, PerspectiveCamera, Quaternion, Scene, Vector3, WebGLRenderer } from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

const MOVE_MILLISECONDS = 700;

/** The glTF extensions that need a decoder, and the decoder each needs. */
const DECODER_FOR_EXTENSION = { KHR_draco_mesh_compression: 'draco', EXT_meshopt_compression: 'meshopt', KHR_texture_basisu: 'ktx2' };

const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const easeInOut = (t) => (t < 0.5 ? 2 * t * t : 1 - (-2 * t + 2) ** 2 / 2);

function createRenderer(canvas) {
    try {
        return new WebGLRenderer({ canvas, antialias: true });
    } catch {
        const error = new Error('WebGL is not available.');
        error.name = 'WebGLUnavailable';
        throw error;
    }
}

/**
 * Starts loading a decoder and sets it on the loader; resolves once it is ready to use.
 */
function loadDecoder(name, loader, renderer) {
    switch (name) {
        case 'draco':
            // The decoder build made for glTF: smaller, and all that KHR_draco_mesh_compression needs.
            return import('three/addons/loaders/DRACOLoader.js').then(({ DRACOLoader, DRACO_GLTF_CONFIG }) => {
                loader.setDRACOLoader(new DRACOLoader().setDecoderPath(DRACO_GLTF_CONFIG).preload());
            });
        case 'meshopt':
            return import('three/addons/libs/meshopt_decoder.module.js').then(({ MeshoptDecoder }) => MeshoptDecoder.ready.then(() => {
                loader.setMeshoptDecoder(MeshoptDecoder);
            }));
        case 'ktx2':
            return import('three/addons/loaders/KTX2Loader.js').then(({ KTX2Loader }) => {
                loader.setKTX2Loader(new KTX2Loader().detectSupport(renderer));
            });
        default:
            return Promise.resolve();
    }
}

/**
 * The extensions a GLB file uses, read from its JSON chunk.
 *
 * @param {ArrayBuffer} buffer
 * @returns {string[]}
 */
function extensionsUsed(buffer) {
    try {
        const view = new DataView(buffer);
        const json = JSON.parse(new TextDecoder().decode(new Uint8Array(buffer, 20, view.getUint32(12, true))));

        return Array.isArray(json.extensionsUsed) ? json.extensionsUsed : [];
    } catch {
        return [];
    }
}

/**
 * Shows a model in stage. Camera positions are the model's SG_CAM_<n> nodes; each looks along its node's
 * -Z axis, as glTF cameras do.
 *
 * @param {HTMLElement} stage
 * @param {{ model: Promise<ArrayBuffer>, label: string, decoders?: string[], onContextLost?: () => void, signal?: AbortSignal }} options
 *   model: the GLB file, downloading; decoders: those the upload's checks found the model needs (draco,
 *   meshopt, ktx2), started at once; any other the file turns out to need is loaded after the download;
 *   signal: once aborted, the viewer never touches the stage and gives up its graphics context.
 * @returns {Promise<{ showWhole: () => void, showCamera: (nodeName: string) => boolean, dispose: () => void }>}
 */
export async function mountTwinViewer(stage, { model, label, decoders = [], onContextLost = () => {}, signal }) {
    performance.mark('twin:renderer-started');
    const canvas = document.createElement('canvas');
    const renderer = createRenderer(canvas);
    performance.mark('twin:renderer-ready');
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    Object.assign(canvas.style, { display: 'block', width: '100%', height: '100%', touchAction: 'none' });
    canvas.tabIndex = 0;
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', label);
    const contextLost = (event) => {
        event.preventDefault();
        onContextLost();
    };
    canvas.addEventListener('webglcontextlost', contextLost);

    // Gives the graphics context back at once: browsers keep only a few alive and drop the oldest, which
    // would otherwise reach this viewer's handler after it is gone and stop the one on screen.
    const release = () => {
        canvas.removeEventListener('webglcontextlost', contextLost);
        renderer.dispose();
        renderer.forceContextLoss();
    };

    const loader = new GLTFLoader();
    const started = new Map(decoders.map((name) => [name, loadDecoder(name, loader, renderer)]));
    let gltf;

    try {
        const buffer = await model;

        for (const extension of extensionsUsed(buffer)) {
            const name = DECODER_FOR_EXTENSION[extension];

            if (name && !started.has(name)) {
                started.set(name, loadDecoder(name, loader, renderer));
            }
        }

        await Promise.all(started.values());
        performance.mark('twin:decoders-ready');
        gltf = await loader.parseAsync(buffer, '');
        performance.mark('twin:model-parsed');
        signal?.throwIfAborted();
    } catch (error) {
        release();
        throw error;
    }

    stage.hidden = false;
    stage.replaceChildren(canvas);

    const scene = new Scene();
    scene.background = new Color(0xf3f4f6);
    scene.add(new HemisphereLight(0xffffff, 0x8d8d8d, 2.5));
    const sun = new DirectionalLight(0xffffff, 2);
    sun.position.set(5, 10, 7);
    scene.add(sun);
    scene.add(gltf.scene);

    const camera = new PerspectiveCamera(50, 1, 0.05, 2000);
    const controls = new OrbitControls(camera, canvas);
    controls.listenToKeyEvents(canvas);

    const render = () => renderer.render(scene, camera);

    const bounds = new Box3().setFromObject(gltf.scene);
    const centre = bounds.getCenter(new Vector3());
    const size = Math.max(bounds.getSize(new Vector3()).length(), 0.001);
    camera.near = size / 1000;
    camera.far = size * 20;
    const whole = { position: centre.clone().add(new Vector3(size * 0.6, size * 0.45, size * 0.6)), target: centre.clone() };

    let movement = 0;

    const moveTo = ({ position, target }) => {
        cancelAnimationFrame(movement);

        // controls.update() draws through its change event when the view moved; otherwise draw here.
        if (prefersReducedMotion()) {
            camera.position.copy(position);
            controls.target.copy(target);
            controls.update() || render();

            return;
        }

        const fromPosition = camera.position.clone();
        const fromTarget = controls.target.clone();
        const moveStarted = performance.now();

        const step = (now) => {
            const progress = easeInOut(Math.min((now - moveStarted) / MOVE_MILLISECONDS, 1));
            camera.position.lerpVectors(fromPosition, position, progress);
            controls.target.lerpVectors(fromTarget, target, progress);
            controls.update() || render();

            if (progress < 1) {
                movement = requestAnimationFrame(step);
            }
        };

        movement = requestAnimationFrame(step);
    };

    const cameraView = (nodeName) => {
        const node = gltf.scene.getObjectByName(nodeName);

        if (!node) {
            return null;
        }

        const position = node.getWorldPosition(new Vector3());
        const forward = new Vector3(0, 0, -1).applyQuaternion(node.getWorldQuaternion(new Quaternion()));

        return { position, target: position.clone().add(forward.multiplyScalar(Math.max(size * 0.02, 0.5))) };
    };

    // The stage's size when last drawn; -1 until the first drawing.
    let drawnWidth = -1;
    let drawnHeight = -1;

    const resize = () => {
        if (stage.clientWidth === drawnWidth && stage.clientHeight === drawnHeight) {
            return;
        }

        drawnWidth = stage.clientWidth;
        drawnHeight = stage.clientHeight;
        const width = Math.max(drawnWidth, 1);
        const height = Math.max(drawnHeight, 1);
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        render();
    };

    // Set up the first view, then draw it once at the stage's size; only then draw on every change, so
    // the set-up draws nothing at the wrong size.
    camera.position.copy(whole.position);
    controls.target.copy(whole.target);
    controls.update();
    resize();
    performance.mark('twin:first-render');
    requestAnimationFrame(() => performance.mark('twin:first-frame'));
    controls.addEventListener('change', render);

    const observer = new ResizeObserver(resize);
    observer.observe(stage);

    return {
        showWhole: () => moveTo(whole),
        showCamera: (nodeName) => {
            const view = cameraView(nodeName);

            if (view) {
                moveTo(view);
            }

            return view !== null;
        },
        dispose: () => {
            cancelAnimationFrame(movement);
            observer.disconnect();
            controls.dispose();
            release();
        },
    };
}
