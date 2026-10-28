// SmartGro 3D viewer (ADR-0009, FR-3D-3). A separate Vite entry that a page imports only when a person
// asks to see a model (BR-3D-6), so pages without a viewer load no 3D code. Compressed geometry
// (Draco, Meshopt) and KTX2 textures are decoded here; their decoders load only when a file needs them.
// The scene is drawn only when the view changes, once per change, to spare batteries; with reduced
// motion, moving to a camera position is a cut, not an animation.
import { Box3, Color, DirectionalLight, HemisphereLight, PerspectiveCamera, Quaternion, Scene, Vector3, WebGLRenderer } from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { MeshoptDecoder } from 'three/addons/libs/meshopt_decoder.module.js';
import { DRACOLoader } from 'three/addons/loaders/DRACOLoader.js';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { KTX2Loader } from 'three/addons/loaders/KTX2Loader.js';

const MOVE_MILLISECONDS = 700;

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
 * Shows the model at modelUrl in stage. Camera positions are the model's SG_CAM_<n> nodes; each looks
 * along its node's -Z axis, as glTF cameras do.
 *
 * @param {HTMLElement} stage
 * @param {{ modelUrl: string, label: string }} options
 * @returns {Promise<{ showWhole: () => void, showCamera: (nodeName: string) => boolean, dispose: () => void }>}
 */
export async function mountTwinViewer(stage, { modelUrl, label }) {
    const canvas = document.createElement('canvas');
    const renderer = createRenderer(canvas);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    Object.assign(canvas.style, { display: 'block', width: '100%', height: '100%', touchAction: 'none' });
    canvas.tabIndex = 0;
    canvas.setAttribute('role', 'img');
    canvas.setAttribute('aria-label', label);
    stage.replaceChildren(canvas);

    const scene = new Scene();
    scene.background = new Color(0xf3f4f6);
    scene.add(new HemisphereLight(0xffffff, 0x8d8d8d, 2.5));
    const sun = new DirectionalLight(0xffffff, 2);
    sun.position.set(5, 10, 7);
    scene.add(sun);

    const camera = new PerspectiveCamera(50, 1, 0.05, 2000);
    const controls = new OrbitControls(camera, canvas);
    controls.listenToKeyEvents(canvas);

    const loader = new GLTFLoader()
        .setDRACOLoader(new DRACOLoader())
        .setKTX2Loader(new KTX2Loader().detectSupport(renderer))
        .setMeshoptDecoder(MeshoptDecoder);

    let gltf;

    try {
        gltf = await loader.loadAsync(modelUrl);
    } catch (error) {
        renderer.dispose();
        throw error;
    }

    scene.add(gltf.scene);

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
        const started = performance.now();

        const step = (now) => {
            const progress = easeInOut(Math.min((now - started) / MOVE_MILLISECONDS, 1));
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
            renderer.dispose();
        },
    };
}
