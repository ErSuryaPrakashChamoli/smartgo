<?php

use App\Actions\Brand\GenerateBrandRenditions;
use App\Services\Design\DesignTokens;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->source = resource_path('brand/source/smartgro-logo.webp');
    $this->output = storage_path('framework/testing/brand-renditions');
    File::deleteDirectory($this->output);
});

afterEach(function () {
    File::deleteDirectory($this->output);
});

/**
 * @return array{red: int, green: int, blue: int, alpha: int}
 */
function brandPixel(GdImage $image, int $x, int $y): array
{
    $pixel = imagecolorat($image, $x, $y);

    return ['red' => ($pixel >> 16) & 0xFF, 'green' => ($pixel >> 8) & 0xFF, 'blue' => $pixel & 0xFF, 'alpha' => ($pixel >> 24) & 0x7F];
}

it('writes the logo, reversed logo, symbol, home-screen icon and tab icon', function () {
    $files = app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    expect($files)->toBe([
        'brand/smartgro-logo.webp',
        'brand/smartgro-logo-reversed.webp',
        'brand/smartgro-mark.png',
        'brand/apple-touch-icon.png',
        'favicon.ico',
    ])
        ->and(hash_file('sha256', "{$this->output}/brand/smartgro-logo.webp"))->toBe(hash_file('sha256', $this->source));
});

it('crops the SG symbol without changing its colours', function () {
    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    $logo = imagecreatefromwebp($this->source);
    $mark = imagecreatefrompng("{$this->output}/brand/smartgro-mark.png");

    // The symbol occupies x 82–303 and y 2–144 of the logo and is centred vertically on a 222 px square.
    expect([imagesx($mark), imagesy($mark)])->toBe([222, 222]);

    foreach ([[85, 30], [218, 60], [208, 60], [230, 6]] as [$x, $y]) {
        expect(brandPixel($mark, $x - 82, $y - 2 + 39))->toBe(brandPixel($logo, $x, $y));
    }
});

it('builds a tab icon with 16, 32 and 48 pixel images', function () {
    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    $icon = file_get_contents("{$this->output}/favicon.ico");
    $header = unpack('vreserved/vtype/vcount', $icon);
    $sizes = array_map(fn (int $index): int => unpack('Cwidth', $icon, 6 + 16 * $index)['width'], range(0, $header['count'] - 1));

    expect($header['type'])->toBe(1)
        ->and($sizes)->toBe([16, 32, 48]);
});

it('turns only the dark lettering, the buildings and the tagline lettering white in the reversed logo', function (int $x, int $y) {
    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    $pixel = brandPixel(imagecreatefromwebp("{$this->output}/brand/smartgro-logo-reversed.webp"), $x, $y);

    expect($pixel['red'])->toBeGreaterThanOrEqual(240)
        ->and($pixel['green'])->toBeGreaterThanOrEqual(240)
        ->and($pixel['blue'])->toBeGreaterThanOrEqual(235)
        ->and($pixel['alpha'])->toBe(0);
})->with([
    'wordmark "SMART"' => [20, 156],
    'upper arc of the G' => [230, 6],
    'buildings' => [218, 60],
    'tagline lettering' => [189, 208],
]);

it('keeps green elements, building faces and the tagline side lines exactly as supplied in the reversed logo', function (int $x, int $y) {
    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    expect(brandPixel(imagecreatefromwebp("{$this->output}/brand/smartgro-logo-reversed.webp"), $x, $y))
        ->toBe(brandPixel(imagecreatefromwebp($this->source), $x, $y));
})->with([
    'green S' => [85, 30],
    'green "GRO"' => [270, 155],
    'building face' => [208, 60],
    'left tagline line' => [20, 211],
    'right tagline line' => [368, 211],
]);

it('keeps the size and the transparency of every pixel of the logo, so shapes and alignment are unchanged', function () {
    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    $original = imagecreatefromwebp($this->source);
    $reversed = imagecreatefromwebp("{$this->output}/brand/smartgro-logo-reversed.webp");
    $differences = 0;

    for ($y = 0; $y < imagesy($original); $y++) {
        for ($x = 0; $x < imagesx($original); $x++) {
            $differences += (int) (brandPixel($original, $x, $y)['alpha'] !== brandPixel($reversed, $x, $y)['alpha']);
        }
    }

    expect([imagesx($reversed), imagesy($reversed)])->toBe([imagesx($original), imagesy($original)])
        ->and($differences)->toBe(0);
});

it('makes the tagline lettering readable on the forest background', function () {
    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    $lettering = brandPixel(imagecreatefromwebp("{$this->output}/brand/smartgro-logo-reversed.webp"), 189, 208);
    $tokens = app(DesignTokens::class);

    expect($tokens->contrastRatio(sprintf('#%02x%02x%02x', $lettering['red'], $lettering['green'], $lettering['blue']), $tokens->colors()['forest']))
        ->toBeGreaterThanOrEqual(4.5);
});

it('never modifies the original logo file', function () {
    $before = hash_file('sha256', $this->source);

    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    expect(hash_file('sha256', $this->source))->toBe($before);
});

it('keeps the background of the reversed logo transparent', function () {
    app(GenerateBrandRenditions::class)->handle($this->source, $this->output);

    expect(brandPixel(imagecreatefromwebp("{$this->output}/brand/smartgro-logo-reversed.webp"), 0, 0)['alpha'])->toBe(127);
});
