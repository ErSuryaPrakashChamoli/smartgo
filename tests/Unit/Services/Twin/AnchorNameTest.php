<?php

use App\Enums\Twin\AnchorType;
use App\Services\Twin\AnchorName;

it('reads the three kinds of anchor', function (string $nodeName, AnchorType $type, string $reference) {
    expect((new AnchorName)->parse($nodeName))->toBe(['type' => $type, 'reference' => $reference]);
})->with([
    'space code' => ['SG_SPACE_A70-101', AnchorType::Space, 'A70-101'],
    'two-character space code' => ['SG_SPACE_B2', AnchorType::Space, 'B2'],
    'amenity' => ['SG_AMENITY_phone-booth', AnchorType::Amenity, 'phone-booth'],
    'camera' => ['SG_CAM_1', AnchorType::Camera, '1'],
    'camera 999' => ['SG_CAM_999', AnchorType::Camera, '999'],
]);

it('refuses names that start like anchors but break the convention', function (string $nodeName) {
    $anchorName = new AnchorName;

    expect($anchorName->looksLikeAnchor($nodeName))->toBeTrue()
        ->and($anchorName->parse($nodeName))->toBeNull();
})->with([
    'lowercase prefix' => ['sg_cam_1'],
    'lowercase space code' => ['SG_SPACE_a70-101'],
    'space code with a trailing hyphen' => ['SG_SPACE_A70-'],
    'one-character space code' => ['SG_SPACE_A'],
    'space code over 40 characters' => ['SG_SPACE_'.str_repeat('A', 41)],
    'uppercase amenity' => ['SG_AMENITY_Cafe'],
    'amenity with a space' => ['SG_AMENITY_tea point'],
    'camera 0' => ['SG_CAM_0'],
    'camera with a leading zero' => ['SG_CAM_01'],
    'camera 1000' => ['SG_CAM_1000'],
    'exporter suffix' => ['SG_CAM_1.001'],
    'trailing newline' => ["SG_CAM_1\n"],
    'unknown kind' => ['SG_ROOM_A70-101'],
    'prefix only' => ['SG_'],
]);

it('refuses an anchor name over 120 characters', function () {
    expect((new AnchorName)->parse('SG_AMENITY_'.str_repeat('a', 110)))->toBeNull()
        ->and((new AnchorName)->parse('SG_AMENITY_'.str_repeat('a', 109)))->not->toBeNull();
});

it('ignores ordinary node names', function (string $nodeName) {
    expect((new AnchorName)->looksLikeAnchor($nodeName))->toBeFalse();
})->with(['Floor', 'Chair.003', 'XSG_CAM_1', 'S_G_CAM_1']);
