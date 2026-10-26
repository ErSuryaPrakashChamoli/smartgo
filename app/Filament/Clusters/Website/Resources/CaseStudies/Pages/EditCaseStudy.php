<?php

namespace App\Filament\Clusters\Website\Resources\CaseStudies\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Concerns\TakesContentDown;
use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use App\Models\CaseStudy;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * The customer story editor; the workflow lives in HasRevisionWorkflow.
 *
 * @property CaseStudy $record
 */
class EditCaseStudy extends EditRecord
{
    use HasRevisionWorkflow, TakesContentDown;

    protected static string $resource = CaseStudyResource::class;

    public function getTitle(): string
    {
        return CaseStudyResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return match ($this->record->status) {
            'published' => 'Live at '.$this->record->path(),
            'unpublished' => 'Taken off the website',
            default => 'Not published yet',
        };
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions([
            Action::make('viewLive')
                ->label('View live story')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->record->isPublished())
                ->url(fn (): string => url((string) $this->record->path()), shouldOpenInNewTab: true),
            $this->takeDownAction(),
        ]);
    }

    protected function contentNoun(): string
    {
        return 'customer story';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'title' => $payload['title'] ?? null,
            'slug' => $payload['slug'] ?? null,
            'company_name' => $payload['company_name'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'consent_on_file' => (bool) ($payload['consent_on_file'] ?? false),
            'consent_reference' => $payload['consent_reference'] ?? null,
            'blocks' => $payload['blocks'] ?? [],
        ];
    }

    protected function workflowExtraNotice(): string
    {
        $newSlug = $this->workingRevision()?->payload['slug'] ?? null;

        return $this->record->isPublished() && filled($newSlug) && $newSlug !== $this->record->slug
            ? "The old address {$this->record->path()} will redirect permanently to /customers/{$newSlug}."
            : $this->takeDownNotice();
    }
}
