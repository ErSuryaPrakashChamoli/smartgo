<?php

use App\Enums\Twin\AnchorType;
use App\Services\Twin\AnchorName;
use App\Services\Twin\DracoStream;
use App\Services\Twin\GlbValidator;
use App\Services\Twin\ImageDimensions;
use App\Services\Twin\TwinModelLimits;
use Tests\Support\Twin\TestGlb;

/**
 * The limits in config/twin.php, with $changes.
 *
 * @param  array<string, int|float>  $changes
 */
function twinLimits(array $changes = []): TwinModelLimits
{
    return TwinModelLimits::fromConfig([...(require dirname(__DIR__, 4).'/config/twin.php')['limits'], ...$changes]);
}

/**
 * @param  array<string, int|float>  $limits  changes to the configured limits
 * @return array{problems: list<string>, report: array<string, mixed>, anchors: list<array<string, mixed>>}
 */
function validateGlb(string $bytes, array $limits = []): array
{
    $path = TestGlb::write($bytes);

    try {
        return (new GlbValidator(new AnchorName, twinLimits($limits), new ImageDimensions, new DracoStream))->validate($path);
    } finally {
        unlink($path);
    }
}

/**
 * The test model's description with node $index replaced.
 *
 * @param  array<string, mixed>  $node
 * @return array<string, mixed>
 */
function glbWithNode(int $index, array $node): array
{
    $document = TestGlb::document();
    $document['nodes'][$index] = $node;

    return $document;
}

/**
 * The start of a PNG file: signature and header, enough for its pixel size to be read.
 */
