<?php

namespace App\Actions\Brand;

use GdImage;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Generates the web renditions of the official logo (FR-BRAND-1) without redrawing it:
 * the browser-tab icon is the "SG" symbol cropped and scaled, unchanged in shape and colour
 * (OD-44), and the reversed logo recolours only the dark ink of the symbol and wordmark to
 * Warm White, keeping every green element and the tagline row as supplied (OD-45).
 */
class GenerateBrandRenditions
{
    /**
     * The dark ink of the supplied logo (wordmark "SMART", upper arc of the G, buildings, base arc).
     *
     * @var array{int, int, int}
     */
    private const INK = [15, 52, 30];

    /**
     * @var array{int, int, int}
     */
    private const WARM_WHITE = [247, 247, 242];

    /**
     * Every other colour of the logo: building faces, then the greens from dark to light.
     *
     * @var list<array{int, int, int}>
     */
    private const OTHER_COLOURS = [
        [248, 253, 249],
        [33, 138, 40],
        [67, 149, 41],
        [72, 160, 35],
        [94, 163, 46],
        [106, 174, 38],
        [132, 189, 83],
        [150, 200, 101],
    ];

    /**
     * Below the floor a pixel counts as fully non-ink, above the ceiling as fully ink.
     */
    private const INK_SHARE_FLOOR = 0.3;

    private const INK_SHARE_CEILING = 0.7;

    private const FAVICON_SIZES = [16, 32, 48];

    private const APPLE_TOUCH_ICON_SIZE = 180;

    /**
     * Rows that are at least this many pixels apart separate the symbol, wordmark and tagline.
     */
    private const BAND_GAP = 3;

    /**
     * @return list<string> paths of the files written, relative to the output directory
     */
    public function handle(string $sourcePath, string $outputDirectory): array
    {
        $logo = imagecreatefromwebp($sourcePath);

        if ($logo === false) {
            throw new RuntimeException("The logo at {$sourcePath} could not be read as WebP.");
        }

        $bands = $this->contentBands($logo);

        if (count($bands) < 2) {
            throw new RuntimeException('Expected the symbol and wordmark as separate rows of the logo.');
        }

        File::ensureDirectoryExists("{$outputDirectory}/brand");

        File::copy($sourcePath, "{$outputDirectory}/brand/smartgro-logo.webp");
        imagewebp($this->reversed($logo, [$bands[0], $bands[1]]), "{$outputDirectory}/brand/smartgro-logo-reversed.webp", IMG_WEBP_LOSSLESS);

        $mark = $this->squareMark($logo, $bands[0]);
        imagepng($mark, "{$outputDirectory}/brand/smartgro-mark.png");
        imagepng($this->resized($mark, self::APPLE_TOUCH_ICON_SIZE, background: self::WARM_WHITE, margin: 18), "{$outputDirectory}/brand/apple-touch-icon.png");
        file_put_contents("{$outputDirectory}/favicon.ico", $this->icon($mark));

        return [
            'brand/smartgro-logo.webp',
            'brand/smartgro-logo-reversed.webp',
            'brand/smartgro-mark.png',
            'brand/apple-touch-icon.png',
            'favicon.ico',
        ];
    }

    /**
     * Rows of the image that contain visible pixels, grouped into bands separated by empty rows.
     *
     * @return list<array{top: int, bottom: int}>
     */
    private function contentBands(GdImage $image): array
    {
        $bands = [];
        $emptyRows = PHP_INT_MAX;

        for ($y = 0; $y < imagesy($image); $y++) {
            if (! $this->rowHasContent($image, $y)) {
                $emptyRows++;

                continue;
            }

            if ($emptyRows >= self::BAND_GAP || $bands === []) {
                $bands[] = ['top' => $y, 'bottom' => $y];
            } else {
                $bands[array_key_last($bands)]['bottom'] = $y;
            }

            $emptyRows = 0;
        }

        return $bands;
    }

    private function rowHasContent(GdImage $image, int $y): bool
    {
        for ($x = 0; $x < imagesx($image); $x++) {
            if ($this->alpha(imagecolorat($image, $x, $y)) < 120) {
                return true;
            }
        }

        return false;
    }

