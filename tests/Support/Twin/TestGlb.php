<?php

namespace Tests\Support\Twin;

/**
 * Builds GLB files for tests and for the browser checks; every model is a test fixture, labelled as
 * such, showing no real location.
 *
 * - `model()`: three boxes on a floor, with a space, an amenity and two camera anchors. Its positions
 *   are compressed with EXT_meshopt_compression (attribute mode, every byte group stored raw, the
 *   simplest valid form of that format), so the viewer decodes it like a supplier's file.
 * - `dracoModel()`: the same scene, each box a Draco mesh (KHR_draco_mesh_compression).
 * - `panelsModel()`: wavy panels in a grid, as large as needed, for the limits and the performance
 *   checks: each panel a mesh of 2,000 triangles with Meshopt-compressed positions, normals and texture
 *   coordinates, textured with generated PNG images.
 * - `document()`, `withDocument()` and `withIndexCount()`: the small model's description, changed, for
 *   checks of single rules.
 */
final class TestGlb
{
    private const int MAGIC = 0x46546C67;

    private const int JSON_CHUNK = 0x4E4F534A;

    private const int BIN_CHUNK = 0x004E4942;

    /** A unit box: 8 corners, 12 triangles. */
    private const array BOX_POSITIONS = [
        -0.5, -0.5, -0.5, 0.5, -0.5, -0.5, 0.5, 0.5, -0.5, -0.5, 0.5, -0.5,
        -0.5, -0.5, 0.5, 0.5, -0.5, 0.5, 0.5, 0.5, 0.5, -0.5, 0.5, 0.5,
    ];

    private const array BOX_INDICES = [
        0, 2, 1, 0, 3, 2, 4, 5, 6, 4, 6, 7, 0, 1, 5, 0, 5, 4,
        3, 7, 6, 3, 6, 2, 0, 4, 7, 0, 7, 3, 1, 2, 6, 1, 6, 5,
    ];

    /** Cells of one panel across and along: 25 × 40 cells, 2,000 triangles, 26 × 41 vertices. */
    private const int PANEL_COLUMNS = 25;

    private const int PANEL_ROWS = 40;

    /**
     * The bytes of a GLB file with this description and binary chunk.
     *
     * @param  array<string, mixed>  $document
     */
    public static function bytes(array $document, string $binary = ''): string
    {
        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
        $chunks = pack('VV', strlen($json), self::JSON_CHUNK).$json;

        if ($binary !== '') {
            $binary .= str_repeat("\0", (4 - strlen($binary) % 4) % 4);
            $chunks .= pack('VV', strlen($binary), self::BIN_CHUNK).$binary;
        }

        return pack('VVV', self::MAGIC, 2, 12 + strlen($chunks)).$chunks;
    }

    /**
     * Writes the bytes to a new temporary file and returns its path.
     */
    public static function write(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'glb');
        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * The test model's description; $changes replace top-level entries. Replacing the nodes also makes
     * the scene show every one of them, unless the scenes are replaced too.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public static function document(array $changes = []): array
    {
        $document = [...self::boxes(draco: false)['document'], ...$changes];

        if (array_key_exists('nodes', $changes) && ! array_key_exists('scenes', $changes) && is_array($changes['nodes'])) {
            $document['scenes'] = [['name' => 'TEST FIXTURE', 'nodes' => array_keys($changes['nodes'])]];
        }

        return $document;
    }

    /**
     * A GLB file with this description and the test model's binary data.
     *
     * @param  array<string, mixed>  $document
     */
    public static function withDocument(array $document): string
    {
        return self::bytes($document, self::boxes(draco: false)['binary']);
    }

    /**
     * A GLB file with this description, its index accessor (shared by every mesh) holding $count
     * indices; the binary data grows to hold them.
     *
     * @param  array<string, mixed>  $document
     */
    public static function withIndexCount(array $document, int $count): string
    {
        $binary = str_pad(self::boxes(draco: false)['binary'], $document['bufferViews'][1]['byteOffset'] + $count * 2, "\0");
        $document['accessors'][1]['count'] = $count;
        $document['bufferViews'][1]['byteLength'] = $count * 2;
        $document['buffers'][0]['byteLength'] = strlen($binary);

        return self::bytes($document, $binary);
    }

