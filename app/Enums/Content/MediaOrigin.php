<?php

namespace App\Enums\Content;

/**
 * Where a media file comes from (BR-MED-1). AI concepts and renders are never shown as real photography:
 * they carry a visible "Concept visual" label and never enter a property gallery (BR-MED-2).
 */
enum MediaOrigin: string
{
    case ClientPhotography = 'client_photography';
    case Commissioned = 'commissioned';
    case LicensedStock = 'licensed_stock';
    case AiGeneratedConcept = 'ai_generated_concept';
    case Render = 'render';

    public function label(): string
    {
        return match ($this) {
            self::ClientPhotography => 'SmartGro photography',
            self::Commissioned => 'Commissioned',
            self::LicensedStock => 'Licensed stock',
            self::AiGeneratedConcept => 'AI concept',
            self::Render => 'Render',
        };
    }

    /**
     * Shown with a "Concept visual" label wherever it appears (BR-MED-2).
     */
    public function isConcept(): bool
    {
        return in_array($this, [self::AiGeneratedConcept, self::Render], true);
    }
}
