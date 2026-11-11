<?php

namespace App\Services\Twin;

use App\Models\TwinModel;
use Illuminate\Support\Facades\Vite;

/**
 * What a page needs to start the 3D viewer only when a person asks for it (BR-3D-6): the viewer's own module (a
 * separate Vite entry), the built files to fetch alongside it so their downloads overlap, and the decoders a model
 * needs. Used by the back-office preview and by a location page's walkthrough (OD-181).
 */
class TwinViewerAssets
{
    public const string VIEWER_ENTRY = 'resources/js/twin-viewer.js';

    /**
     * Each decoder's module and the files that module fetches: three's defaults, and for Draco the build made
     * for glTF, which the viewer selects (DRACO_GLTF_CONFIG).
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const array DECODER_SOURCES = [
        'draco' => ['node_modules/three/examples/jsm/loaders/DRACOLoader.js', ['node_modules/three/examples/jsm/libs/draco/gltf/draco_wasm_wrapper.js', 'node_modules/three/examples/jsm/libs/draco/gltf/draco_decoder.wasm']],
        'meshopt' => ['node_modules/three/examples/jsm/libs/meshopt_decoder.module.js', []],
        'ktx2' => ['node_modules/three/examples/jsm/loaders/KTX2Loader.js', ['node_modules/three/examples/jsm/libs/basis/basis_transcoder.js', 'node_modules/three/examples/jsm/libs/basis/basis_transcoder.wasm']],
    ];

    /**
     * Everything the viewer's markup carries for one model.
     *
     * @return array{moduleUrl: string, moduleImports: list<string>, decoderFiles: list<string>, decoders: list<string>, size: string}
     */
    public function forModel(TwinModel $model): array
    {
        $decoders = $this->decoders($model);

        return [
            'moduleUrl' => Vite::asset(self::VIEWER_ENTRY),
            ...$this->files($decoders),
            'decoders' => $decoders,
            'size' => $this->megabytes($model->file_bytes),
        ];
    }

    /**
     * The built files to fetch alongside the viewer module when the button is pressed, instead of after it:
     * the modules it imports and the decoders' modules (`moduleImports`), and the files those decoders fetch
     * (`decoderFiles`), for the decoders this model needs only. None while Vite's development server runs.
     *
     * @param  list<string>  $decoders
     * @return array{moduleImports: list<string>, decoderFiles: list<string>}
     */
    public function files(array $decoders): array
    {
        $manifest = Vite::isRunningHot() ? [] : json_decode((string) @file_get_contents(public_path('build/manifest.json')), true);
        $url = fn (string $source): ?string => isset($manifest[$source]['file']) ? asset('build/'.$manifest[$source]['file']) : null;
        $needed = array_intersect_key(self::DECODER_SOURCES, array_flip($decoders));

        return [
            'moduleImports' => array_values(array_filter(array_map($url, [...($manifest[self::VIEWER_ENTRY]['imports'] ?? []), ...array_column($needed, 0)]))),
            'decoderFiles' => array_values(array_filter(array_map($url, array_merge([], ...array_column($needed, 1))))),
        ];
    }

    /**
     * The decoders the checks found the model needs, so the viewer can start loading them at once.
     *
     * @return list<string>
     */
    public function decoders(TwinModel $model): array
    {
        $report = $model->validation_report;

        return array_values(array_filter([
            in_array('draco', $report['compression'] ?? [], true) ? 'draco' : null,
            in_array('meshopt', $report['compression'] ?? [], true) ? 'meshopt' : null,
            in_array('ktx2', $report['textures']['formats'] ?? [], true) ? 'ktx2' : null,
        ]));
    }

    public function megabytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1);
    }
}