    /**
     * The test model as a complete GLB file: description and Meshopt-compressed geometry.
     */
    public static function model(): string
    {
        ['document' => $document, 'binary' => $binary] = self::boxes(draco: false);

        return self::bytes($document, $binary);
    }

    /**
     * The test model, its binary chunk padded so that the file is exactly $bytes long (a multiple of 4).
     */
    public static function modelOfSize(int $bytes): string
    {
        ['document' => $document, 'binary' => $binary] = self::boxes(draco: false);

        return self::bytes($document, self::aligned($binary).str_repeat("\0", $bytes - strlen(self::bytes($document, $binary))));
    }

    /**
     * The test model with each box a Draco mesh.
     */
    public static function dracoModel(): string
    {
        ['document' => $document, 'binary' => $binary] = self::boxes(draco: true);

        return self::bytes($document, $binary);
    }

    /**
     * $panels wavy panels of 2 m × 3.2 m in rows of 25, each its own mesh of 2,000 triangles (so as many
     * pieces drawn), sharing $textures generated PNG images of $textureSide pixels square through ten
     * materials. Positions, normals and texture coordinates are Meshopt-compressed; indices are not. One
     * panel is a space anchor and a camera looks over the grid.
     */
    public static function panelsModel(int $panels, int $textures, int $textureSide, string $label): string
    {
        $vertices = self::panelVertices();
        $streams = [
            ['data' => pack('g*', ...$vertices['positions']), 'stride' => 12, 'type' => 'VEC3', 'bounds' => true],
            ['data' => pack('g*', ...$vertices['normals']), 'stride' => 12, 'type' => 'VEC3', 'bounds' => false],
            ['data' => pack('g*', ...$vertices['uvs']), 'stride' => 8, 'type' => 'VEC2', 'bounds' => false],
        ];
        $count = intdiv(strlen($streams[0]['data']), 12);
        $indices = pack('v*', ...$vertices['indices']);

        foreach ($streams as $index => $stream) {
            $streams[$index]['encoded'] = self::meshoptAttributes($stream['data'], $stream['stride']);
        }

        $binary = '';
        $fallback = 0;
        $document = [
            'asset' => ['version' => '2.0', 'generator' => "SmartGro test fixture: {$label} (not a real location)"],
            'extensionsUsed' => ['EXT_meshopt_compression'],
            'extensionsRequired' => ['EXT_meshopt_compression'],
            'scene' => 0,
            'scenes' => [['name' => 'TEST FIXTURE', 'nodes' => range(0, $panels)]],
            'nodes' => [], 'meshes' => [], 'accessors' => [], 'bufferViews' => [],
        ];

        for ($panel = 0; $panel < $panels; $panel++) {
            $attributes = [];

            foreach ($streams as $index => $stream) {
                $document['bufferViews'][] = [
                    'buffer' => 1, 'byteOffset' => $fallback, 'byteLength' => strlen($stream['data']), 'byteStride' => $stream['stride'], 'target' => 34962,
                    'extensions' => ['EXT_meshopt_compression' => ['buffer' => 0, 'byteOffset' => strlen($binary), 'byteLength' => strlen($stream['encoded']), 'byteStride' => $stream['stride'], 'mode' => 'ATTRIBUTES', 'count' => $count]],
                ];
                $binary .= self::aligned($stream['encoded']);
                $fallback += strlen($stream['data']);
                $accessor = ['bufferView' => count($document['bufferViews']) - 1, 'componentType' => 5126, 'count' => $count, 'type' => $stream['type']];
                $document['accessors'][] = $stream['bounds'] ? [...$accessor, 'min' => $vertices['min'], 'max' => $vertices['max']] : $accessor;
                $attributes[['POSITION', 'NORMAL', 'TEXCOORD_0'][$index]] = count($document['accessors']) - 1;
            }

            $document['bufferViews'][] = ['buffer' => 0, 'byteOffset' => strlen($binary), 'byteLength' => strlen($indices), 'target' => 34963];
            $binary .= self::aligned($indices);
            $document['accessors'][] = ['bufferView' => count($document['bufferViews']) - 1, 'componentType' => 5123, 'count' => count($vertices['indices']), 'type' => 'SCALAR'];
            $document['meshes'][] = ['name' => sprintf('Test panel %03d', $panel + 1), 'primitives' => [['attributes' => $attributes, 'indices' => count($document['accessors']) - 1, 'material' => $panel % 10]]];
            $document['nodes'][] = [
                'name' => $panel === 0 ? 'SG_SPACE_TEST-02' : sprintf('Test panel %03d', $panel + 1),
                'mesh' => $panel, 'translation' => [($panel % 25) * 2.4, 0, intdiv($panel, 25) * 3.6],
            ];
        }

        $rows = intdiv($panels + 24, 25);
        $document['nodes'][] = ['name' => 'SG_CAM_1', 'translation' => [29.0, 9.0, $rows * 3.6 + 8], 'rotation' => [-0.2588190451, 0, 0, 0.9659258263]];
        $images = [];

        for ($texture = 0; $texture < $textures; $texture++) {
            $png = self::png($textureSide, $texture);
            $document['bufferViews'][] = ['buffer' => 0, 'byteOffset' => strlen($binary), 'byteLength' => strlen($png)];
            $binary .= self::aligned($png);
            $images[] = ['name' => sprintf('Test texture %d', $texture + 1), 'bufferView' => count($document['bufferViews']) - 1, 'mimeType' => 'image/png'];
        }

        $document['images'] = $images;
        $document['samplers'] = [['magFilter' => 9729, 'minFilter' => 9987, 'wrapS' => 10497, 'wrapT' => 10497]];
        $document['textures'] = array_map(fn (int $image): array => ['source' => $image, 'sampler' => 0], array_keys($images));
        $document['materials'] = array_map(fn (int $material): array => [
            'name' => sprintf('Test material %d', $material + 1),
            'pbrMetallicRoughness' => [
                'baseColorFactor' => [0.75 + 0.025 * $material, 0.85, 0.95 - 0.03 * $material, 1],
                ...($textures > 0 ? ['baseColorTexture' => ['index' => $material % $textures]] : []),
                'metallicFactor' => 0, 'roughnessFactor' => 0.9,
            ],
        ], range(0, 9));
        $document['buffers'] = [
            ['byteLength' => strlen($binary)],
            ['byteLength' => $fallback, 'extensions' => ['EXT_meshopt_compression' => ['fallback' => true]]],
        ];

        return self::bytes($document, $binary);
    }

