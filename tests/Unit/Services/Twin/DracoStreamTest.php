<?php

use App\Services\Twin\DracoStream;
use Tests\Support\Twin\TestGlb;

/*
 * The test model's Draco streams are decoded by the real Draco decoder in the browser checks
 * (docs/operations/scripts/browser). The Edgebreaker and metadata layouts here follow the Draco
 * decoder's source (MeshEdgebreakerDecoderImpl, MetadataDecoder); no Edgebreaker file from an encoder
 * was available to check them against.
 */

function dracoVarint(int $value): string
{
    $bytes = '';

    do {
        $byte = $value & 0x7F;
        $value >>= 7;
        $bytes .= chr($value > 0 ? $byte | 0x80 : $byte);
    } while ($value > 0);

    return $bytes;
}

function dracoHeader(int $major, int $minor, int $method, int $flags = 0, int $type = 1): string
{
    return 'DRACO'.chr($major).chr($minor).chr($type).chr($method).pack('v', $flags);
}

it('reads the triangles of the test model\'s Draco stream', function () {
    $bytes = TestGlb::dracoModel();
    $description = json_decode(substr($bytes, 20, unpack('V', $bytes, 12)[1]), true);
    $stream = substr($bytes, 28 + unpack('V', $bytes, 12)[1], $description['bufferViews'][0]['byteLength']);

    expect((new DracoStream)->inspect($stream))->toBe(['version' => '2.2', 'faces' => 12]);
});

it('reads the triangle count of every layout it supports', function (string $bytes, string $version, int $faces) {
    expect((new DracoStream)->inspect($bytes))->toBe(['version' => $version, 'faces' => $faces]);
})->with([
    'sequential, 2.2' => [dracoHeader(2, 2, 0).dracoVarint(300_000).dracoVarint(200_000).chr(1), '2.2', 300_000],
    'sequential, 2.1 (fixed-size counts)' => [dracoHeader(2, 1, 0).pack('VV', 70_000, 5).chr(1), '2.1', 70_000],
    'Edgebreaker, 2.2' => [dracoHeader(2, 2, 1).chr(0).dracoVarint(5_000).dracoVarint(9_000).chr(0), '2.2', 9_000],
    'Edgebreaker, 2.1 (with new vertices)' => [dracoHeader(2, 1, 1).chr(2).dracoVarint(10).dracoVarint(5_000).dracoVarint(9_000), '2.1', 9_000],
    'after metadata' => [
        dracoHeader(2, 2, 0, flags: 0x8000)
            .dracoVarint(1).dracoVarint(0)
            .dracoVarint(1).chr(4).'name'.dracoVarint(3).'abc'.dracoVarint(1).chr(3).'sub'.dracoVarint(0).dracoVarint(0)
            .dracoVarint(0).dracoVarint(0)
            .dracoVarint(42).dracoVarint(30).chr(1),
        '2.2', 42,
    ],
]);

it('reads nothing it cannot rely on', function (string $bytes) {
    expect((new DracoStream)->inspect($bytes))->toBeNull();
})->with([
    'not Draco' => ['glTF'.str_repeat("\0", 20)],
    'a point cloud' => [dracoHeader(2, 2, 0, type: 0).dracoVarint(10)],
    'version 1.3' => [dracoHeader(1, 3, 0).pack('VV', 10, 5)],
    'version 2.3' => [dracoHeader(2, 3, 0).dracoVarint(10).dracoVarint(5)],
    'an unknown method' => [dracoHeader(2, 2, 2).dracoVarint(10)],
    'counts cut off' => [dracoHeader(2, 2, 0)],
    'a count over 32 bits' => [dracoHeader(2, 2, 0)."\xFF\xFF\xFF\xFF\x7F"],
    'more faces than Draco allows' => [dracoHeader(2, 2, 0).dracoVarint(0x55555556).dracoVarint(5)],
    'metadata running past the end' => [dracoHeader(2, 2, 0, flags: 0x8000).dracoVarint(0).dracoVarint(1).chr(4).'name'.dracoVarint(500).'abc'],
]);
