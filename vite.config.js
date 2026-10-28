import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // twin-viewer.js is the 3D viewer (ADR-0009): imported on request only, never by app.js.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css', 'resources/js/twin-viewer.js'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    alias: 'inter',
                    weights: [400, 500, 600],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                    optimizedFallbacks: false,
                }),
                bunny('Sora', {
                    alias: 'sora',
                    weights: [500, 600],
                    subsets: ['latin', 'latin-ext'],
                    preload: [{ weight: 600 }],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    build: {
        rolldownOptions: {
            // Keeps the exports of an entry that has them (twin-viewer.js's mountTwinViewer), which pages
            // import on request; Vite's application default drops them.
            preserveEntrySignatures: 'exports-only',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