    /**
     * @return array{document: array<string, mixed>, binary: string}
     */
    private static function boxes(bool $draco): array
    {
        $material = fn (string $name, array $colour): array => ['name' => $name, 'pbrMetallicRoughness' => ['baseColorFactor' => [...$colour, 1], 'metallicFactor' => 0, 'roughnessFactor' => 0.8]];
        $box = ['min' => [-0.5, -0.5, -0.5], 'max' => [0.5, 0.5, 0.5]];

        if ($draco) {
            $binary = self::dracoMesh(self::BOX_POSITIONS, self::BOX_INDICES);
            $compression = 'KHR_draco_mesh_compression';
            $primitive = ['attributes' => ['POSITION' => 0], 'indices' => 1, 'extensions' => [$compression => ['bufferView' => 0, 'attributes' => ['POSITION' => 0]]]];
            $accessors = [
                ['componentType' => 5126, 'count' => 8, 'type' => 'VEC3', ...$box],
                ['componentType' => 5123, 'count' => count(self::BOX_INDICES), 'type' => 'SCALAR'],
            ];
            $bufferViews = [['buffer' => 0, 'byteLength' => strlen($binary)]];
            $buffers = [['byteLength' => strlen($binary)]];
        } else {
            $positions = pack('g*', ...self::BOX_POSITIONS);
            $encoded = self::meshoptAttributes($positions, 12);
            $compressed = self::aligned($encoded);
            $indices = pack('v*', ...self::BOX_INDICES);
            $binary = $compressed.$indices;
            $compression = 'EXT_meshopt_compression';
            $primitive = ['attributes' => ['POSITION' => 0], 'indices' => 1];
            $accessors = [
                ['bufferView' => 0, 'componentType' => 5126, 'count' => 8, 'type' => 'VEC3', ...$box],
                ['bufferView' => 1, 'componentType' => 5123, 'count' => count(self::BOX_INDICES), 'type' => 'SCALAR'],
            ];
            $bufferViews = [
                [
                    'buffer' => 1, 'byteLength' => strlen($positions), 'byteStride' => 12, 'target' => 34962,
                    'extensions' => [$compression => ['buffer' => 0, 'byteOffset' => 0, 'byteLength' => strlen($encoded), 'byteStride' => 12, 'mode' => 'ATTRIBUTES', 'count' => 8]],
                ],
                ['buffer' => 0, 'byteOffset' => strlen($compressed), 'byteLength' => strlen($indices), 'target' => 34963],
            ];
            $buffers = [
                ['byteLength' => strlen(self::aligned($binary))],
                ['byteLength' => strlen($positions), 'extensions' => [$compression => ['fallback' => true]]],
            ];
        }

        $document = [
            'asset' => ['version' => '2.0', 'generator' => 'SmartGro test fixture (not a real location)'],
            'extensionsUsed' => [$compression],
            'extensionsRequired' => [$compression],
            'scene' => 0,
            'scenes' => [['name' => 'TEST FIXTURE', 'nodes' => [0, 1, 2, 3, 4]]],
            'nodes' => [
                ['name' => 'Floor', 'mesh' => 0, 'translation' => [0, -0.1, 0], 'scale' => [10, 0.2, 8]],
                ['name' => 'SG_SPACE_TEST-01', 'mesh' => 1, 'translation' => [-2, 0.5, -1], 'scale' => [2, 1, 1.2]],
                ['name' => 'SG_AMENITY_test-lounge', 'mesh' => 2, 'translation' => [2.5, 0.4, 1.5], 'scale' => [1.5, 0.8, 1.5]],
                ['name' => 'SG_CAM_1', 'translation' => [0, 1.6, 5]],
                ['name' => 'SG_CAM_2', 'translation' => [4, 1.6, 0], 'rotation' => [0, M_SQRT1_2, 0, M_SQRT1_2]],
            ],
            'meshes' => [
                ['name' => 'Floor', 'primitives' => [[...$primitive, 'material' => 0]]],
                ['name' => 'Test space', 'primitives' => [[...$primitive, 'material' => 1]]],
                ['name' => 'Test lounge', 'primitives' => [[...$primitive, 'material' => 2]]],
            ],
            'materials' => [$material('Test floor', [0.75, 0.76, 0.78]), $material('Test space', [0.18, 0.55, 0.09]), $material('Test lounge', [0.85, 0.6, 0.15])],
            'accessors' => $accessors,
            'bufferViews' => $bufferViews,
            'buffers' => $buffers,
        ];

        return ['document' => $document, 'binary' => $binary];
    }

