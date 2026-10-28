<?php

namespace App\Enums\Twin;

/**
 * How a 3D model was made (ERD §8). Only a measured scan of the location may be called a scan; an
 * architectural or conceptual model always carries its notice, which editors cannot remove (BR-3D-1,
 * BR-3D-2). The capture type is set at upload and never changes.
 */
enum CaptureType: string
{
    case MeasuredScan = 'measured_scan';
    case ArchitecturalModel = 'architectural_model';
    case ConceptualModel = 'conceptual_model';

    public function label(): string
    {
        return match ($this) {
            self::MeasuredScan => 'Measured scan of the location',
            self::ArchitecturalModel => 'Architectural model',
            self::ConceptualModel => 'Conceptual model',
        };
    }

    /**
     * The heading for a model of one location: "3D scan" only for a measured scan (BR-3D-1).
     */
    public function title(string $locationName): string
    {
        return ($this === self::MeasuredScan ? '3D scan of ' : '3D model of ').$locationName;
    }

    /**
     * The notice shown with the model wherever it appears (BR-3D-2); a measured scan needs none.
     */
    public function disclaimer(): ?string
    {
        return match ($this) {
            self::MeasuredScan => null,
            self::ArchitecturalModel => 'Architectural model — final fit-out may differ',
            self::ConceptualModel => 'Conceptual illustration — not a scan of this location',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])->all();
    }
}
