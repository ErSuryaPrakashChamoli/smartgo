<?php

/*
 * Draws the two example floor plans of the demo (OD-181) from ExampleOfficeModel, the same description its 3D
 * models are built from, into this folder. Needs PHP's GD with FreeType and a TrueType font with its bold (DejaVu
 * Sans by default; pass other files as arguments):
 *
 *   php database/seeders/media/demo/floor-plans/draw.php [font.ttf bold.ttf]
 */

use Database\Seeders\ExampleOfficeModel;

require __DIR__.'/../../../../../vendor/autoload.php';

$font = $argv[1] ?? '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
$boldFont = $argv[2] ?? '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

foreach (ExampleOfficeModel::FLOORS as $floor) {
    $path = __DIR__."/{$floor}.png";
    file_put_contents($path, ExampleOfficeModel::floorPlan($floor, $font, $boldFont));
    echo $path, ' ', filesize($path), " bytes\n";
}