    /**
     * Each pixel is treated as a blend of the ink and its nearest other logo colour; only the ink
     * share is swapped for Warm White, so anti-aliased edges and transparency are preserved.
     *
     * @param  list<array{top: int, bottom: int}>  $bands
     */
    private function reversed(GdImage $logo, array $bands): GdImage
    {
        $reversed = $this->transparentCanvas(imagesx($logo), imagesy($logo));

        for ($y = 0; $y < imagesy($logo); $y++) {
            $recolour = collect($bands)->contains(fn (array $band): bool => $y >= $band['top'] && $y <= $band['bottom']);

            for ($x = 0; $x < imagesx($logo); $x++) {
                $pixel = imagecolorat($logo, $x, $y);
                [$red, $green, $blue] = [($pixel >> 16) & 0xFF, ($pixel >> 8) & 0xFF, $pixel & 0xFF];

                if ($recolour) {
                    $inkShare = $this->inkShare([$red, $green, $blue]);
                    $red = $this->channel($red + $inkShare * (self::WARM_WHITE[0] - self::INK[0]));
                    $green = $this->channel($green + $inkShare * (self::WARM_WHITE[1] - self::INK[1]));
                    $blue = $this->channel($blue + $inkShare * (self::WARM_WHITE[2] - self::INK[2]));
                }

                imagesetpixel($reversed, $x, $y, imagecolorallocatealpha($reversed, $red, $green, $blue, $this->alpha($pixel)));
            }
        }

        return $reversed;
    }

    /**
     * How much of a pixel is ink, from 0 (any green or face colour, left exactly as supplied)
     * to 1 (solid ink). The smoothstep keeps greens that are not an exact reference colour
     * untouched while blending anti-aliased ink edges.
     *
     * @param  array{int, int, int}  $colour
     */
    private function inkShare(array $colour): float
    {
        $toInk = $this->distance($colour, self::INK);
        $toOther = min(array_map(fn (array $other): float => $this->distance($colour, $other), self::OTHER_COLOURS));

        if ($toInk + $toOther === 0.0) {
            return 0.0;
        }

        $share = min(1.0, max(0.0, ($toOther / ($toInk + $toOther) - self::INK_SHARE_FLOOR) / (self::INK_SHARE_CEILING - self::INK_SHARE_FLOOR)));

        return $share * $share * (3 - 2 * $share);
    }

    /**
     * @param  array{top: int, bottom: int}  $band
     */
    private function squareMark(GdImage $logo, array $band): GdImage
    {
        $left = imagesx($logo);
        $right = 0;

        for ($y = $band['top']; $y <= $band['bottom']; $y++) {
            for ($x = 0; $x < imagesx($logo); $x++) {
                if ($this->alpha(imagecolorat($logo, $x, $y)) < 120) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                }
            }
        }

        $width = $right - $left + 1;
        $height = $band['bottom'] - $band['top'] + 1;
        $side = max($width, $height);

        $mark = $this->transparentCanvas($side, $side);
        imagecopy($mark, $logo, intdiv($side - $width, 2), intdiv($side - $height, 2), $left, $band['top'], $width, $height);

        return $mark;
    }

    /**
     * Scales the image onto a square canvas; the margin and background add space around the
     * symbol (iOS rounds icon corners) without changing the symbol itself.
     *
     * @param  array{int, int, int}|null  $background
     */
    private function resized(GdImage $image, int $size, ?array $background = null, int $margin = 0): GdImage
    {
        $canvas = $this->transparentCanvas($size, $size);

        if ($background !== null) {
            imagefilledrectangle($canvas, 0, 0, $size - 1, $size - 1, imagecolorallocate($canvas, ...$background));
            imagealphablending($canvas, true);
        }

        imagecopyresampled($canvas, $image, $margin, $margin, 0, 0, $size - 2 * $margin, $size - 2 * $margin, imagesx($image), imagesy($image));

        return $canvas;
    }

    /**
     * A multi-size .ico with PNG-encoded images, which every current browser supports.
     */
    private function icon(GdImage $mark): string
    {
        $images = array_map(function (int $size) use ($mark): string {
            ob_start();
            imagepng($this->resized($mark, $size));

            return (string) ob_get_clean();
        }, self::FAVICON_SIZES);

        $header = pack('vvv', 0, 1, count($images));
        $directory = '';
        $offset = 6 + 16 * count($images);

        foreach (self::FAVICON_SIZES as $index => $size) {
            $directory .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($images[$index]), $offset);
            $offset += strlen($images[$index]);
        }

        return $header.$directory.implode('', $images);
    }

    private function transparentCanvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    /**
     * GD alpha runs from 0 (opaque) to 127 (transparent).
     */
    private function alpha(int $pixel): int
    {
        return ($pixel >> 24) & 0x7F;
    }

    /**
     * @param  array{int, int, int}  $first
     * @param  array{int, int, int}  $second
     */
    private function distance(array $first, array $second): float
    {
        return sqrt(($first[0] - $second[0]) ** 2 + ($first[1] - $second[1]) ** 2 + ($first[2] - $second[2]) ** 2);
    }

    private function channel(float $value): int
    {
        return (int) max(0, min(255, round($value)));
    }
}
