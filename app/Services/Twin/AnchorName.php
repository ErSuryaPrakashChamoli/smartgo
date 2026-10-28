<?php

namespace App\Services\Twin;

use App\Enums\Twin\AnchorType;

/**
 * The naming convention for anchors in a GLB file (ADR-0009, ERD §8): SG_SPACE_ followed by the space
 * code (capitals, digits and inner hyphens, as INVENTORY_IMPORT_SPEC V-102 requires of codes),
 * SG_AMENITY_ followed by a lowercase name with hyphens, and SG_CAM_ followed by a camera number from 1.
 * A node whose name starts with "SG_" in any letter case is meant as an anchor, so one that breaks the
 * convention is reported rather than silently ignored.
 */
class AnchorName
{
    public const int MAX_LENGTH = 120;

    public function looksLikeAnchor(string $nodeName): bool
    {
        return stripos($nodeName, 'SG_') === 0;
    }

    /**
     * @return array{type: AnchorType, reference: string}|null null when the name breaks the convention
     */
    public function parse(string $nodeName): ?array
    {
        if (strlen($nodeName) > self::MAX_LENGTH) {
            return null;
        }

        foreach (AnchorType::cases() as $type) {
            if (str_starts_with($nodeName, $type->prefix())) {
                $reference = substr($nodeName, strlen($type->prefix()));

                return preg_match($this->pattern($type), $reference) === 1 ? ['type' => $type, 'reference' => $reference] : null;
            }
        }

        return null;
    }

    private function pattern(AnchorType $type): string
    {
        return match ($type) {
            AnchorType::Space => '/^[A-Z0-9][A-Z0-9-]{0,38}[A-Z0-9]\z/',
            AnchorType::Amenity => '/^[a-z0-9]+(?:-[a-z0-9]+)*\z/',
            AnchorType::Camera => '/^[1-9][0-9]{0,2}\z/',
        };
    }
}
