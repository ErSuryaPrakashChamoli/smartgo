<?php

namespace App\Services\Twin;

use App\Enums\Twin\AnchorType;
use Illuminate\Support\Str;

/**
 * The automated checks a GLB file must pass before it is stored (BR-3D-4, FR-3D-1), held to the hard
 * limits the owner approved and the safeguards chosen with them (OD-119, TwinModelLimits); the supplier's
 * specification is CLIENT_INPUTS_REQUIRED C-6.5. The file's size is checked first, from the file system,
 * and its description is measured before it is decoded. Then: the structure (every reference and byte
 * range, the node trees, a scene to show), the geometry (triangles, counted inside Draco streams, which
 * the viewer draws whatever the description declares; compression; pieces drawn separately; size once
 * decompressed), materials, textures (format and pixel size, read from each image's own header), the
 * model's dimensions, and its anchors (ADR-0009). Nothing is decompressed or decoded, and all the checks
 * must finish within the time limit. Every problem is stated in plain language, so the uploader can pass
 * it on to the 3D-modelling supplier.
 */
class GlbValidator
{
    /**
     * Extensions a file may require: the viewer can decode and show these (ADR-0009).
     *
     * @var list<string>
     */
    public const array SUPPORTED_REQUIRED_EXTENSIONS = [
        'KHR_draco_mesh_compression', 'EXT_meshopt_compression', 'KHR_mesh_quantization',
        'KHR_texture_basisu', 'KHR_texture_transform', 'KHR_materials_unlit', 'EXT_texture_webp',
    ];

    /** "glTF", read as a little-endian number. */
    private const int MAGIC = 0x46546C67;

    /** "JSON", read as a little-endian number. */
    private const int JSON_CHUNK = 0x4E4F534A;

    /** "BIN", read as a little-endian number. */
    private const int BIN_CHUNK = 0x004E4942;

    /** Nesting allowed in the description; glTF needs far less. */
    private const int JSON_DEPTH = 64;

    /** Triangle list, strip and fan; points and lines carry no surfaces. */
    private const array SURFACE_MODES = [4, 5, 6];

    /** Bytes per component, by glTF component type. */
    private const array COMPONENT_BYTES = [5120 => 1, 5121 => 1, 5122 => 2, 5123 => 2, 5125 => 4, 5126 => 4];

    /** The stored value that reads as 1.0 in a normalised accessor, by component type. */
    private const array NORMALIZED_ONE = [5120 => 127, 5121 => 255, 5122 => 32767, 5123 => 65535];

    /** Components per element, by accessor type. */
    private const array TYPE_COMPONENTS = ['SCALAR' => 1, 'VEC2' => 2, 'VEC3' => 3, 'VEC4' => 4, 'MAT2' => 4, 'MAT3' => 9, 'MAT4' => 16];

    private const array MESHOPT_MODES = ['ATTRIBUTES', 'TRIANGLES', 'INDICES'];

    /** Texture slots of a glTF material. */
    private const array MATERIAL_TEXTURES = [['pbrMetallicRoughness', 'baseColorTexture'], ['pbrMetallicRoughness', 'metallicRoughnessTexture'], ['normalTexture'], ['occlusionTexture'], ['emissiveTexture']];

    private const array IDENTITY = [1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0];

    /** How many names a message lists before "and N more". */
    private const int NAMES_SHOWN = 5;

    /** @var resource|null the file being checked */
    private $handle = null;

    /** Where the binary chunk's data starts in the file, and its length. */
    private int $binaryOffset = 0;

    private int $binaryLength = 0;

    /** The hrtime() after which the checks stop. */
    private int $deadline = 0;

    /** @var array<int, string> decoded "data:" buffers, by buffer index */
    private array $embeddedBuffers = [];

    public function __construct(
        private AnchorName $anchorName,
        private TwinModelLimits $limits,
        private ImageDimensions $imageDimensions,
        private DracoStream $dracoStream,
    ) {}

    /**
     * @return array{problems: list<string>, report: array{bytes: int, triangles: int, draw_calls: int, materials: int, geometry_bytes: int, textures: array{count: int, pixels: int, largest_side: int, formats: list<string>}, size_metres: list<float>|null, compression: list<string>, required_extensions: list<string>, nodes: int, generator: string|null, budgets: array<string, int|float>}, anchors: list<array{node_name: string, type: AnchorType, reference: string}>}
     */
    public function validate(string $path): array
    {
        $this->deadline = hrtime(true) + (int) ($this->limits->checkSeconds * 1_000_000_000);
        $bytes = (int) filesize($path);
        $problems = [];
        $report = [
            'bytes' => $bytes, 'triangles' => 0, 'draw_calls' => 0, 'materials' => 0, 'geometry_bytes' => 0,
            'textures' => ['count' => 0, 'pixels' => 0, 'largest_side' => 0, 'formats' => []], 'size_metres' => null,
            'compression' => [], 'required_extensions' => [], 'nodes' => 0, 'generator' => null, 'budgets' => $this->limits->toArray(),
        ];

        if ($bytes > $this->limits->bytes) {
            $problems[] = sprintf('The file is %s MB; the limit is %s MB per model. Ask the 3D-modelling supplier to compress it further or reduce the detail.', $this->megabytes($bytes), $this->megabytes($this->limits->bytes));
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['problems' => [...$problems, 'The file could not be read. Upload it again.'], 'report' => $report, 'anchors' => []];
        }

        $this->handle = $handle;
        $this->embeddedBuffers = [];

        try {
            $document = $this->readDescription($bytes, $problems);

            if ($document === null) {
                return ['problems' => $problems, 'report' => $report, 'anchors' => []];
            }

            $version = $document['asset']['version'] ?? null;

            if (! is_string($version) || ! str_starts_with($version, '2.')) {
                $problems[] = 'The file does not say it is glTF 2.0. Ask the supplier for a glTF 2.0 export.';
            }

            $generator = $document['asset']['generator'] ?? null;
            $report['generator'] = is_string($generator) ? Str::limit($generator, 80) : null;
            $report['nodes'] = count($this->items($document, 'nodes'));
            $report['materials'] = count($this->items($document, 'materials'));
            $this->checkParts($report['nodes'], $report['materials'], $problems);
            $this->checkOutsideFiles($document, $problems);
            $report['required_extensions'] = $this->checkExtensions($document, $problems);
            $structure = $this->checkStructure($document, $problems);
            $report = [...$report, ...$this->checkGeometry($document, $structure['views'], $problems)];
            $report['textures'] = $this->checkTextures($document, $problems);
            $report['size_metres'] = $this->checkDimensions($document, $structure['roots'], $structure['children'], $problems);
            $anchors = $this->checkAnchors($document, $problems);

            return ['problems' => $problems, 'report' => $report, 'anchors' => $anchors];
        } catch (ChecksTimedOut) {
            $problems[] = sprintf('Checking the model took longer than %s seconds, so it was stopped. The file is probably far too complex: ask the supplier to simplify it, with fewer parts and textures.', $this->number($this->limits->checkSeconds));

            return ['problems' => $problems, 'report' => $report, 'anchors' => []];
        } finally {
            fclose($handle);
            $this->handle = null;
            $this->embeddedBuffers = [];
        }
    }