    /**
     * One panel: a grid of 26 × 41 vertices over 2 m × 3.2 m, gently waved, with its normals, texture
     * coordinates, triangles and bounds.
     *
     * @return array{positions: list<float>, normals: list<float>, uvs: list<float>, indices: list<int>, min: list<float>, max: list<float>}
     */
    private static function panelVertices(): array
    {
        $panel = ['positions' => [], 'normals' => [], 'uvs' => [], 'indices' => []];
        $height = fn (float $x, float $z): float => 0.12 * sin($x * M_PI * 2) * cos($z * M_PI * 1.5);

        for ($row = 0; $row <= self::PANEL_ROWS; $row++) {
            for ($column = 0; $column <= self::PANEL_COLUMNS; $column++) {
                $x = $column / self::PANEL_COLUMNS * 2 - 1;
                $z = $row / self::PANEL_ROWS * 3.2 - 1.6;
                $slopeX = 0.24 * M_PI * cos($x * M_PI * 2) * cos($z * M_PI * 1.5);
                $slopeZ = -0.18 * M_PI * sin($x * M_PI * 2) * sin($z * M_PI * 1.5);
                $length = sqrt($slopeX ** 2 + 1 + $slopeZ ** 2);
                array_push($panel['positions'], $x, $height($x, $z), $z);
                array_push($panel['normals'], -$slopeX / $length, 1 / $length, -$slopeZ / $length);
                array_push($panel['uvs'], $column / self::PANEL_COLUMNS, $row / self::PANEL_ROWS);
            }
        }

        for ($row = 0; $row < self::PANEL_ROWS; $row++) {
            for ($column = 0; $column < self::PANEL_COLUMNS; $column++) {
                $corner = $row * (self::PANEL_COLUMNS + 1) + $column;
                $below = $corner + self::PANEL_COLUMNS + 1;
                array_push($panel['indices'], $corner, $below, $corner + 1, $corner + 1, $below, $below + 1);
            }
        }

        $ys = array_values(array_filter($panel['positions'], fn (int $index): bool => $index % 3 === 1, ARRAY_FILTER_USE_KEY));
        $panel['min'] = [-1.0, (float) unpack('g', pack('g', min($ys)))[1], -1.6];
        $panel['max'] = [1.0, (float) unpack('g', pack('g', max($ys)))[1], 1.6];

        return $panel;
    }

