{{--
    3D preview of one model (FR-3D-3, BR-3D-6). Nothing 3D is fetched until the button is pressed: then the
    model's download and the viewer module (a separate Vite entry) start together, so they overlap, with the
    download's progress shown and a way to stop it. The heading and notice are part of the page, set by how
    the model was made (BR-3D-1, BR-3D-2), so they cannot be hidden.
--}}
<div wire:ignore data-twin-viewer data-module="{{ $moduleUrl }}" data-module-imports="{{ implode(' ', $moduleImports) }}" data-decoder-files="{{ implode(' ', $decoderFiles) }}" data-model="{{ $modelUrl }}" data-label="{{ $heading }}" data-decoders="{{ implode(' ', $decoders) }}" class="flex flex-col gap-3">
    <p class="text-base font-semibold text-gray-950">{{ $heading }}</p>

    @if ($notice)
        <p class="rounded-md bg-warning-50 px-3 py-2 text-sm font-semibold text-warning-800 ring-1 ring-warning-200" data-twin-notice>{{ $notice }}</p>
    @endif

    <div class="flex flex-wrap items-center gap-2" data-twin-start>
        <x-filament::button type="button" data-twin-load>Show the 3D model ({{ $size }} MB)</x-filament::button>
        <x-filament::button type="button" color="gray" data-twin-stop hidden>Stop loading</x-filament::button>
    </div>

    <p role="status" aria-live="polite" class="text-sm text-gray-600" data-twin-status>The model loads only when you ask for it.</p>

    <progress data-twin-progress hidden max="1" aria-label="Download of the 3D model" class="h-2 w-full"></progress>

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
            const start = root.querySelector('[data-twin-start]');
            const load = root.querySelector('[data-twin-load]');
            const stop = root.querySelector('[data-twin-stop]');
            const progress = root.querySelector('[data-twin-progress]');
            let viewer = null;
            let loading = null;

            const failure = (name) => Object.assign(new Error(name), { name });

            // The model's bytes, showing the download's progress.
            const download = async (url, signal) => {
                let response;

                try {
                    response = await fetch(url, { signal });
                } catch (error) {
                    throw signal.aborted ? error : failure('DownloadFailed');
                }

                if (!response.ok || !response.body) {
                    throw failure('DownloadFailed');
                }

                const total = Number(response.headers.get('Content-Length')) || 0;
                const reader = response.body.getReader();
                const parts = [];
                let received = 0;

                if (total) {
                    progress.max = total;
                    progress.value = 0;
                } else {
                    progress.removeAttribute('value');
                }

                for (let part = await reader.read(); !part.done; part = await reader.read()) {
                    parts.push(part.value);
                    received += part.value.length;

                    if (total) {
                        progress.value = received;
                    }
                }

                const bytes = new Uint8Array(received);
                let offset = 0;

                for (const part of parts) {
                    bytes.set(part, offset);
                    offset += part.length;
                }

                performance.mark('twin:model-downloaded');

                return bytes.buffer;
            };

            const reset = (message) => {
                loading?.abort();
                viewer?.dispose();
                viewer = null;
                loading = null;
                stage.hidden = true;
                stage.replaceChildren();
                progress.hidden = true;
                stop.hidden = true;
                controls.hidden = true;
                start.hidden = false;
                load.disabled = false;
                status.textContent = message;
            };

            stop.addEventListener('click', () => {
                loading?.abort();
                reset('Loading stopped. Press "Show the 3D model" to start again; the anchors are listed below.');
                load.focus();
            });

            controls.querySelector('[data-twin-whole]').addEventListener('click', () => viewer?.showWhole());

            for (const button of controls.querySelectorAll('[data-twin-camera]')) {
                button.addEventListener('click', () => {
                    status.textContent = viewer?.showCamera(button.dataset.twinCamera)
                        ? button.textContent.trim() + ' shown.'
                        : 'This camera position is not in the model.';
                });
            }

            load.addEventListener('click', async () => {
                performance.mark('twin:requested');
                const abort = new AbortController();
                loading = abort;
                load.disabled = true;
                stop.hidden = false;
                progress.hidden = false;
                status.textContent = 'Loading the viewer and the model…';

                try {
                    // The model, the viewer module, the modules it imports and the decoders this model needs
                    // all start now, so their downloads overlap; the viewer waits for the model.
                    const model = download(root.dataset.model, abort.signal);
                    model.catch(() => {});

                    for (const href of root.dataset.moduleImports.split(' ').filter(Boolean)) {
                        document.head.append(Object.assign(document.createElement('link'), { rel: 'modulepreload', href }));
                    }

                    for (const href of root.dataset.decoderFiles.split(' ').filter(Boolean)) {
                        document.head.append(Object.assign(document.createElement('link'), { rel: 'preload', as: 'fetch', crossOrigin: 'anonymous', href }));
                    }

                    const { mountTwinViewer } = await import(root.dataset.module);
                    performance.mark('twin:module-ready');
                    const shown = await mountTwinViewer(stage, {
                        model,
                        label: root.dataset.label,
                        decoders: root.dataset.decoders.split(' ').filter(Boolean),
                        onContextLost: () => reset('The 3D view stopped: the device ran out of graphics memory. Press "Show the 3D model" to try again; the anchors are listed below.'),
                        signal: abort.signal,
                    });

                    if (abort.signal.aborted) {
                        shown.dispose();

                        return;
                    }

                    viewer = shown;
                    loading = null;
                    progress.hidden = true;
                    start.hidden = true;
                    controls.hidden = false;
                    status.textContent = 'Model shown. Drag to turn it, scroll or pinch to zoom, or choose a view below. With the view focused, arrow keys move it.';
                    performance.mark('twin:usable');
                } catch (error) {
                    if (abort.signal.aborted) {
                        return;
                    }

                    reset({
                        WebGLUnavailable: 'This browser or device cannot show 3D. The anchors are listed below.',
                        DownloadFailed: 'The model could not be downloaded. Check the connection and try again; the anchors are listed below.',
                    }[error?.name] ?? 'The model could not be shown. Try again; the anchors are listed below.');
                }
            });
        }
    </script>
</div>