    /**
     * The GLB header and JSON chunk, decoded, after the description's size is checked; null when the file
     * is not a readable binary glTF 2.0 file. Notes where the binary chunk is.
     *
     * @param  list<string>  $problems
     * @return array<string, mixed>|null
     */
    private function readDescription(int $bytes, array &$problems): ?array
    {
        $header = fread($this->handle, 12);

        if (! is_string($header) || strlen($header) < 12 || unpack('V', $header)[1] !== self::MAGIC) {
            $problems[] = 'This is not a GLB file. Ask the 3D-modelling supplier to export the model as binary glTF (.glb).';

            return null;
        }

        ['version' => $version, 'length' => $length] = unpack('Vmagic/Vversion/Vlength', $header);

        if ($version !== 2) {
            $problems[] = sprintf('Only glTF 2.0 files can be used; this file is glTF version %d.', $version);

            return null;
        }

        $chunkHeader = fread($this->handle, 8);

        if ($length !== $bytes || ! is_string($chunkHeader) || strlen($chunkHeader) < 8) {
            $problems[] = 'The file is incomplete or damaged. Upload it again, or ask the supplier for a new export.';

            return null;
        }

        ['length' => $chunkLength, 'type' => $chunkType] = unpack('Vlength/Vtype', $chunkHeader);

        if ($chunkType === self::JSON_CHUNK && $chunkLength > $this->limits->descriptionBytes) {
            $problems[] = sprintf('The model\'s description (the part of the file that lists its nodes, meshes and materials) is %s MB; the limit is %s MB. That usually means far too many separate parts: ask the supplier to merge them.', $this->megabytes($chunkLength), $this->megabytes($this->limits->descriptionBytes));

            return null;
        }

        $json = $chunkType === self::JSON_CHUNK && $chunkLength > 0 && $chunkLength <= $bytes - 20 ? fread($this->handle, $chunkLength) : false;
        $document = is_string($json) && strlen($json) === $chunkLength ? json_decode(rtrim($json, "\x00\x20"), true, self::JSON_DEPTH) : null;

        if (! is_array($document) || array_is_list($document)) {
            $problems[] = 'The model\'s description cannot be read: the file is damaged. Ask the supplier for a new export.';

            return null;
        }

        $binaryHeader = fread($this->handle, 8);

        if (is_string($binaryHeader) && strlen($binaryHeader) === 8) {
            ['length' => $binaryLength, 'type' => $binaryType] = unpack('Vlength/Vtype', $binaryHeader);
            $this->binaryOffset = 28 + $chunkLength;
            $this->binaryLength = $binaryType === self::BIN_CHUNK && $this->binaryOffset + $binaryLength <= $bytes ? $binaryLength : 0;
        } else {
            $this->binaryOffset = 0;
            $this->binaryLength = 0;
        }

        return $document;
    }

    /**
     * @param  list<string>  $problems
     */
    private function checkParts(int $nodes, int $materials, array &$problems): void
    {
        if ($nodes > $this->limits->nodes) {
            $problems[] = sprintf('The model has %s nodes (separate parts); the limit is %s. Ask the supplier to merge parts that do not need to be separate.', number_format($nodes), number_format($this->limits->nodes));
        }

        if ($materials > $this->limits->materials) {
            $problems[] = sprintf('The model has %s materials; the limit is %s. Ask the supplier to merge materials that look the same.', number_format($materials), number_format($this->limits->materials));
        }
    }

