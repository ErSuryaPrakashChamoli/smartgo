<?php

use App\Services\Twin\ImageDimensions;

/**
 * A black image of this size, as GD encodes it.
 */
function encodedImage(string $format, int $width, int $height, int $quality = 80, bool $progressive = false): string
{
    $image = imagecreatetruecolor($width, $height);
    imageinterlace($image, $progressive);
    ob_start();

    match ($format) {
        'png' => imagepng($image),
        'jpeg' => imagejpeg($image),
        'webp' => imagewebp($image, null, $quality),
    };

    return (string) ob_get_clean();
}

it('reads the format and size of images as encoders write them', function (string $bytes, string $format) {
    expect((new ImageDimensions)->read($bytes))->toBe(['format' => $format, 'width' => 300, 'height' => 120]);
})->with([
    'PNG' => [fn (): string => encodedImage('png', 300, 120), 'png'],
    'JPEG' => [fn (): string => encodedImage('jpeg', 300, 120), 'jpeg'],
    'progressive JPEG' => [fn (): string => encodedImage('jpeg', 300, 120, progressive: true), 'jpeg'],
    'JPEG with a large comment and fill bytes before the frame' => [fn (): string => substr_replace(encodedImage('jpeg', 300, 120), "\xFF\xFE".pack('n', 60_002).str_repeat('x', 60_000)."\xFF", 2, 0), 'jpeg'],
    'lossy WebP' => [fn (): string => encodedImage('webp', 300, 120), 'webp'],
    'lossless WebP' => [fn (): string => encodedImage('webp', 300, 120, IMG_WEBP_LOSSLESS), 'webp'],
    'extended WebP' => ['RIFF'.pack('V', 22).'WEBPVP8X'.pack('V', 10)."\0\0\0\0".substr(pack('V', 299), 0, 3).substr(pack('V', 119), 0, 3), 'webp'],
    'KTX2' => ["\xABKTX 20\xBB\r\n\x1A\n".pack('V7', 0, 1, 300, 120, 0, 0, 1), 'ktx2'],
]);

it('reads nothing from other formats or damaged headers', function (string $bytes) {
    expect((new ImageDimensions)->read($bytes))->toBeNull();
})->with([
    'GIF' => ['GIF89a'.pack('vv', 16, 16)],
    'random bytes' => [str_repeat("\x42", 64)],
    'a cut-off PNG' => [fn (): string => substr(encodedImage('png', 8, 8), 0, 20)],
    'a PNG without a width' => ["\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NNC5', 0, 8, 8, 2, 0, 0, 0)],
    'a JPEG without a frame' => ["\xFF\xD8\xFF\xD9"],
    'a JPEG with a broken segment' => ["\xFF\xD8\xFF\xE0\x00\x01xxxxxxxx"],
    'a cut-off WebP' => ['RIFF'.pack('V', 4).'WEBPVP8 '],
    'a KTX2 without a height' => ["\xABKTX 20\xBB\r\n\x1A\n".pack('V7', 0, 1, 300, 0, 0, 0, 1)],
]);
