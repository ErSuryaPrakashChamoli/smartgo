<?php

use App\Enums\Twin\CaptureType;

it('calls only a measured scan a scan and gives the other models their notice', function (CaptureType $type, string $title, ?string $notice) {
    expect($type->title('SmartGro A-70'))->toBe($title)
        ->and($type->disclaimer())->toBe($notice);
})->with([
    'measured scan' => [CaptureType::MeasuredScan, '3D scan of SmartGro A-70', null],
    'architectural model' => [CaptureType::ArchitecturalModel, '3D model of SmartGro A-70', 'Architectural model — final fit-out may differ'],
    'conceptual model' => [CaptureType::ConceptualModel, '3D model of SmartGro A-70', 'Conceptual illustration — not a scan of this location'],
]);