    /**
     * BR-3D-4: the file must be self-contained. Embedded data ("data:" addresses) is allowed.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $problems
     */
    private function checkOutsideFiles(array $document, array &$problems): void
    {
        $outside = collect([...$this->items($document, 'buffers'), ...$this->items($document, 'images')])
            ->map(fn (array $item): mixed => $item['uri'] ?? null)
            ->filter(fn (mixed $uri): bool => is_string($uri) && ! str_starts_with($uri, 'data:'))
            ->values()
            ->all();

        if ($outside !== []) {
            $problems[] = sprintf('The model refers to outside files (%s). Ask the supplier to export one self-contained .glb with everything embedded.', $this->names($outside));
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<string>  $problems
     * @return list<string> the extensions the file requires
     */
    private function checkExtensions(array $document, array &$problems): array
    {
        $required = array_values(array_filter((array) ($document['extensionsRequired'] ?? []), 'is_string'));
        $unsupported = array_values(array_diff($required, self::SUPPORTED_REQUIRED_EXTENSIONS));

        if ($unsupported !== []) {
            $problems[] = sprintf('The model needs features the viewer cannot show (%s). Ask the supplier to export without them.', $this->names($unsupported));
        }

        return $required;
    }

    /**
     * Every reference points to something in the file and every byte range fits in its data (glTF 2.0
     * §3.6); positions declare their bounds; the nodes form trees, each node with one parent at most
     * (§3.5); and there is a scene to show.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $problems
     * @return array{views: array<int, array{usable: int, held: int}>, children: array<int, list<int>>, roots: list<int>}
     */
    private function checkStructure(array $document, array &$problems): array
    {
        $damage = [];
        $views = $this->checkBufferViews($document, $this->checkBuffers($document, $damage), $damage);
        $this->checkAccessors($document, $views, $damage);
        $this->checkMeshes($document, $views, $damage);
        $this->checkTextureReferences($document, $views, $damage);
        $children = $this->checkHierarchy($document, $damage);
        $roots = $this->sceneRoots($document, $damage);

        if ($damage !== []) {
            $problems[] = sprintf('The file is damaged or was exported incorrectly: %s. Ask the supplier for a new export.', $this->phrases(array_values(array_unique($damage))));
        }

        if ($roots === []) {
            $problems[] = 'The file has no scene to show. Ask the supplier to export the model with its scene.';
        }

        return ['views' => $views, 'children' => $children, 'roots' => $roots];
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<string>  $damage
     * @return array<int, int> the size of each buffer whose data is all there
     */
    private function checkBuffers(array $document, array &$damage): array
    {
        $sizes = [];

        foreach ($this->items($document, 'buffers') as $index => $buffer) {
            $this->keepWithinTime();
            $size = $buffer['byteLength'] ?? null;
            $uri = $buffer['uri'] ?? null;

            if (! is_int($size) || $size < 1) {
                $damage[] = "buffer {$index} has no size";

                continue;
            }

            $available = match (true) {
                is_string($uri) && str_starts_with($uri, 'data:') => strlen($this->embeddedBuffer($document, $index) ?? ''),
                // An outside file is reported on its own; a Meshopt fallback buffer is filled by decompression.
                is_string($uri), $this->isMeshoptFallback($buffer) => $size,
                $index === 0 => $this->binaryLength,
                default => 0,
            };

            if ($available < $size) {
                $damage[] = "buffer {$index} holds less data than it declares";

                continue;
            }

            $sizes[$index] = $size;
        }

        return $sizes;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, int>  $bufferSizes
     * @param  list<string>  $damage
     * @return array<int, array{usable: int, held: int}> each view's bytes an accessor may read, and the bytes the viewer holds for it (decompressed, for Meshopt)
     */
    private function checkBufferViews(array $document, array $bufferSizes, array &$damage): array
    {
        $views = [];

        foreach ($this->items($document, 'bufferViews') as $index => $view) {
            $this->keepWithinTime();

            if (! $this->fits($view, $bufferSizes)) {
                $damage[] = "buffer view {$index} reaches past the end of its buffer";

                continue;
            }

            $meshopt = $view['extensions']['EXT_meshopt_compression'] ?? null;

            if (! is_array($meshopt)) {
                $views[$index] = ['usable' => $view['byteLength'], 'held' => $view['byteLength']];

                continue;
            }

            $count = $meshopt['count'] ?? null;
            $stride = $meshopt['byteStride'] ?? null;

            if (! $this->fits($meshopt, $bufferSizes) || ! is_int($count) || $count < 1 || ! is_int($stride) || $stride < 1 || $stride > 256 || ! in_array($meshopt['mode'] ?? null, self::MESHOPT_MODES, true)) {
                $damage[] = "the compressed data of buffer view {$index} is not described properly";

                continue;
            }

            $views[$index] = ['usable' => min($view['byteLength'], $count * $stride), 'held' => $count * $stride];
        }

        return $views;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, array{usable: int, held: int}>  $views
     * @param  list<string>  $damage
     */
    private function checkAccessors(array $document, array $views, array &$damage): void
    {
        $bufferViews = $this->items($document, 'bufferViews');

        foreach ($this->items($document, 'accessors') as $index => $accessor) {
            $this->keepWithinTime();
            $elementBytes = $this->elementBytes($accessor);
            $count = $accessor['count'] ?? null;
            $view = $accessor['bufferView'] ?? null;

            if ($elementBytes === null || ! is_int($count) || $count < 1) {
                $damage[] = "accessor {$index} is not described properly";

                continue;
            }

            // No buffer view: all zeros, or filled from a Draco stream.
            if ($view === null) {
                continue;
            }

            $offset = $accessor['byteOffset'] ?? 0;
            $stride = is_int($view) ? ($bufferViews[$view]['byteStride'] ?? $elementBytes) : null;

            if (! is_int($view) || ! isset($views[$view]) || ! is_int($offset) || $offset < 0 || ! is_int($stride) || $offset + ($count - 1) * $stride + $elementBytes > $views[$view]['usable']) {
                $damage[] = "accessor {$index} reaches past the end of its data";
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, array{usable: int, held: int}>  $views
     * @param  list<string>  $damage
     */
    private function checkMeshes(array $document, array $views, array &$damage): void
    {
        $accessors = $this->items($document, 'accessors');
        $materials = $this->items($document, 'materials');

        foreach ($this->items($document, 'meshes') as $index => $mesh) {
            foreach ($this->primitives($mesh) as $primitive) {
                $this->keepWithinTime();
                $attributes = $primitive['attributes'] ?? null;
                $references = is_array($attributes) && $attributes !== [] && ! array_is_list($attributes) ? array_values($attributes) : [null];

                if (array_key_exists('indices', $primitive)) {
                    $references[] = $primitive['indices'];
                }

                $draco = $primitive['extensions']['KHR_draco_mesh_compression'] ?? null;
                $missing = collect($references)->contains(fn (mixed $reference): bool => ! is_int($reference) || ! isset($accessors[$reference]))
                    || (array_key_exists('material', $primitive) && (! is_int($primitive['material']) || ! isset($materials[$primitive['material']])))
                    || (is_array($draco) && (! is_int($draco['bufferView'] ?? null) || ! isset($views[$draco['bufferView']])));

                if ($missing) {
                    $damage[] = $this->label('mesh', $mesh, $index).' refers to data that is not in the file';
                } elseif (in_array($primitive['mode'] ?? 4, self::SURFACE_MODES, true) && is_int($attributes['POSITION'] ?? null) && $this->positionBounds($accessors[$attributes['POSITION']]) === null) {
                    $damage[] = 'the positions of '.$this->label('mesh', $mesh, $index).' do not state their bounds (min and max)';
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<int, array{usable: int, held: int}>  $views
     * @param  list<string>  $damage
     */
    private function checkTextureReferences(array $document, array $views, array &$damage): void
    {
        $images = $this->items($document, 'images');
        $textures = $this->items($document, 'textures');

        foreach ($images as $index => $image) {
            $view = $image['bufferView'] ?? null;

            if (($view === null && ! is_string($image['uri'] ?? null)) || ($view !== null && (! is_int($view) || ! isset($views[$view])))) {
                $damage[] = "image {$index} refers to data that is not in the file";
            }
        }

        foreach ($textures as $index => $texture) {
            $sources = [$texture['source'] ?? null, $texture['extensions']['KHR_texture_basisu']['source'] ?? null, $texture['extensions']['EXT_texture_webp']['source'] ?? null];

            foreach (array_filter($sources, fn (mixed $source): bool => $source !== null) as $source) {
                if (! is_int($source) || ! isset($images[$source])) {
                    $damage[] = "texture {$index} refers to an image that is not in the file";
                }
            }
        }

        foreach ($this->items($document, 'materials') as $index => $material) {
            foreach (self::MATERIAL_TEXTURES as $slot) {
                $reference = data_get($material, [...$slot, 'index']);

                if (data_get($material, $slot) !== null && (! is_int($reference) || ! isset($textures[$reference]))) {
                    $damage[] = $this->label('material', $material, $index).' refers to a texture that is not in the file';
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<string>  $damage
     * @return array<int, list<int>> each node's children
     */
    private function checkHierarchy(array $document, array &$damage): array
    {
        $nodes = $this->items($document, 'nodes');
        $meshes = $this->items($document, 'meshes');
        $children = [];
        $parents = [];

        foreach ($nodes as $index => $node) {
            $this->keepWithinTime();
            $children[$index] = [];

            if (array_key_exists('mesh', $node) && (! is_int($node['mesh']) || ! isset($meshes[$node['mesh']]))) {
                $damage[] = "node {$index} refers to a mesh that is not in the file";
            }

            foreach ((array) ($node['children'] ?? []) as $child) {
                if (! is_int($child) || ! isset($nodes[$child]) || $child === $index) {
                    $damage[] = "node {$index} lists a child that is not in the file";
                } elseif (isset($parents[$child])) {
                    $damage[] = "node {$child} has more than one parent";
                } else {
                    $parents[$child] = $index;
                    $children[$index][] = $child;
                }
            }
        }

        // A node that no tree reaches from its root is part of a loop.
        $reached = [];
        $pending = array_keys(array_diff_key($nodes, $parents));

        while ($pending !== []) {
            $index = array_pop($pending);
            $reached[$index] = true;
            array_push($pending, ...$children[$index]);
        }

        if (count($reached) < count($nodes)) {
            $damage[] = 'some nodes are their own ancestors';
        }

        return $children;
    }

    /**
     * The nodes of the scene the viewer shows: `scene`, or the first scene.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $damage
     * @return list<int>
     */
    private function sceneRoots(array $document, array &$damage): array
    {
        $index = $document['scene'] ?? 0;
        $roots = is_int($index) ? ($this->items($document, 'scenes')[$index]['nodes'] ?? null) : null;
        $nodes = $this->items($document, 'nodes');

        if (! is_array($roots)) {
            return [];
        }

        $valid = array_values(array_filter($roots, fn (mixed $root): bool => is_int($root) && isset($nodes[$root])));

        if (count($valid) < count($roots)) {
            $damage[] = "scene {$index} lists a node that is not in the file";
        }

        return $valid;
    }

    /**
     * Triangles shown (each mesh once per node that shows it; for Draco, the triangles its stream holds),
     * the pieces drawn separately, the compression of every surface, and the geometry's size once
     * decompressed.
     *
     * @param  array<string, mixed>  $document
     * @param  array<int, array{usable: int, held: int}>  $views
     * @param  list<string>  $problems
     * @return array{triangles: int, draw_calls: int, geometry_bytes: int, compression: list<string>}
     */
    private function checkGeometry(array $document, array $views, array &$problems): array
    {
        $accessors = $this->items($document, 'accessors');
        $bufferViews = $this->items($document, 'bufferViews');
        $meshTriangles = [];
        $meshDrawCalls = [];
        $heldViews = [];
        $heldAccessors = [];
        $streams = [];
        $methods = [];
        $unreadable = [];
        $mismatched = [];
        $surfaces = 0;
        $uncompressed = 0;

        foreach ($this->items($document, 'meshes') as $index => $mesh) {
            $meshTriangles[$index] = 0;
            $meshDrawCalls[$index] = 0;

            foreach ($this->primitives($mesh) as $primitive) {
                $this->keepWithinTime();
                $meshDrawCalls[$index]++;

                foreach ([...(is_array($primitive['attributes'] ?? null) ? $primitive['attributes'] : []), $primitive['indices'] ?? null] as $reference) {
                    $view = is_int($reference) ? ($accessors[$reference]['bufferView'] ?? null) : false;

                    if (is_int($view) && isset($views[$view])) {
                        $heldViews[$view] = $views[$view]['held'];
                    } elseif ($view === null && isset($accessors[$reference])) {
                        $heldAccessors[$reference] = $this->count($accessors, $reference) * ($this->elementBytes($accessors[$reference]) ?? 0);
                    }
                }

                $mode = $primitive['mode'] ?? 4;

                if (! in_array($mode, self::SURFACE_MODES, true)) {
                    continue;
                }

                $positions = $primitive['attributes']['POSITION'] ?? null;
                $positionView = is_int($positions) ? ($accessors[$positions]['bufferView'] ?? null) : null;
                $count = $this->count($accessors, $primitive['indices'] ?? $positions);
                $declared = $mode === 4 ? intdiv($count, 3) : max(0, $count - 2);
                $primitiveTriangles = $declared;
                $draco = $primitive['extensions']['KHR_draco_mesh_compression'] ?? null;

                if (is_array($draco)) {
                    $view = $draco['bufferView'] ?? null;
                    $stream = is_int($view) ? ($streams[$view] ??= $this->dracoStream->inspect($this->viewBytes($document, $view) ?? '') ?? false) : false;

                    if ($stream === false) {
                        $unreadable[] = $this->label('mesh', $mesh, $index);
                    } else {
                        $primitiveTriangles = $stream['faces'];

                        if ($mode === 4 && $stream['faces'] !== $declared) {
                            $mismatched[] = sprintf('%s declares %s %s but holds %s', $this->label('mesh', $mesh, $index), number_format($declared), Str::plural('triangle', $declared), number_format($stream['faces']));
                        }
                    }
                }

                if ($primitiveTriangles === 0) {
                    continue;
                }

                $meshTriangles[$index] += $primitiveTriangles;
                $surfaces++;

                $method = match (true) {
                    is_array($draco) => 'draco',
                    is_int($positionView) && is_array($bufferViews[$positionView]['extensions']['EXT_meshopt_compression'] ?? null) => 'meshopt',
                    default => null,
                };

                $method === null ? $uncompressed++ : $methods[$method] = true;
            }
        }

        $shown = collect($this->items($document, 'nodes'))
            ->map(fn (array $node): mixed => $node['mesh'] ?? null)
            ->filter(fn (mixed $mesh): bool => is_int($mesh) && isset($meshTriangles[$mesh]));
        $triangles = $shown->sum(fn (int $mesh): int => $meshTriangles[$mesh]);
        $drawCalls = $shown->sum(fn (int $mesh): int => $meshDrawCalls[$mesh]);
        $geometryBytes = array_sum($heldViews) + array_sum($heldAccessors);

        if ($triangles === 0) {
            $problems[] = 'The file contains no 3D surfaces to show.';
        } elseif ($triangles > $this->limits->triangles) {
            $problems[] = sprintf('The model has %s triangles; the limit is %s. Ask the supplier to reduce the detail.', number_format($triangles), number_format($this->limits->triangles));
        }

        if ($uncompressed > 0) {
            $problems[] = sprintf('%d of the model\'s %d surface parts are not compressed. Ask the supplier to export with Draco or Meshopt compression.', $uncompressed, $surfaces);
        }

        if ($unreadable !== []) {
            $problems[] = sprintf('The compressed geometry of %s cannot be read. Ask the supplier for a new export with a current Draco encoder.', $this->phrases(array_values(array_unique($unreadable))));
        }

        if ($mismatched !== []) {
            $problems[] = sprintf('The compressed geometry does not match the file\'s description (%s). The file is damaged; ask the supplier for a new export.', $this->phrases(array_values(array_unique($mismatched))));
        }

        if ($drawCalls > $this->limits->drawCalls) {
            $problems[] = sprintf('The model is drawn in %s separate pieces (one for each part of each mesh shown); the limit is %s, because every piece slows the viewer, most of all on phones. Ask the supplier to merge meshes that share a material.', number_format($drawCalls), number_format($this->limits->drawCalls));
        }

        if ($geometryBytes > $this->limits->geometryBytes) {
            $problems[] = sprintf('Once decompressed, the model\'s geometry takes %s MB; the limit is %s MB. Ask the supplier to remove vertex data the model does not need (extra texture coordinates, vertex colours) or reduce the detail.', $this->megabytes($geometryBytes), $this->megabytes($this->limits->geometryBytes));
        }

        return ['triangles' => $triangles, 'draw_calls' => $drawCalls, 'geometry_bytes' => $geometryBytes, 'compression' => array_keys($methods)];
    }

    /**
     * Format and pixel size of every embedded image, read from its own bytes.
     *
     * @param  array<string, mixed>  $document
     * @param  list<string>  $problems
     * @return array{count: int, pixels: int, largest_side: int, formats: list<string>}
     */
    private function checkTextures(array $document, array &$problems): array
    {
        $images = $this->items($document, 'images');
        $pixels = 0;
        $largestSide = 0;
        $formats = [];
        $unreadable = [];
        $oversized = [];

        foreach ($images as $index => $image) {
            $this->keepWithinTime();
            $uri = $image['uri'] ?? null;

            // An outside file is reported on its own.
            if (is_string($uri) && ! str_starts_with($uri, 'data:')) {
                continue;
            }

            $bytes = is_string($uri) ? $this->embeddedData($uri) : $this->viewBytes($document, $image['bufferView'] ?? null);
            $dimensions = $bytes === null ? null : $this->imageDimensions->read($bytes);
            $name = is_string($image['name'] ?? null) ? $image['name'] : "image {$index}";

            if ($dimensions === null) {
                $unreadable[] = $name;

                continue;
            }

            ['format' => $format, 'width' => $width, 'height' => $height] = $dimensions;
            $formats[$format] = true;
            $pixels += $width * $height;
            $largestSide = max($largestSide, $width, $height);

            if (max($width, $height) > $this->limits->textureSide) {
                $oversized[] = "{$name}, {$width} × {$height}";
            }
        }

        if (count($images) > $this->limits->textures) {
            $problems[] = sprintf('The model has %d textures; the limit is %d. Ask the supplier to combine textures (a texture atlas) or reuse them across materials.', count($images), $this->limits->textures);
        }

        if ($unreadable !== []) {
            $problems[] = sprintf('These textures cannot be read, or are not PNG, JPEG, WebP or KTX2 images: %s. Ask the supplier to export the textures in one of those formats.', $this->names($unreadable));
        }

        if ($oversized !== []) {
            $problems[] = sprintf('These textures are larger than %1$s pixels on a side: %2$s. Ask the supplier to reduce them to at most %1$s × %1$s pixels.', number_format($this->limits->textureSide), $this->names($oversized));
        }

        if ($pixels > $this->limits->texturePixels) {
            $problems[] = sprintf('The textures add up to %s pixels; the limit is %s pixels in all. Ask the supplier to make the textures smaller or use fewer of them.', number_format($pixels), number_format($this->limits->texturePixels));
        }

        return ['count' => count($images), 'pixels' => $pixels, 'largest_side' => $largestSide, 'formats' => array_keys($formats)];
    }

    /**
     * The model's size in metres along each axis: the bounds each position accessor states, placed by the
     * node transforms of the scene shown. A size far outside the range usually means a wrong export unit.
     *
     * @param  array<string, mixed>  $document
     * @param  list<int>  $roots
     * @param  array<int, list<int>>  $children
     * @param  list<string>  $problems
     * @return list<float>|null null when there is nothing to measure
     */
    private function checkDimensions(array $document, array $roots, array $children, array &$problems): ?array
    {
        $nodes = $this->items($document, 'nodes');
        $meshes = $this->items($document, 'meshes');
        $accessors = $this->items($document, 'accessors');
        $low = [INF, INF, INF];
        $high = [-INF, -INF, -INF];
        $pending = array_map(fn (int $root): array => [$root, self::IDENTITY], $roots);
        $placed = [];

        while ($pending !== []) {
            $this->keepWithinTime();
            [$index, $parent] = array_pop($pending);

            if (isset($placed[$index])) {
                continue;
            }

            $placed[$index] = true;
            $matrix = $this->multiply($parent, $this->localMatrix($nodes[$index]));
            $mesh = $nodes[$index]['mesh'] ?? null;

            foreach (is_int($mesh) && isset($meshes[$mesh]) ? $this->primitives($meshes[$mesh]) : [] as $primitive) {
                $positions = $primitive['attributes']['POSITION'] ?? null;
                $bounds = is_int($positions) && isset($accessors[$positions]) ? $this->positionBounds($accessors[$positions]) : null;

                foreach ($bounds === null ? [] : $this->corners(...$bounds) as $corner) {
                    $point = $this->transform($matrix, $corner);

                    for ($axis = 0; $axis < 3; $axis++) {
                        $low[$axis] = min($low[$axis], $point[$axis]);
                        $high[$axis] = max($high[$axis], $point[$axis]);
                    }
                }
            }

            foreach ($children[$index] ?? [] as $child) {
                $pending[] = [$child, $matrix];
            }
        }

        if ($low[0] === INF) {
            return null;
        }

        $size = [$high[0] - $low[0], $high[1] - $low[1], $high[2] - $low[2]];
        $longest = max($size);

        if ($longest < $this->limits->smallestMetres || $longest > $this->limits->largestMetres) {
            $problems[] = sprintf('The model measures %s m along its longest side; it must be between %s m and %s m. It was probably exported in the wrong unit: glTF uses metres, so ask the supplier to export in metres.', $this->number($longest), $this->number($this->limits->smallestMetres), $this->number($this->limits->largestMetres));
        }

        return array_map(fn (float $length): float => round($length, 2), $size);
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  list<string>  $problems
     * @return list<array{node_name: string, type: AnchorType, reference: string}>
     */
    private function checkAnchors(array $document, array &$problems): array
    {
        $anchors = [];
        $malformed = [];
        $duplicates = [];

        foreach ($this->items($document, 'nodes') as $node) {
            $name = $node['name'] ?? null;

            if (! is_string($name) || ! $this->anchorName->looksLikeAnchor($name)) {
                continue;
            }

            $anchor = $this->anchorName->parse($name);

            if ($anchor === null) {
                $malformed[$name] = $name;
            } elseif (isset($anchors[$name])) {
                $duplicates[$name] = $name;
            } else {
                $anchors[$name] = ['node_name' => $name, ...$anchor];
            }
        }

        if ($malformed !== []) {
            $problems[] = sprintf('These names start like anchors but do not follow the naming convention: %s. Use SG_SPACE_ followed by the space code in capitals, digits and hyphens; SG_AMENITY_ followed by a lowercase name with hyphens; or SG_CAM_ followed by a number from 1.', $this->names(array_values($malformed)));
        }

        if ($duplicates !== []) {
            $problems[] = sprintf('Each anchor name must be used once, but these appear more than once: %s.', $this->names(array_values($duplicates)));
        }

        if ($anchors === [] && $malformed === []) {
            $problems[] = 'No anchors were found. The supplier names nodes SG_SPACE_…, SG_AMENITY_… and SG_CAM_… so that spaces, amenities and camera positions can be linked; ask them to add the names.';
        }

        return array_values($anchors);
    }

    private function keepWithinTime(): void
    {
        if (hrtime(true) > $this->deadline) {
            throw new ChecksTimedOut;
        }
    }

    /**
     * A buffer view's bytes, from the binary chunk or an embedded buffer; null when they are not there,
     * including a Meshopt view, whose data exists only once decompressed.
     *
     * @param  array<string, mixed>  $document
     */
    private function viewBytes(array $document, mixed $index): ?string
    {
        $view = is_int($index) ? ($this->items($document, 'bufferViews')[$index] ?? null) : null;
        $buffer = $view['buffer'] ?? null;
        $offset = $view['byteOffset'] ?? 0;
        $length = $view['byteLength'] ?? null;

        if ($view === null || isset($view['extensions']['EXT_meshopt_compression']) || ! is_int($buffer) || ! is_int($offset) || ! is_int($length) || $offset < 0 || $length < 1) {
            return null;
        }

        $definition = $this->items($document, 'buffers')[$buffer] ?? null;

        if (is_string($definition['uri'] ?? null)) {
            $data = $this->embeddedBuffer($document, $buffer);

            return $data !== null && $offset + $length <= strlen($data) ? substr($data, $offset, $length) : null;
        }

        if ($buffer !== 0 || $definition === null || $this->isMeshoptFallback($definition) || $offset + $length > $this->binaryLength) {
            return null;
        }

        fseek($this->handle, $this->binaryOffset + $offset);
        $bytes = fread($this->handle, $length);

        return is_string($bytes) && strlen($bytes) === $length ? $bytes : null;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function embeddedBuffer(array $document, int $index): ?string
    {
        $uri = $this->items($document, 'buffers')[$index]['uri'] ?? null;

        if (! isset($this->embeddedBuffers[$index]) && is_string($uri) && str_starts_with($uri, 'data:')) {
            $data = $this->embeddedData($uri);

            if ($data !== null) {
                $this->embeddedBuffers[$index] = $data;
            }
        }

        return $this->embeddedBuffers[$index] ?? null;
    }

    /**
     * The bytes of a "data:" address, base64 or percent-encoded.
     */
    private function embeddedData(string $uri): ?string
    {
        $comma = strpos($uri, ',');

        if ($comma === false) {
            return null;
        }

        $data = substr($uri, $comma + 1);
        $bytes = str_ends_with(substr($uri, 0, $comma), ';base64') ? base64_decode($data, true) : rawurldecode($data);

        return $bytes === false ? null : $bytes;
    }

    /**
     * @param  array<string, mixed>  $buffer
     */
    private function isMeshoptFallback(array $buffer): bool
    {
        return ($buffer['extensions']['EXT_meshopt_compression']['fallback'] ?? false) === true;
    }

    /**
     * Whether a byte range (buffer, byteOffset, byteLength) lies within its buffer.
     *
     * @param  array<string, mixed>  $range
     * @param  array<int, int>  $bufferSizes
     */
    private function fits(array $range, array $bufferSizes): bool
    {
        $buffer = $range['buffer'] ?? null;
        $offset = $range['byteOffset'] ?? 0;
        $length = $range['byteLength'] ?? null;

        return is_int($buffer) && isset($bufferSizes[$buffer]) && is_int($offset) && $offset >= 0 && is_int($length) && $length >= 1 && $offset + $length <= $bufferSizes[$buffer];
    }

    /**
     * @param  array<string, mixed>  $accessor
     */
    private function elementBytes(array $accessor): ?int
    {
        $componentBytes = is_int($accessor['componentType'] ?? null) ? (self::COMPONENT_BYTES[$accessor['componentType']] ?? null) : null;
        $components = is_string($accessor['type'] ?? null) ? (self::TYPE_COMPONENTS[$accessor['type']] ?? null) : null;

        return $componentBytes === null || $components === null ? null : $componentBytes * $components;
    }

    /**
     * The bounds a position accessor states, as stored values read the way the viewer reads them.
     *
     * @param  array<string, mixed>  $accessor
     * @return array{0: list<float>, 1: list<float>}|null
     */
    private function positionBounds(array $accessor): ?array
    {
        $min = $accessor['min'] ?? null;
        $max = $accessor['max'] ?? null;

        if (! is_array($min) || ! is_array($max) || count($min) !== 3 || count($max) !== 3 || ! collect([...$min, ...$max])->every(fn (mixed $value): bool => is_int($value) || is_float($value))) {
            return null;
        }

        $one = ($accessor['normalized'] ?? false) === true && is_int($accessor['componentType'] ?? null) ? (self::NORMALIZED_ONE[$accessor['componentType']] ?? 1) : 1;
        $read = fn (int|float $value): float => max($value / $one, $one === 1 ? -INF : -1.0);

        return [array_map($read, array_values($min)), array_map($read, array_values($max))];
    }

    /**
     * @param  list<float>  $min
     * @param  list<float>  $max
     * @return list<list<float>>
     */
    private function corners(array $min, array $max): array
    {
        $corners = [];

        foreach ([$min[0], $max[0]] as $x) {
            foreach ([$min[1], $max[1]] as $y) {
                foreach ([$min[2], $max[2]] as $z) {
                    $corners[] = [$x, $y, $z];
                }
            }
        }

        return $corners;
    }

    /**
     * A node's transform, column-major as glTF writes matrices: its matrix, or translation × rotation × scale.
     *
     * @param  array<string, mixed>  $node
     * @return list<float>
     */
    private function localMatrix(array $node): array
    {
        $matrix = $this->numbers($node['matrix'] ?? null, 16);

        if ($matrix !== null) {
            return $matrix;
        }

        [$tx, $ty, $tz] = $this->numbers($node['translation'] ?? null, 3) ?? [0.0, 0.0, 0.0];
        [$x, $y, $z, $w] = $this->numbers($node['rotation'] ?? null, 4) ?? [0.0, 0.0, 0.0, 1.0];
        [$sx, $sy, $sz] = $this->numbers($node['scale'] ?? null, 3) ?? [1.0, 1.0, 1.0];

        return [
            (1 - 2 * ($y * $y + $z * $z)) * $sx, 2 * ($x * $y + $z * $w) * $sx, 2 * ($x * $z - $y * $w) * $sx, 0.0,
            2 * ($x * $y - $z * $w) * $sy, (1 - 2 * ($x * $x + $z * $z)) * $sy, 2 * ($y * $z + $x * $w) * $sy, 0.0,
            2 * ($x * $z + $y * $w) * $sz, 2 * ($y * $z - $x * $w) * $sz, (1 - 2 * ($x * $x + $y * $y)) * $sz, 0.0,
            $tx, $ty, $tz, 1.0,
        ];
    }

    /**
     * @return list<float>|null
     */
    private function numbers(mixed $values, int $count): ?array
    {
        return is_array($values) && array_is_list($values) && count($values) === $count && collect($values)->every(fn (mixed $value): bool => is_int($value) || is_float($value))
            ? array_map('floatval', $values)
            : null;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     * @return list<float>
     */
    private function multiply(array $a, array $b): array
    {
        $product = [];

        for ($column = 0; $column < 4; $column++) {
            for ($row = 0; $row < 4; $row++) {
                $product[$column * 4 + $row] = $a[$row] * $b[$column * 4] + $a[4 + $row] * $b[$column * 4 + 1] + $a[8 + $row] * $b[$column * 4 + 2] + $a[12 + $row] * $b[$column * 4 + 3];
            }
        }

        return $product;
    }

    /**
     * @param  list<float>  $matrix
     * @param  list<float>  $point
     * @return list<float>
     */
    private function transform(array $matrix, array $point): array
    {
        [$x, $y, $z] = $point;

        return [
            $matrix[0] * $x + $matrix[4] * $y + $matrix[8] * $z + $matrix[12],
            $matrix[1] * $x + $matrix[5] * $y + $matrix[9] * $z + $matrix[13],
            $matrix[2] * $x + $matrix[6] * $y + $matrix[10] * $z + $matrix[14],
        ];
    }

    /**
     * The entries of a top-level list that are objects, keeping their index.
     *
     * @param  array<string, mixed>  $document
     * @return array<int, array<string, mixed>>
     */
    private function items(array $document, string $key): array
    {
        $items = $document[$key] ?? [];

        return is_array($items) && array_is_list($items) ? array_filter($items, 'is_array') : [];
    }

    /**
     * @param  array<string, mixed>  $mesh
     * @return array<int, array<string, mixed>>
     */
    private function primitives(array $mesh): array
    {
        return is_array($mesh['primitives'] ?? null) && array_is_list($mesh['primitives']) ? array_filter($mesh['primitives'], 'is_array') : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $accessors
     */
    private function count(array $accessors, mixed $index): int
    {
        $count = is_int($index) ? ($accessors[$index]['count'] ?? 0) : 0;

        return is_int($count) ? max(0, $count) : 0;
    }

    /**
     * "mesh “Floor”", or "mesh 3" when it has no name.
     *
     * @param  array<string, mixed>  $item
     */
    private function label(string $kind, array $item, int $index): string
    {
        return is_string($item['name'] ?? null) && $item['name'] !== '' ? $kind.' “'.Str::limit($item['name'], 60).'”' : "{$kind} {$index}";
    }

    /**
     * @param  list<string>  $names
     */
    private function names(array $names): string
    {
        return $this->phrases(array_map(fn (string $name): string => '“'.Str::limit($name, 60).'”', $names));
    }

    /**
     * @param  list<string>  $phrases
     */
    private function phrases(array $phrases): string
    {
        $shown = implode(', ', array_slice($phrases, 0, self::NAMES_SHOWN));
        $more = count($phrases) - self::NAMES_SHOWN;

        return $more > 0 ? "{$shown} and {$more} more" : $shown;
    }

    /**
     * Rounded up, so a file just over the limit never reads as equal to it.
     */
    private function megabytes(int $bytes): string
    {
        return number_format(ceil($bytes / 1024 / 1024 * 10) / 10, 1);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, $value < 10 ? 2 : 1), '0'), '.');
    }
}
