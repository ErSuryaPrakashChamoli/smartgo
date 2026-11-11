<?php

namespace App\Enums\Crm;

/**
 * What a pipeline stage means for a deal (`pipeline_stages.outcome`, FR-CRM-4): still open, won or lost.
 * One value per stage, so a stage can never be both won and lost.
 */
enum PipelineOutcome: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Won => 'success',
            self::Lost => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $outcome): array => [$outcome->value => $outcome->label()])->all();
    }
}
