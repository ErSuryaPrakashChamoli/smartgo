<?php

namespace App\Filament\Clusters\Website\Resources\HomeHighlights\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Concerns\TakesContentDown;
use App\Filament\Clusters\Website\Resources\HomeHighlights\HomeHighlightResource;
use App\Models\HomeHighlight;
use Filament\Resources\Pages\EditRecord;

/**
 * The highlight editor; the workflow lives in HasRevisionWorkflow.
 *
 * @property HomeHighlight $record
 */
class EditHomeHighlight extends EditRecord
{
    use HasRevisionWorkflow, TakesContentDown;

    protected static string $resource = HomeHighlightResource::class;

    public function getTitle(): string
    {
        return HomeHighlightResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return match ($this->record->status) {
            'published' => $this->record->is_sample ? 'Live in the home page\'s highlights strip, marked as example text' : 'Live in the home page\'s highlights strip',
            'unpublished' => 'Taken off the website',
            default => 'Not published yet',
        };
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions([$this->takeDownAction()]);
    }

    protected function workflowExtraNotice(): string
    {
        return $this->takeDownNotice();
    }

    protected function contentNoun(): string
    {
        return 'highlight';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'text' => $payload['text'] ?? null,
            'icon' => $payload['icon'] ?? HomeHighlight::DEFAULT_ICON,
            'is_sample' => (bool) ($payload['is_sample'] ?? false),
            'sort_order' => $payload['sort_order'] ?? 0,
        ];
    }
}