    /**
     * A square RGB PNG: two-colour squares of 128 pixels, with every 40th row noise, so the file has a
     * realistic size for its pixels. The same $seed gives the same image.
     */
    private static function png(int $side, int $seed): string
    {
        $colours = [[chr(200 - $seed * 10).chr(215).chr(230), chr(150).chr(170 + $seed * 5).chr(190)], [chr(235).chr(225 - $seed * 8).chr(200), chr(180).chr(160).chr(140 + $seed * 6)]];
        mt_srand($seed + 1);
        $rows = '';

        for ($y = 0; $y < $side; $y++) {
            if ($y % 40 === 0) {
                $noise = '';

                for ($byte = 0; $byte < $side * 3; $byte += 4) {
                    $noise .= pack('N', mt_rand());
                }

                $rows .= "\0".substr($noise, 0, $side * 3);

                continue;
            }

            [$light, $dark] = $colours[intdiv($y, 128) % 2];
            $rows .= "\0".substr(str_repeat(str_repeat($light, 128).str_repeat($dark, 128), intdiv($side, 256) + 1), 0, $side * 3);
        }

        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNC5', $side, $side, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress($rows, 6)).$chunk('IEND', '');
    }

    /**
     * A Draco mesh (bitstream 2.2, sequential encoding): the face and point counts, uncompressed indices
     * of one byte each (under 256 points), then one position attribute stored raw (generic decoder).
     * The simplest valid form of that format, so the viewer decodes it with the real Draco decoder.
     *
     * @param  list<float>  $positions
     * @param  list<int>  $indices
     */
    private static function dracoMesh(array $positions, array $indices): string
    {
        return 'DRACO'.chr(2).chr(2).chr(1).chr(0).pack('v', 0)
            .self::varint(intdiv(count($indices), 3)).self::varint(intdiv(count($positions), 3)).chr(1)
            .implode('', array_map('chr', $indices))
            .chr(1)
            .self::varint(1).chr(0).chr(9).chr(3).chr(0).self::varint(0)
            .chr(0)
            .pack('g*', ...$positions);
    }

    /**
     * An unsigned LEB128 number, as Draco writes counts.
     */
    private static function varint(int $value): string
    {
        $bytes = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;
            $bytes .= chr($value > 0 ? $byte | 0x80 : $byte);
        } while ($value > 0);

        return $bytes;
    }

    private static function aligned(string $bytes): string
    {
        return $bytes.str_repeat("\0", (4 - strlen($bytes) % 4) % 4);
    }

    /**
     * Meshopt vertex codec, version 0: the bytes of each element are delta-encoded per byte position,
     * zigzagged and stored in groups of 16, each group raw (header code 3); the tail holds the first
     * element as the baseline, padded at the front to 32 bytes.
     */
    private static function meshoptAttributes(string $data, int $stride): string
    {
        $count = intdiv(strlen($data), $stride);
        $blockSize = min(intdiv(8192, $stride) & ~15, 256);
        $encoded = "\xa0";
        $previousElement = substr($data, 0, $stride);

        for ($first = 0; $first < $count; $first += $blockSize) {
            $elements = min($blockSize, $count - $first);
            $groups = intdiv($elements + 15, 16);

            for ($byte = 0; $byte < $stride; $byte++) {
                $previous = ord($previousElement[$byte]);
                $deltas = '';

                for ($element = 0; $element < $elements; $element++) {
                    $value = ord($data[($first + $element) * $stride + $byte]);
                    $delta = ($value - $previous) & 0xFF;
                    $deltas .= chr((($delta << 1) & 0xFF) ^ ($delta & 0x80 ? 0xFF : 0x00));
                    $previous = $value;
                }

                $encoded .= str_repeat("\xFF", intdiv($groups + 3, 4)).str_pad($deltas, $groups * 16, "\0");
            }

            $previousElement = substr($data, ($first + $elements - 1) * $stride, $stride);
        }

        return $encoded.str_repeat("\0", max(0, 32 - $stride)).substr($data, 0, $stride);
    }
}
