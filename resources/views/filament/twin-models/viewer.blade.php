{{--
    3D preview of one model (FR-3D-3, BR-3D-6). Nothing 3D is fetched until the button is pressed: then
    the viewer module (a separate Vite entry) and the model file load. The heading and notice are part of
    the page, set by how the model was made (BR-3D-1, BR-3D-2), so they cannot be hidden.
--}}
<div wire:ignore data-twin-viewer data-module="{{ $moduleUrl }}" data-model="{{ $modelUrl }}" data-label="{{ $heading }}" class="flex flex-col gap-3">
    <p class="text-base font-semibold text-gray-950">{{ $heading }}</p>

    @if ($notice)
        <p class="rounded-md bg-warning-50 px-3 py-2 text-sm font-semibold text-warning-800 ring-1 ring-warning-200" data-twin-notice>{{ $notice }}</p>
    @endif

    <div>
        <x-filament::button type="button" data-twin-load>Show the 3D model ({{ $size }} MB)</x-filament::button>
    </div>

    <p role="status" aria-live="polite" class="text-sm text-gray-600" data-twin-status>The model loads only when you ask for it.</p>

    <div data-twin-stage hidden class="aspect-video w-full overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200"></div>

    <div data-twin-controls hidden role="group" aria-label="Views of the model" class="flex flex-wrap gap-2">
        <x-filament::button type="button" color="gray" size="sm" data-twin-whole>Whole model</x-filament::button>
        @foreach ($cameras as $camera)
            <x-filament::button type="button" color="gray" size="sm" data-twin-camera="{{ $camera['node'] }}">{{ $camera['label'] }}</x-filament::button>
        @endforeach
    </div>

    <script type="module">
        for (const root of document.querySelectorAll('[data-twin-viewer]:not([data-twin-ready])')) {
            root.dataset.twinReady = '';
            const status = root.querySelector('[data-twin-status]');
            const stage = root.querySelector('[data-twin-stage]');
            const controls = root.querySelector('[data-twin-controls]');
            const load = root.querySelector('[data-twin-load]');

            load.addEventListener('click', async () => {
                load.disabled = true;
                status.textContent = 'Loading the viewer and the model…';
                stage.hidden = false;

                try {
                    const { mountTwinViewer } = await import(root.dataset.module);
                    const viewer = await mountTwinViewer(stage, { modelUrl: root.dataset.model, label: root.dataset.label });

                    controls.querySelector('[data-twin-whole]').addEventListener('click', () => viewer.showWhole());

                    for (const button of controls.querySelectorAll('[data-twin-camera]')) {
                        button.addEventListener('click', () => {
                            status.textContent = viewer.showCamera(button.dataset.twinCamera)
                                ? button.textContent.trim() + ' shown.'
                                : 'This camera position is not in the model.';
                        });
                    }

                    controls.hidden = false;
                    load.closest('div').hidden = true;
                    status.textContent = 'Model shown. Drag to turn it, scroll or pinch to zoom, or choose a view below. With the view focused, arrow keys move it.';
                } catch (error) {
                    stage.hidden = true;
                    load.disabled = false;
                    status.textContent = error?.name === 'WebGLUnavailable'
                        ? 'This browser or device cannot show 3D. The anchors are listed below.'
                        : 'The model could not be shown. Try again; the anchors are listed below.';
                }
            });
        }
    </script>
</div>
