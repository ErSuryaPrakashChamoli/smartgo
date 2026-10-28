<?php

namespace App\Enums\Twin;

/**
 * The kinds of named node a 3D-modelling supplier puts in a GLB file (ADR-0009): a space, an amenity
 * or a camera position for the guided tour. Each is recognised by its prefix.
 */
enum AnchorType: string
{
    case Space = 'space';
    case Amenity = 'amenity';
    case Camera = 'camera';

    public function label(): string
    {
        return match ($this) {
            self::Space => 'Space',
            self::Amenity => 'Amenity',
            self::Camera => 'Camera position',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Space => 'SG_SPACE_',
            self::Amenity => 'SG_AMENITY_',
            self::Camera => 'SG_CAM_',
        };
    }
}