function pngHeader(int $width, int $height): string
{
    $header = 'IHDR'.pack('NNC5', $width, $height, 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n".pack('N', 13).$header.pack('N', crc32($header));
}

/**
 * The test model with these images embedded as "data:" addresses, each used by a texture of the floor.
 *
 * @param  list<string>  $images
 */
function glbWithImages(array $images): string
{
    $document = TestGlb::document([
        'images' => array_map(fn (string $bytes): array => ['uri' => 'data:image/png;base64,'.base64_encode($bytes)], $images),
        'textures' => array_map(fn (int $image): array => ['source' => $image], array_keys($images)),
    ]);
    $document['materials'][0]['pbrMetallicRoughness']['baseColorTexture'] = ['index' => 0];

    return TestGlb::withDocument($document);
}

it('passes the test model and reports what it found and the limits applied', function () {
    $bytes = TestGlb::model();
    $result = validateGlb($bytes);

    expect($result['problems'])->toBe([])
        ->and($result['report'])->toMatchArray([
            'bytes' => strlen($bytes),
            'triangles' => 36,
            'draw_calls' => 3,
            'materials' => 3,
            'geometry_bytes' => 8 * 12 + 36 * 2,
            'textures' => ['count' => 0, 'pixels' => 0, 'largest_side' => 0, 'formats' => []],
            'size_metres' => [10.0, 1.2, 8.0],
            'compression' => ['meshopt'],
            'required_extensions' => ['EXT_meshopt_compression'],
            'nodes' => 5,
            'generator' => 'SmartGro test fixture (not a real location)',
            'budgets' => twinLimits()->toArray(),
        ])
        ->and($result['anchors'])->toBe([
            ['node_name' => 'SG_SPACE_TEST-01', 'type' => AnchorType::Space, 'reference' => 'TEST-01'],
            ['node_name' => 'SG_AMENITY_test-lounge', 'type' => AnchorType::Amenity, 'reference' => 'test-lounge'],
            ['node_name' => 'SG_CAM_1', 'type' => AnchorType::Camera, 'reference' => '1'],
            ['node_name' => 'SG_CAM_2', 'type' => AnchorType::Camera, 'reference' => '2'],
        ]);
});

it('holds files to the approved limits of 15 MB and 500,000 triangles (OD-119)', function () {
    expect(twinLimits())->bytes->toBe(15 * 1024 * 1024)->triangles->toBe(500_000);
});

it('refuses a file that is not a readable binary glTF 2.0 file, whatever its name says', function (string $bytes, string $problem) {
    expect(validateGlb($bytes)['problems'])->toBe([$problem]);
})->with([
    'an image' => [base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='), 'This is not a GLB file. Ask the 3D-modelling supplier to export the model as binary glTF (.glb).'],
    'a text file' => ['{"asset":{"version":"2.0"}}', 'This is not a GLB file. Ask the 3D-modelling supplier to export the model as binary glTF (.glb).'],
    'glTF 1' => [pack('VVV', 0x46546C67, 1, 12), 'Only glTF 2.0 files can be used; this file is glTF version 1.'],
    'a cut-off file' => [substr(TestGlb::model(), 0, 400), 'The file is incomplete or damaged. Upload it again, or ask the supplier for a new export.'],
    'a damaged description' => [pack('VVV', 0x46546C67, 2, 28).pack('VV', 8, 0x4E4F534A).'{"asset"', 'The model\'s description cannot be read: the file is damaged. Ask the supplier for a new export.'],
    'a binary chunk first' => [pack('VVV', 0x46546C67, 2, 24).pack('VV', 4, 0x004E4942)."\0\0\0\0", 'The model\'s description cannot be read: the file is damaged. Ask the supplier for a new export.'],
]);

it('refuses a file over the size limit and still runs every other check', function () {
    $document = TestGlb::document(['nodes' => [['name' => 'Floor', 'mesh' => 0, 'scale' => [10, 0.2, 8]]]]);
    $padding = str_repeat("\0", twinLimits()->bytes);

    expect(validateGlb(TestGlb::bytes($document, $padding))['problems'])->toBe([
        'The file is 15.1 MB; the limit is 15.0 MB per model. Ask the 3D-modelling supplier to compress it further or reduce the detail.',
        'No anchors were found. The supplier names nodes SG_SPACE_…, SG_AMENITY_… and SG_CAM_… so that spaces, amenities and camera positions can be linked; ask them to add the names.',
    ]);
});

it('refuses a description over its limit without decoding it', function () {
    $document = TestGlb::document(['extras' => ['padding' => str_repeat('x', 4 * 1024 * 1024)]]);

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toBe([
        'The model\'s description (the part of the file that lists its nodes, meshes and materials) is 4.1 MB; the limit is 4.0 MB. That usually means far too many separate parts: ask the supplier to merge them.',
    ]);
});

it('counts a mesh once for every node that shows it, against the triangle limit', function () {
    $document = TestGlb::document();
    $document['nodes'][1]['mesh'] = 0;
    $document['nodes'][2]['mesh'] = 0;

    expect(validateGlb(TestGlb::withIndexCount($document, 300_000 * 3)))
        ->report->triangles->toBe(900_000)
        ->problems->toBe(['The model has 900,000 triangles; the limit is 500,000. Ask the supplier to reduce the detail.']);
});

it('accepts exactly 500,000 triangles and refuses one more', function (int $triangles, array $problems) {
    $document = TestGlb::document(['nodes' => [['name' => 'SG_CAM_1', 'mesh' => 0, 'scale' => [10, 1, 8]]]]);

    expect(validateGlb(TestGlb::withIndexCount($document, $triangles * 3)))->report->triangles->toBe($triangles)->problems->toBe($problems);
})->with([
    'at the limit' => [500_000, []],
    'one over' => [500_001, ['The model has 500,001 triangles; the limit is 500,000. Ask the supplier to reduce the detail.']],
]);

it('applies limits changed in the configuration', function () {
    expect(validateGlb(TestGlb::model(), ['triangles' => 30, 'nodes' => 4, 'materials' => 2, 'draw_calls' => 2, 'geometry_bytes' => 100])['problems'])->toBe([
        'The model has 5 nodes (separate parts); the limit is 4. Ask the supplier to merge parts that do not need to be separate.',
        'The model has 3 materials; the limit is 2. Ask the supplier to merge materials that look the same.',
        'The model has 36 triangles; the limit is 30. Ask the supplier to reduce the detail.',
        'The model is drawn in 3 separate pieces (one for each part of each mesh shown); the limit is 2, because every piece slows the viewer, most of all on phones. Ask the supplier to merge meshes that share a material.',
        'Once decompressed, the model\'s geometry takes 0.1 MB; the limit is 0.1 MB. Ask the supplier to remove vertex data the model does not need (extra texture coordinates, vertex colours) or reduce the detail.',
    ]);
});

it('counts strips and fans, and ignores points and lines', function () {
    $document = TestGlb::document(['nodes' => [['name' => 'SG_CAM_1', 'mesh' => 0], ['mesh' => 1], ['mesh' => 2]]]);
    $document['meshes'][0]['primitives'][0]['mode'] = 5;
    $document['meshes'][1]['primitives'][0]['mode'] = 6;
    $document['meshes'][2]['primitives'][0]['mode'] = 1;

    expect(validateGlb(TestGlb::withDocument($document))['report'])->toMatchArray(['triangles' => 68, 'draw_calls' => 3]);
});

it('refuses uncompressed geometry', function () {
    $document = TestGlb::document();
    unset($document['bufferViews'][0]['extensions']);

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toBe(['3 of the model\'s 3 surface parts are not compressed. Ask the supplier to export with Draco or Meshopt compression.']);
});

it('accepts Draco and counts the triangles its streams hold', function () {
    expect(validateGlb(TestGlb::dracoModel()))
        ->problems->toBe([])
        ->report->toMatchArray(['triangles' => 36, 'compression' => ['draco'], 'required_extensions' => ['KHR_draco_mesh_compression']]);
});

it('holds Draco geometry to what its stream holds, not to what the description declares', function () {
    $bytes = TestGlb::dracoModel();
    $document = json_decode(substr($bytes, 20, unpack('V', $bytes, 12)[1]), true);
    $binary = substr($bytes, 28 + unpack('V', $bytes, 12)[1]);
    $document['accessors'][1]['count'] = 3;

    expect(validateGlb(TestGlb::bytes($document, $binary)))
        ->report->triangles->toBe(36)
        ->problems->toBe(['The compressed geometry does not match the file\'s description (mesh “Floor” declares 1 triangle but holds 12, mesh “Test space” declares 1 triangle but holds 12, mesh “Test lounge” declares 1 triangle but holds 12). The file is damaged; ask the supplier for a new export.']);

    $document['accessors'][1]['count'] = 36;
    $document['bufferViews'][0]['byteLength'] = 9;

    expect(validateGlb(TestGlb::bytes($document, $binary))['problems'])
        ->toContain('The compressed geometry of mesh “Floor”, mesh “Test space”, mesh “Test lounge” cannot be read. Ask the supplier for a new export with a current Draco encoder.');
});

it('refuses references to outside files but accepts embedded data', function () {
    $document = TestGlb::document(['images' => [['uri' => 'textures/wall.png'], ['uri' => 'data:image/png;base64,'.base64_encode(pngHeader(4, 4))]]]);
    $document['buffers'][1]['uri'] = 'https://cdn.example.com/model.bin';

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toBe(['The model refers to outside files (“https://cdn.example.com/model.bin”, “textures/wall.png”). Ask the supplier to export one self-contained .glb with everything embedded.']);
});

it('reads each texture\'s format and size from its own bytes', function () {
    expect(validateGlb(glbWithImages([pngHeader(2048, 1024), pngHeader(512, 512)])))
        ->problems->toBe([])
        ->report->textures->toBe(['count' => 2, 'pixels' => 2048 * 1024 + 512 * 512, 'largest_side' => 2048, 'formats' => ['png']]);

    expect(validateGlb(TestGlb::panelsModel(2, 1, 64, 'two panels'))['report'])->toMatchArray([
        'triangles' => 4000, 'draw_calls' => 2, 'materials' => 10,
        'textures' => ['count' => 1, 'pixels' => 64 * 64, 'largest_side' => 64, 'formats' => ['png']],
    ]);
});

it('refuses textures over the side, pixel and count limits, and images it cannot read', function (array $images, string $problem) {
    expect(validateGlb(glbWithImages($images))['problems'])->toBe([$problem]);
})->with([
    'a side over 4,096 pixels' => [[pngHeader(4097, 16), pngHeader(16, 8192)], 'These textures are larger than 4,096 pixels on a side: “image 0, 4097 × 16”, “image 1, 16 × 8192”. Ask the supplier to reduce them to at most 4,096 × 4,096 pixels.'],
    'more pixels than allowed in all' => [array_fill(0, 3, pngHeader(4096, 4096)), 'The textures add up to 50,331,648 pixels; the limit is 33,554,432 pixels in all. Ask the supplier to make the textures smaller or use fewer of them.'],
    'more textures than allowed' => [array_fill(0, 65, pngHeader(8, 8)), 'The model has 65 textures; the limit is 64. Ask the supplier to combine textures (a texture atlas) or reuse them across materials.'],
    'a GIF declared as a PNG' => [['GIF89a'.pack('vv', 16, 16)], 'These textures cannot be read, or are not PNG, JPEG, WebP or KTX2 images: “image 0”. Ask the supplier to export the textures in one of those formats.'],
]);

it('refuses a model measured in the wrong unit', function (array $scale, string $longest) {
    $document = TestGlb::document(['nodes' => [['name' => 'SG_CAM_1', 'mesh' => 0, 'scale' => $scale]]]);

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toBe([
        "The model measures {$longest} m along its longest side; it must be between 1 m and 1,000 m. It was probably exported in the wrong unit: glTF uses metres, so ask the supplier to export in metres.",
    ]);
})->with([
    'millimetres' => [[10_000, 3_000, 8_000], '10,000'],
    'kilometres' => [[0.01, 0.003, 0.008], '0.01'],
]);

it('measures the model through its node transforms and quantised positions', function () {
    $document = TestGlb::document([
        'nodes' => [['name' => 'Building', 'children' => [1], 'translation' => [100, 0, 0], 'rotation' => [0, M_SQRT1_2, 0, M_SQRT1_2]], ['name' => 'SG_CAM_1', 'mesh' => 0, 'scale' => [10, 0.2, 8]]],
        'scenes' => [['nodes' => [0]]],
    ]);
    $document['accessors'][0] = [...$document['accessors'][0], 'componentType' => 5122, 'normalized' => true, 'min' => [-16384, -16384, -16384], 'max' => [16384, 16384, 16384]];

    expect(validateGlb(TestGlb::withDocument($document)))
        ->problems->toBe([])
        ->report->size_metres->toBe([8.0, 0.2, 10.0]);
});

it('refuses a damaged structure with the parts that are wrong', function (Closure $damage, string $problem) {
    $document = TestGlb::document();
    $damage($document);

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toContain($problem);
})->with([
    'an accessor past its data' => [function (array &$document) {
        $document['accessors'][0]['count'] = 9;
    }, 'The file is damaged or was exported incorrectly: accessor 0 reaches past the end of its data. Ask the supplier for a new export.'],
    'a buffer view past its buffer' => [function (array &$document) {
        $document['bufferViews'][1]['byteLength'] = 4096;
    }, 'The file is damaged or was exported incorrectly: buffer view 1 reaches past the end of its buffer, accessor 1 reaches past the end of its data. Ask the supplier for a new export.'],
    'a missing accessor' => [function (array &$document) {
        $document['meshes'][2]['primitives'][0]['indices'] = 7;
    }, 'The file is damaged or was exported incorrectly: mesh “Test lounge” refers to data that is not in the file. Ask the supplier for a new export.'],
    'positions without bounds' => [function (array &$document) {
        unset($document['accessors'][0]['min']);
    }, 'The file is damaged or was exported incorrectly: the positions of mesh “Floor” do not state their bounds (min and max), the positions of mesh “Test space” do not state their bounds (min and max), the positions of mesh “Test lounge” do not state their bounds (min and max). Ask the supplier for a new export.'],
    'a node with two parents' => [function (array &$document) {
        $document['nodes'][0]['children'] = [3];
        $document['nodes'][1]['children'] = [3];
        $document['scenes'][0]['nodes'] = [0, 1, 2, 4];
    }, 'The file is damaged or was exported incorrectly: node 3 has more than one parent. Ask the supplier for a new export.'],
    'a loop of nodes' => [function (array &$document) {
        $document['nodes'][3]['children'] = [4];
        $document['nodes'][4]['children'] = [3];
        $document['scenes'][0]['nodes'] = [0, 1, 2];
    }, 'The file is damaged or was exported incorrectly: some nodes are their own ancestors. Ask the supplier for a new export.'],
    'a texture without its image' => [function (array &$document) {
        $document['textures'] = [['source' => 0]];
    }, 'The file is damaged or was exported incorrectly: texture 0 refers to an image that is not in the file. Ask the supplier for a new export.'],
    'no scene' => [function (array &$document) {
        $document['scene'] = 3;
    }, 'The file has no scene to show. Ask the supplier to export the model with its scene.'],
]);

it('refuses a file whose binary chunk is missing', function () {
    expect(validateGlb(TestGlb::bytes(TestGlb::document()))['problems'])
        ->toBe(['The file is damaged or was exported incorrectly: buffer 0 holds less data than it declares, the compressed data of buffer view 0 is not described properly, buffer view 1 reaches past the end of its buffer, accessor 0 reaches past the end of its data, accessor 1 reaches past the end of its data. Ask the supplier for a new export.']);
});

it('stops when the checks take longer than allowed', function () {
    expect(validateGlb(TestGlb::model(), ['check_seconds' => 0]))
        ->problems->toBe(['Checking the model took longer than 0 seconds, so it was stopped. The file is probably far too complex: ask the supplier to simplify it, with fewer parts and textures.'])
        ->anchors->toBe([]);
});

it('refuses features the viewer cannot show', function () {
    $document = TestGlb::document(['extensionsRequired' => ['EXT_meshopt_compression', 'EXT_mesh_gpu_instancing', 'KHR_texture_basisu']]);

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toBe(['The model needs features the viewer cannot show (“EXT_mesh_gpu_instancing”). Ask the supplier to export without them.']);
});

it('refuses a file that does not say it is glTF 2.0', function () {
    expect(validateGlb(TestGlb::withDocument(TestGlb::document(['asset' => ['version' => '1.0']])))['problems'])
        ->toBe(['The file does not say it is glTF 2.0. Ask the supplier for a glTF 2.0 export.']);
});

it('refuses anchor names that break the convention or repeat', function () {
    $document = glbWithNode(3, ['name' => 'sg_cam_1']);
    $document['nodes'][4]['name'] = 'SG_SPACE_TEST-01';

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toBe([
        'These names start like anchors but do not follow the naming convention: “sg_cam_1”. Use SG_SPACE_ followed by the space code in capitals, digits and hyphens; SG_AMENITY_ followed by a lowercase name with hyphens; or SG_CAM_ followed by a number from 1.',
        'Each anchor name must be used once, but these appear more than once: “SG_SPACE_TEST-01”.',
    ]);
});

it('lists at most five names in a message', function () {
    $nodes = array_map(fn (int $number): array => ['name' => "SG_CAM_0{$number}"], range(1, 7));

    expect(validateGlb(TestGlb::withDocument(TestGlb::document(['nodes' => [['name' => 'SG_CAM_1', 'mesh' => 0, 'scale' => [2, 2, 2]], ...$nodes]])))['problems'][0])
        ->toStartWith('These names start like anchors but do not follow the naming convention: “SG_CAM_01”, “SG_CAM_02”, “SG_CAM_03”, “SG_CAM_04”, “SG_CAM_05” and 2 more.');
});

it('reports every problem of a file at once', function () {
    $document = TestGlb::document([
        'nodes' => [['name' => 'Floor', 'mesh' => 0, 'scale' => [10, 0.2, 8]]],
        'images' => [['uri' => 'wall.png']],
        'extensionsRequired' => ['KHR_materials_variants'],
    ]);
    unset($document['bufferViews'][0]['extensions']);

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toHaveCount(4);
});

it('refuses a file with nothing to show', function () {
    expect(validateGlb(TestGlb::withDocument(TestGlb::document(['nodes' => [['name' => 'SG_CAM_1']]])))['problems'])
        ->toBe(['The file contains no 3D surfaces to show.']);
});

it('reports malformed description entries as damage instead of failing', function () {
    $document = TestGlb::document([
        'nodes' => [['name' => 'SG_CAM_1', 'mesh' => 'zero'], 'not a node', ['name' => ['SG_CAM_2']]],
        'meshes' => [['primitives' => 'none'], ['primitives' => [['attributes' => 'POSITION', 'indices' => [1]]]]],
        'accessors' => 'none',
        'buffers' => [['uri' => ['x']]],
        'extensionsRequired' => 'EXT_meshopt_compression',
    ]);

    expect(validateGlb(TestGlb::withDocument($document))['problems'])->toBe([
        'The file is damaged or was exported incorrectly: buffer 0 has no size, buffer view 0 reaches past the end of its buffer, buffer view 1 reaches past the end of its buffer, mesh 1 refers to data that is not in the file, node 0 refers to a mesh that is not in the file and 1 more. Ask the supplier for a new export.',
        'The file contains no 3D surfaces to show.',
    ]);
});
