<?php

namespace Database\Seeders;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Two invented office floors for the demo of a location's 3D walkthrough and floor plans (OD-181): an open plan
 * with private cabins, and a meeting and training floor. Neither is a SmartGro layout. Each floor is described
 * once (zones, walls, furniture, guided views) and drawn twice from that description, so the 3D model and the
 * floor plan always agree:
 *
 * - `glb()`: a GLB file (glTF 2.0, uncompressed, no textures) of the floor as seen from above with the front walls
 *   cut low, with the anchor names the upload checks require (SG_CAM_1…5 for the guided views, SG_SPACE_… and
 *   SG_AMENITY_…, ADR-0009). It is uploaded as a conceptual model, so the website always shows "Conceptual
 *   illustration — not a scan of this location" with it (BR-3D-2).
 * - `floorPlan()`: the same floor as a 2D plan (PNG), titled "Example floor plan — not SmartGro's layout". It
 *   needs a TrueType font, so the demo seeder uses the copies made with it, kept in `media/demo/floor-plans/`;
 *   draw them again with `php database/seeders/media/demo/floor-plans/draw.php`.
 *
 * Coordinates are in metres: x runs left to right along the front, z from the back wall (0) to the front (18),
 * and y is up (glTF's axes). The floor is 30 × 18 m.
 */
final class ExampleOfficeModel
{
    public const string OPEN_PLAN = 'open-plan';

    public const string MEETING_FLOOR = 'meeting-floor';

    public const array FLOORS = [self::OPEN_PLAN, self::MEETING_FLOOR];

    private const float WIDTH = 30.0;

    private const float DEPTH = 18.0;

    private const float WALL_HEIGHT = 2.7;

    private const float LOW_WALL_HEIGHT = 1.0;

    private const float WALL_THICKNESS = 0.2;

    private const float GLASS_THICKNESS = 0.06;

    /**
     * Material name => colour (sRGB hex) and opacity. Kept in this order in the file.
     *
     * @var array<string, array{0: string, 1: float}>
     */
    private const array MATERIALS = [
        'floor' => ['ece8e1', 1.0],
        'carpet' => ['d3dfd0', 1.0],
        'carpet_warm' => ['e6dccb', 1.0],
        'wall' => ['f7f5f0', 1.0],
        'wood' => ['c49a6c', 1.0],
        'dark' => ['33403a', 1.0],
        'seat' => ['1f4d3a', 1.0],
        'accent' => ['7db046', 1.0],
        'plant' => ['3f8a3f', 1.0],
        'white' => ['fbfaf6', 1.0],
        'glass' => ['9cc9d6', 0.3],
    ];

    /**
     * Zone fills on the floor plan, by floor material.
     *
     * @var array<string, string>
     */
    private const array PLAN_FILLS = ['floor' => 'f4f1ec', 'carpet' => 'e3ece1', 'carpet_warm' => 'f1eadf'];

    /**
     * @return array{title: string, summary: string, zones: list<array{name: string, detail: string|null, area: array{0: float, 1: float, 2: float, 3: float}, material: string, label?: array{0: float, 1: float}}>, walls: list<array{0: float, 1: float, 2: float, 3: float, 4: string}>, furniture: list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>, views: list<array{title: string, from: array{0: float, 1: float, 2: float}, to: array{0: float, 1: float, 2: float}}>, anchors: array<string, array{0: float, 1: float, 2: float}>}
     */
    public static function floor(string $key): array
    {
        return match ($key) {
            self::OPEN_PLAN => self::openPlan(),
            self::MEETING_FLOOR => self::meetingFloor(),
            default => throw new InvalidArgumentException("Unknown example floor {$key}."),
        };
    }

    /**
     * The floor as a GLB file.
     */
    public static function glb(string $key): string
    {
        $floor = self::floor($key);
        $boxes = [['floor', 0, -0.1, 0, self::WIDTH, 0, self::DEPTH]];

        foreach ($floor['zones'] as $zone) {
            if ($zone['material'] !== 'floor') {
                [$x0, $z0, $x1, $z1] = $zone['area'];
                $boxes[] = [$zone['material'], $x0, 0, $z0, $x1, 0.01, $z1];
            }
        }

        foreach ($floor['walls'] as [$x0, $z0, $x1, $z1, $kind]) {
            $half = ($kind === 'glass' ? self::GLASS_THICKNESS : self::WALL_THICKNESS) / 2;
            $height = $kind === 'low' ? self::LOW_WALL_HEIGHT : self::WALL_HEIGHT;
            $boxes[] = [$kind === 'glass' ? 'glass' : 'wall', min($x0, $x1) - $half, 0, min($z0, $z1) - $half, max($x0, $x1) + $half, $height, max($z0, $z1) + $half];
        }

        $boxes = [...$boxes, ...$floor['furniture']];

        // Buffer 0 is the file's binary chunk; buffer 1 is where the viewer decompresses vertex data to (Meshopt's
        // fallback buffer, which the file does not hold).
        $binary = '';
        $fallbackBytes = 0;
        $views = [];
        $accessors = [];
        $meshes = [];
        $nodes = [];
        $materials = [];

        foreach (self::MATERIALS as $name => [$hex, $opacity]) {
            $own = array_values(array_filter($boxes, fn (array $box): bool => $box[0] === $name));

            if ($own === []) {
                continue;
            }

            [$positions, $normals, $indices, $min, $max] = self::geometry($own);
            $vertexCount = intdiv(count($positions), 3);
            $indexType = $vertexCount > 65535 ? 5125 : 5123;

            $accessor = count($accessors);
            foreach ([pack('g*', ...$positions), pack('g*', ...$normals)] as $bytes) {
                $encoded = self::meshoptAttributes($bytes, 12);
                $views[] = [
                    'buffer' => 1, 'byteOffset' => $fallbackBytes, 'byteLength' => strlen($bytes), 'byteStride' => 12, 'target' => 34962,
                    'extensions' => ['EXT_meshopt_compression' => ['buffer' => 0, 'byteOffset' => strlen($binary), 'byteLength' => strlen($encoded), 'byteStride' => 12, 'mode' => 'ATTRIBUTES', 'count' => $vertexCount]],
                ];
                $binary .= self::aligned($encoded);
                $fallbackBytes += strlen($bytes);
            }
            $indexBytes = pack($indexType === 5125 ? 'V*' : 'v*', ...$indices);
            $views[] = ['buffer' => 0, 'byteOffset' => strlen($binary), 'byteLength' => strlen($indexBytes), 'target' => 34963];
            $binary .= self::aligned($indexBytes);
            $accessors[] = ['bufferView' => count($views) - 3, 'componentType' => 5126, 'count' => $vertexCount, 'type' => 'VEC3', 'min' => $min, 'max' => $max];
            $accessors[] = ['bufferView' => count($views) - 2, 'componentType' => 5126, 'count' => $vertexCount, 'type' => 'VEC3'];
            $accessors[] = ['bufferView' => count($views) - 1, 'componentType' => $indexType, 'count' => count($indices), 'type' => 'SCALAR'];

            $materials[] = array_filter([
                'name' => $name,
                'pbrMetallicRoughness' => ['baseColorFactor' => [...self::linear($hex), $opacity], 'metallicFactor' => 0.0, 'roughnessFactor' => $name === 'glass' ? 0.1 : 0.85],
                'alphaMode' => $opacity < 1 ? 'BLEND' : null,
                'doubleSided' => $opacity < 1 ? true : null,
            ], fn (mixed $value): bool => $value !== null);
            $meshes[] = ['name' => $name, 'primitives' => [['attributes' => ['POSITION' => $accessor, 'NORMAL' => $accessor + 1], 'indices' => $accessor + 2, 'material' => count($materials) - 1]]];
            $nodes[] = ['name' => 'Example '.str_replace('_', ' ', $name), 'mesh' => count($meshes) - 1];
        }

        foreach ($floor['anchors'] as $name => $position) {
            $nodes[] = ['name' => $name, 'translation' => $position];
        }

        foreach ($floor['views'] as $number => $view) {
            $nodes[] = ['name' => 'SG_CAM_'.($number + 1), 'translation' => $view['from'], 'rotation' => self::lookRotation($view['from'], $view['to'])];
        }

        $document = [
            'asset' => ['version' => '2.0', 'generator' => 'SmartGro demo seeder (OD-181): example floor, not a SmartGro layout'],
            'extensionsUsed' => ['EXT_meshopt_compression'],
            'extensionsRequired' => ['EXT_meshopt_compression'],
            'scene' => 0,
            'scenes' => [['name' => $floor['title'], 'nodes' => array_keys($nodes)]],
            'nodes' => $nodes,
            'meshes' => $meshes,
            'materials' => $materials,
            'accessors' => $accessors,
            'bufferViews' => $views,
            'buffers' => [['byteLength' => strlen($binary)], ['byteLength' => $fallbackBytes, 'extensions' => ['EXT_meshopt_compression' => ['fallback' => true]]]],
        ];

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
        $chunks = pack('VV', strlen($json), 0x4E4F534A).$json.pack('VV', strlen($binary), 0x004E4942).$binary;

        return pack('VVV', 0x46546C67, 2, 12 + strlen($chunks)).$chunks;
    }

    /**
     * The floor as a 2D plan: a PNG 2,400 pixels wide, drawn with the given TrueType fonts.
     */
    public static function floorPlan(string $key, string $font, string $boldFont): string
    {
        if (! is_file($font) || ! is_file($boldFont)) {
            throw new RuntimeException('The floor plan needs a TrueType font and its bold.');
        }

        $floor = self::floor($key);
        $scale = 70;
        $left = 150;
        $top = 230;
        $image = imagecreatetruecolor(2400, 1660);
        imageantialias($image, true);
        $colour = fn (string $hex): int => (int) imagecolorallocate($image, (int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2)));
        $point = fn (float $x, float $z): array => [(int) round($left + $x * $scale), (int) round($top + $z * $scale)];

        imagefilledrectangle($image, 0, 0, 2399, 1659, $colour('ffffff'));
        [$x0, $y0] = $point(0, 0);
        [$x1, $y1] = $point(self::WIDTH, self::DEPTH);
        imagefilledrectangle($image, $x0, $y0, $x1, $y1, $colour(self::PLAN_FILLS['floor']));

        foreach ($floor['zones'] as $zone) {
            [$zx0, $zy0] = $point($zone['area'][0], $zone['area'][1]);
            [$zx1, $zy1] = $point($zone['area'][2], $zone['area'][3]);
            imagefilledrectangle($image, $zx0, $zy0, $zx1, $zy1, $colour(self::PLAN_FILLS[$zone['material']]));
        }

        $outline = $colour('9aa59f');
        foreach ($floor['furniture'] as [$material, $fx0, $fy0, $fz0, $fx1, , $fz1]) {
            if ($fy0 > 1.5) {
                continue;
            }
            [$px0, $py0] = $point($fx0, $fz0);
            [$px1, $py1] = $point($fx1, $fz1);
            imagefilledrectangle($image, $px0, $py0, $px1, $py1, $colour($material === 'plant' ? 'b9d8a9' : ($material === 'seat' ? 'c9d6cf' : 'ffffff')));
            imagerectangle($image, $px0, $py0, $px1, $py1, $outline);
        }

        foreach ($floor['walls'] as [$wx0, $wz0, $wx1, $wz1, $kind]) {
            $half = (int) round(($kind === 'glass' ? 0.08 : self::WALL_THICKNESS) * $scale / 2);
            [$px0, $py0] = $point(min($wx0, $wx1), min($wz0, $wz1));
            [$px1, $py1] = $point(max($wx0, $wx1), max($wz0, $wz1));
            imagefilledrectangle($image, $px0 - $half, $py0 - $half, $px1 + $half, $py1 + $half, $colour($kind === 'glass' ? '6fa8ba' : '33403a'));
        }

        $ink = $colour('1f2a25');
        $muted = $colour('5b6661');
        $centred = function (string $text, float $size, string $fontFile, int $x, int $y, int $color) use ($image): void {
            $box = imagettfbbox($size, 0, $fontFile, $text);
            imagettftext($image, $size, 0, (int) round($x - ($box[2] - $box[0]) / 2), $y, $color, $fontFile, $text);
        };

        foreach ($floor['zones'] as $zone) {
            [$cx, $cy] = $point(...($zone['label'] ?? [($zone['area'][0] + $zone['area'][2]) / 2, ($zone['area'][1] + $zone['area'][3]) / 2]));
            $area = ($zone['area'][2] - $zone['area'][0]) * ($zone['area'][3] - $zone['area'][1]);
            $label = $zone['name'];
            $detail = trim(($zone['detail'] ?? '').' · '.round($area).' m²', ' ·');
            $tag = imagettfbbox(24, 0, $boldFont, $label);
            $width = max($tag[2] - $tag[0], imagettfbbox(18, 0, $font, $detail)[2]) + 28;
            imagefilledrectangle($image, $cx - intdiv($width, 2), $cy - 34, $cx + intdiv($width, 2), $cy + 34, $colour('ffffff'));
            imagerectangle($image, $cx - intdiv($width, 2), $cy - 34, $cx + intdiv($width, 2), $cy + 34, $colour('c7cfca'));
            $centred($label, 24, $boldFont, $cx, $cy - 2, $ink);
            $centred($detail, 18, $font, $cx, $cy + 26, $muted);
        }

        imagettftext($image, 44, 0, $left, 92, $ink, $boldFont, 'Example floor plan — not SmartGro\'s layout');
        imagettftext($image, 28, 0, $left, 150, $muted, $font, $floor['title'].'. Invented for the website demo; replace with SmartGro\'s own plan.');
        $badge = imagettfbbox(26, 0, $boldFont, 'EXAMPLE');
        imagefilledrectangle($image, 2250 - ($badge[2] - $badge[0]) - 36, 54, 2250, 112, $colour('1f4d3a'));
        imagettftext($image, 26, 0, 2250 - ($badge[2] - $badge[0]) - 18, 97, $colour('ffffff'), $boldFont, 'EXAMPLE');

        [$sx, $sy] = [$left, $y1 + 90];
        imagefilledrectangle($image, $sx, $sy, $sx + 5 * $scale, $sy + 10, $ink);
        foreach ([0, 5] as $metres) {
            imagefilledrectangle($image, $sx + $metres * $scale - 2, $sy - 12, $sx + $metres * $scale + 2, $sy + 10, $ink);
        }
        imagettftext($image, 20, 0, $sx + 5 * $scale + 20, $sy + 12, $muted, $font, '5 m');
        imagettftext($image, 20, 0, $sx + 5 * $scale + 120, $sy + 12, $muted, $font, 'Floor 30 × 18 m. Glass partitions in blue. Areas are approximate and invented.');

        return self::png($image);
    }

    private static function aligned(string $bytes): string
    {
        return $bytes.str_repeat("\0", (4 - strlen($bytes) % 4) % 4);
    }

    /**
     * Meshopt's vertex codec, version 0, in its simplest valid form (as the test models use it): each byte position
     * of the elements is delta-encoded, zigzagged and stored in groups of 16, every group raw (header code 3); the
     * tail holds the first element as the baseline, padded at the front to 32 bytes. The file is then no smaller,
     * but it is compressed in the form the upload checks and the viewer expect of a supplier's file.
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

    private static function png(GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * Positions, normals and indices of boxes, each face with its own corners so it is lit flat.
     *
     * @param  list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>  $boxes
     * @return array{0: list<float>, 1: list<float>, 2: list<int>, 3: list<float>, 4: list<float>}
     */
    private static function geometry(array $boxes): array
    {
        $positions = [];
        $normals = [];
        $indices = [];
        $min = [INF, INF, INF];
        $max = [-INF, -INF, -INF];

        foreach ($boxes as [, $x0, $y0, $z0, $x1, $y1, $z1]) {
            $faces = [
                [[1, 0, 0], [[$x1, $y0, $z0], [$x1, $y1, $z0], [$x1, $y1, $z1], [$x1, $y0, $z1]]],
                [[-1, 0, 0], [[$x0, $y0, $z0], [$x0, $y1, $z0], [$x0, $y1, $z1], [$x0, $y0, $z1]]],
                [[0, 1, 0], [[$x0, $y1, $z0], [$x1, $y1, $z0], [$x1, $y1, $z1], [$x0, $y1, $z1]]],
                [[0, -1, 0], [[$x0, $y0, $z0], [$x1, $y0, $z0], [$x1, $y0, $z1], [$x0, $y0, $z1]]],
                [[0, 0, 1], [[$x0, $y0, $z1], [$x1, $y0, $z1], [$x1, $y1, $z1], [$x0, $y1, $z1]]],
                [[0, 0, -1], [[$x0, $y0, $z0], [$x1, $y0, $z0], [$x1, $y1, $z0], [$x0, $y1, $z0]]],
            ];

            foreach ($faces as [$normal, $corners]) {
                // Counter-clockwise seen from outside, as glTF's front faces are.
                $edgeA = array_map(fn (float $a, float $b): float => $a - $b, $corners[1], $corners[0]);
                $edgeB = array_map(fn (float $a, float $b): float => $a - $b, $corners[2], $corners[0]);
                $cross = [$edgeA[1] * $edgeB[2] - $edgeA[2] * $edgeB[1], $edgeA[2] * $edgeB[0] - $edgeA[0] * $edgeB[2], $edgeA[0] * $edgeB[1] - $edgeA[1] * $edgeB[0]];

                if ($cross[0] * $normal[0] + $cross[1] * $normal[1] + $cross[2] * $normal[2] < 0) {
                    $corners = array_reverse($corners);
                }

                $first = intdiv(count($positions), 3);
                foreach ($corners as $corner) {
                    array_push($positions, ...$corner);
                    array_push($normals, ...$normal);
                    for ($axis = 0; $axis < 3; $axis++) {
                        $min[$axis] = min($min[$axis], $corner[$axis]);
                        $max[$axis] = max($max[$axis], $corner[$axis]);
                    }
                }
                array_push($indices, $first, $first + 1, $first + 2, $first, $first + 2, $first + 3);
            }
        }

        // Bounds as stored: single-precision floats.
        $single = fn (array $values): array => array_values(unpack('g*', pack('g*', ...$values)));

        return [array_map(floatval(...), $positions), array_map(floatval(...), $normals), $indices, $single($min), $single($max)];
    }

    /**
     * The rotation that turns a glTF camera node (looking along its -Z axis) at $from towards $to: yaw about Y,
     * then pitch about the node's own X axis.
     *
     * @param  array{0: float, 1: float, 2: float}  $from
     * @param  array{0: float, 1: float, 2: float}  $to
     * @return list<float> x, y, z, w
     */
    private static function lookRotation(array $from, array $to): array
    {
        [$dx, $dy, $dz] = [$to[0] - $from[0], $to[1] - $from[1], $to[2] - $from[2]];
        $yaw = atan2(-$dx, -$dz) / 2;
        $pitch = atan2($dy, sqrt($dx * $dx + $dz * $dz)) / 2;

        return [cos($yaw) * sin($pitch), sin($yaw) * cos($pitch), -sin($yaw) * sin($pitch), cos($yaw) * cos($pitch)];
    }

    /**
     * An sRGB colour as glTF's linear colour factors.
     *
     * @return list<float>
     */
    private static function linear(string $hex): array
    {
        return array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return round($value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4, 4);
        }, str_split($hex, 2));
    }

    /**
     * A desk (top and two side panels) at x0..x1, z0..z1.
     *
     * @return list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    private static function desk(float $x0, float $z0, float $x1, float $z1, string $top = 'wood'): array
    {
        return [
            [$top, $x0, 0.72, $z0, $x1, 0.75, $z1],
            ['dark', $x0 + 0.05, 0, $z0 + 0.05, $x0 + 0.1, 0.72, $z1 - 0.05],
            ['dark', $x1 - 0.1, 0, $z0 + 0.05, $x1 - 0.05, 0.72, $z1 - 0.05],
        ];
    }

    /**
     * A chair centred at x, z whose back is on the side given ('n', 's', 'e' or 'w').
     *
     * @return list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    private static function chair(float $x, float $z, string $back): array
    {
        $half = 0.25;
        $boxes = [['seat', $x - $half, 0.42, $z - $half, $x + $half, 0.5, $z + $half], ['dark', $x - 0.04, 0, $z - 0.04, $x + 0.04, 0.42, $z + 0.04]];
        $boxes[] = match ($back) {
            'n' => ['seat', $x - $half, 0.5, $z - $half, $x + $half, 0.95, $z - $half + 0.07],
            's' => ['seat', $x - $half, 0.5, $z + $half - 0.07, $x + $half, 0.95, $z + $half],
            'w' => ['seat', $x - $half, 0.5, $z - $half, $x - $half + 0.07, 0.95, $z + $half],
            default => ['seat', $x + $half - 0.07, 0.5, $z - $half, $x + $half, 0.95, $z + $half],
        };

        return $boxes;
    }

    /**
     * A table along x or z with chairs on its two long sides, $each per side.
     *
     * @return list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    private static function meetingTable(float $x0, float $z0, float $x1, float $z1, int $each): array
    {
        $boxes = [['wood', $x0, 0.72, $z0, $x1, 0.76, $z1], ['dark', ($x0 + $x1) / 2 - 0.3, 0, ($z0 + $z1) / 2 - 0.3, ($x0 + $x1) / 2 + 0.3, 0.72, ($z0 + $z1) / 2 + 0.3]];
        $alongX = $x1 - $x0 >= $z1 - $z0;

        for ($seat = 0; $seat < $each; $seat++) {
            $at = ($seat + 0.5) / $each;
            $boxes = $alongX
                ? [...$boxes, ...self::chair($x0 + ($x1 - $x0) * $at, $z0 - 0.35, 'n'), ...self::chair($x0 + ($x1 - $x0) * $at, $z1 + 0.35, 's')]
                : [...$boxes, ...self::chair($x0 - 0.35, $z0 + ($z1 - $z0) * $at, 'w'), ...self::chair($x1 + 0.35, $z0 + ($z1 - $z0) * $at, 'e')];
        }

        return $boxes;
    }

    /**
     * A bench of $pairs pairs of facing desks along x, from x0 at z0, each desk 1.6 m wide and 0.8 m deep.
     *
     * @return list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    private static function bench(float $x0, float $z0, int $pairs): array
    {
        $boxes = [];

        for ($pair = 0; $pair < $pairs; $pair++) {
            $x = $x0 + $pair * 1.6;
            $boxes = [...$boxes, ...self::desk($x, $z0, $x + 1.6, $z0 + 0.8), ...self::desk($x, $z0 + 0.8, $x + 1.6, $z0 + 1.6)];
            $boxes[] = ['white', $x + 0.1, 0.75, $z0 + 0.78, $x + 1.5, 1.15, $z0 + 0.82];
            $boxes = [...$boxes, ...self::chair($x + 0.8, $z0 - 0.35, 'n'), ...self::chair($x + 0.8, $z0 + 1.95, 's')];
        }

        return $boxes;
    }

    /**
     * A plant in a pot centred at x, z.
     *
     * @return list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    private static function plant(float $x, float $z): array
    {
        return [['wood', $x - 0.22, 0, $z - 0.22, $x + 0.22, 0.45, $z + 0.22], ['plant', $x - 0.35, 0.45, $z - 0.35, $x + 0.35, 1.4, $z + 0.35]];
    }

    /**
     * A sofa along x (back on the north or south side) or along z (back on the west or east side).
     *
     * @return list<array{0: string, 1: float, 2: float, 3: float, 4: float, 5: float, 6: float}>
     */
    private static function sofa(float $x0, float $z0, float $x1, float $z1, string $back): array
    {
        $boxes = [['accent', $x0, 0, $z0, $x1, 0.45, $z1]];
        $boxes[] = match ($back) {
            'n' => ['accent', $x0, 0.45, $z0, $x1, 0.85, $z0 + 0.2],
            's' => ['accent', $x0, 0.45, $z1 - 0.2, $x1, 0.85, $z1],
            'w' => ['accent', $x0, 0.45, $z0, $x0 + 0.2, 0.85, $z1],
            default => ['accent', $x1 - 0.2, 0.45, $z0, $x1, 0.85, $z1],
        };

        return $boxes;
    }

    /**
     * The outer walls: full height at the back and on the left, cut low at the front and on the right so the floor
     * can be seen from above, with the entrance in the front wall between $doorFrom and $doorTo.
     *
     * @return list<array{0: float, 1: float, 2: float, 3: float, 4: string}>
     */
    private static function outerWalls(float $doorFrom, float $doorTo): array
    {
        return [
            [0, 0, self::WIDTH, 0, 'solid'],
            [0, 0, 0, self::DEPTH, 'solid'],
            [self::WIDTH, 0, self::WIDTH, self::DEPTH, 'low'],
            [0, self::DEPTH, $doorFrom, self::DEPTH, 'low'],
            [$doorTo, self::DEPTH, self::WIDTH, self::DEPTH, 'low'],
        ];
    }

    private static function openPlan(): array
    {
        $furniture = [
            // Reception: desk, a sofa for visitors, plants.
            ['seat', 1.5, 0, 13.2, 5.5, 1.05, 14.2], ['wood', 1.4, 1.05, 13.1, 5.6, 1.1, 14.3],
            ...self::chair(3.5, 12.6, 'n'),
            ...self::sofa(0.3, 15.2, 1.2, 17.4, 'w'),
            ...self::plant(6.4, 17.3), ...self::plant(0.6, 12.4),
            // Open seating: four benches of six desks.
            ...self::bench(8.4, 8.2, 3), ...self::bench(14.0, 8.2, 3),
            ...self::bench(8.4, 12.2, 3), ...self::bench(14.0, 12.2, 3),
            ...self::plant(19.6, 15.6), ...self::plant(7.6, 15.6),
            // Phone booths.
            ['white', 18.5, 0, 0.3, 19.5, 1.05, 0.9], ['white', 20.5, 0, 0.3, 21.5, 1.05, 0.9],
            ...self::chair(19.0, 1.5, 's'), ...self::chair(21.0, 1.5, 's'),
            // Meeting room: a table for eight and a screen.
            ...self::meetingTable(24.0, 2.3, 28.0, 3.7, 4),
            ['dark', 24.8, 0.9, 0.12, 27.2, 2.2, 0.2],
            // Pantry and lounge: counter, high table and stools, sofas.
            ['white', 29.2, 0, 10.6, 29.9, 0.92, 17.6], ['wood', 29.1, 0.92, 10.5, 29.95, 0.96, 17.7],
            ['wood', 25.0, 1.02, 11.5, 27.4, 1.06, 12.4], ['dark', 26.0, 0, 11.8, 26.4, 1.02, 12.1],
            ...self::chair(25.6, 11.0, 'n'), ...self::chair(26.8, 11.0, 'n'), ...self::chair(25.6, 12.9, 's'), ...self::chair(26.8, 12.9, 's'),
            ...self::sofa(22.6, 14.6, 25.4, 15.5, 'n'), ...self::sofa(22.6, 16.8, 25.4, 17.7, 's'),
            ['wood', 23.3, 0.3, 15.8, 24.7, 0.4, 16.5],
            ...self::plant(28.6, 17.3), ...self::plant(22.6, 10.6),
        ];

        // A desk and two chairs in each cabin.
        foreach ([0.0, 4.5, 9.0, 13.5] as $x) {
            $furniture = [...$furniture, ...self::desk($x + 1.2, 1.0, $x + 3.3, 1.8), ...self::chair($x + 2.25, 0.6, 'n'), ...self::chair($x + 1.8, 2.4, 's'), ...self::chair($x + 2.8, 2.4, 's')];
        }

        return [
            'title' => 'Open plan with private cabins',
            'summary' => 'An invented floor: reception, 24 desks in open seating, 4 private cabins, a meeting room for 8, two phone booths and a pantry with a lounge.',
            'zones' => [
                ['name' => 'Cabin C1', 'detail' => '3 seats', 'area' => [0, 0, 4.5, 4], 'material' => 'carpet_warm', 'label' => [2.25, 3.35]],
                ['name' => 'Cabin C2', 'detail' => '3 seats', 'area' => [4.5, 0, 9, 4], 'material' => 'carpet_warm', 'label' => [6.75, 3.35]],
                ['name' => 'Cabin C3', 'detail' => '3 seats', 'area' => [9, 0, 13.5, 4], 'material' => 'carpet_warm', 'label' => [11.25, 3.35]],
                ['name' => 'Cabin C4', 'detail' => '3 seats', 'area' => [13.5, 0, 18, 4], 'material' => 'carpet_warm', 'label' => [15.75, 3.35]],
                ['name' => 'Booths', 'detail' => null, 'area' => [18, 0, 22, 2.2], 'material' => 'carpet_warm'],
                ['name' => 'Meeting room M1', 'detail' => '8 seats', 'area' => [22, 0, 30, 6], 'material' => 'carpet', 'label' => [26, 5.0]],
                ['name' => 'Reception', 'detail' => null, 'area' => [0, 12, 7, 18], 'material' => 'floor'],
                ['name' => 'Open seating', 'detail' => '24 desks', 'area' => [7.5, 6.5, 21, 15], 'material' => 'carpet', 'label' => [14.2, 11.0]],
                ['name' => 'Pantry and lounge', 'detail' => null, 'area' => [22, 10, 30, 18], 'material' => 'floor', 'label' => [26, 13.9]],
            ],
            'walls' => [
                ...self::outerWalls(2.0, 4.6),
                [0, 4, 18, 4, 'glass'], [4.5, 0, 4.5, 4, 'glass'], [9, 0, 9, 4, 'glass'], [13.5, 0, 13.5, 4, 'glass'], [18, 0, 18, 4, 'glass'],
                [18, 2.2, 22, 2.2, 'glass'], [20, 0, 20, 2.2, 'glass'],
                [22, 0, 22, 6, 'glass'], [22, 6, 30, 6, 'glass'],
            ],
            'furniture' => $furniture,
            'views' => [
                ['title' => 'Entrance and reception', 'from' => [3.3, 1.6, 17.4], 'to' => [8.0, 1.0, 10.0]],
                ['title' => 'Open seating', 'from' => [14.2, 1.7, 16.6], 'to' => [14.2, 0.8, 8.0]],
                ['title' => 'Private cabins', 'from' => [9.0, 1.6, 6.2], 'to' => [6.0, 1.1, 1.5]],
                ['title' => 'Meeting room', 'from' => [23.0, 1.6, 5.4], 'to' => [27.0, 1.0, 2.0]],
                ['title' => 'Pantry and lounge', 'from' => [21.0, 1.6, 9.0], 'to' => [27.0, 0.9, 15.0]],
            ],
            'anchors' => [
                'SG_SPACE_C1' => [2.25, 0, 2], 'SG_SPACE_C2' => [6.75, 0, 2], 'SG_SPACE_C3' => [11.25, 0, 2], 'SG_SPACE_C4' => [15.75, 0, 2],
                'SG_SPACE_M1' => [26, 0, 3], 'SG_SPACE_D1' => [14.2, 0, 10.8],
                'SG_AMENITY_reception' => [3.5, 0, 14], 'SG_AMENITY_pantry' => [27, 0, 14], 'SG_AMENITY_phone-booths' => [20, 0, 1],
            ],
        ];
    }

    private static function meetingFloor(): array
    {
        $furniture = [
            // Training room: eight tables of two facing the screen.
            ['dark', 3.0, 0.9, 0.12, 9.0, 2.4, 0.2],
            ...self::plant(11.4, 0.6),
            // Board room: a table for ten and a screen.
            ...self::meetingTable(13.5, 2.4, 18.5, 3.8, 5),
            ['dark', 14.8, 0.9, 0.12, 17.2, 2.2, 0.2],
            // Two small meeting rooms.
            ...self::meetingTable(21.3, 2.0, 23.7, 3.2, 2), ...self::meetingTable(26.3, 2.0, 28.7, 3.2, 2),
            // Dedicated desks: two benches of six.
            ...self::bench(16.6, 9.6, 3), ...self::bench(23.0, 9.6, 3),
            ...self::plant(15.4, 13.6), ...self::plant(29.4, 13.6),
            // Lounge: sofas around low tables, plants.
            ...self::sofa(0.4, 12.0, 1.3, 15.0, 'w'), ...self::sofa(1.8, 11.2, 4.8, 12.1, 'n'),
            ['wood', 2.0, 0.3, 12.8, 3.6, 0.4, 14.4],
            ...self::sofa(6.0, 16.9, 10.0, 17.8, 's'), ['wood', 7.2, 0.3, 15.4, 8.8, 0.4, 16.3],
            ...self::plant(0.7, 17.3), ...self::plant(11.3, 11.4),
            // Reception and pantry.
            ['seat', 12.6, 0, 15.6, 15.4, 1.05, 16.5], ['wood', 12.5, 1.05, 15.5, 15.5, 1.1, 16.6], ...self::chair(14.0, 15.0, 'n'),
            ['white', 25.0, 0, 17.3, 29.9, 0.92, 17.9], ['wood', 24.9, 0.92, 17.2, 29.95, 0.96, 17.95],
            ['wood', 26.2, 1.02, 15.4, 28.4, 1.06, 16.2], ['dark', 27.1, 0, 15.6, 27.5, 1.02, 16.0],
        ];

        foreach ([1.6, 3.3, 5.0, 6.7] as $z) {
            foreach ([1.6, 7.0] as $x) {
                $furniture = [...$furniture, ...self::desk($x, $z, $x + 3.4, $z + 0.6), ...self::chair($x + 0.85, $z + 1.0, 's'), ...self::chair($x + 2.55, $z + 1.0, 's')];
            }
        }

        return [
            'title' => 'Meeting and training floor',
            'summary' => 'An invented floor: a training room for 16, a board room for 10, two meeting rooms for 4, 12 dedicated desks, a lounge, reception and a pantry.',
            'zones' => [
                ['name' => 'Training room T1', 'detail' => '16 seats', 'area' => [0, 0, 12, 8.4], 'material' => 'carpet', 'label' => [6, 0.95]],
                ['name' => 'Board room B1', 'detail' => '10 seats', 'area' => [12, 0, 20, 6], 'material' => 'carpet_warm', 'label' => [16, 5.05]],
                ['name' => 'Meeting M1', 'detail' => '4 seats', 'area' => [20, 0, 25, 5], 'material' => 'carpet', 'label' => [22.5, 4.35]],
                ['name' => 'Meeting M2', 'detail' => '4 seats', 'area' => [25, 0, 30, 5], 'material' => 'carpet', 'label' => [27.5, 4.35]],
                ['name' => 'Dedicated desks', 'detail' => '12 desks', 'area' => [15, 8, 30, 14], 'material' => 'carpet', 'label' => [22.8, 13.2]],
                ['name' => 'Lounge', 'detail' => null, 'area' => [0, 10.5, 11.5, 18], 'material' => 'carpet_warm'],
                ['name' => 'Reception', 'detail' => null, 'area' => [12, 14.5, 17, 18], 'material' => 'floor', 'label' => [14.5, 17.35]],
                ['name' => 'Pantry', 'detail' => null, 'area' => [24, 14.5, 30, 18], 'material' => 'floor', 'label' => [27.3, 14.85]],
            ],
            'walls' => [
                ...self::outerWalls(13.2, 15.8),
                [0, 8.4, 12, 8.4, 'glass'], [12, 0, 12, 8.4, 'glass'],
                [12, 6, 20, 6, 'glass'], [20, 0, 20, 6, 'glass'],
                [20, 5, 30, 5, 'glass'], [25, 0, 25, 5, 'glass'],
            ],
            'furniture' => $furniture,
            'views' => [
                ['title' => 'Reception', 'from' => [14.5, 1.6, 17.6], 'to' => [17.0, 1.0, 10.0]],
                ['title' => 'Training room', 'from' => [11.0, 1.7, 8.0], 'to' => [5.0, 1.0, 1.5]],
                ['title' => 'Board room', 'from' => [19.4, 1.6, 5.6], 'to' => [15.0, 1.0, 2.0]],
                ['title' => 'Dedicated desks', 'from' => [29.0, 1.7, 15.0], 'to' => [20.0, 0.8, 10.5]],
                ['title' => 'Lounge', 'from' => [11.0, 1.6, 17.4], 'to' => [3.5, 0.6, 13.0]],
            ],
            'anchors' => [
                'SG_SPACE_T1' => [6, 0, 4], 'SG_SPACE_B1' => [16, 0, 3], 'SG_SPACE_M1' => [22.5, 0, 2.5], 'SG_SPACE_M2' => [27.5, 0, 2.5],
                'SG_SPACE_D1' => [22.5, 0, 11], 'SG_AMENITY_lounge' => [5.5, 0, 14], 'SG_AMENITY_reception' => [14, 0, 16], 'SG_AMENITY_pantry' => [27, 0, 16.5],
            ],
        ];
    }
}
