<?php

namespace App\Filament\Clusters\Website\Resources\Pages\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * The page editor (FR-CMS-1, FR-CMS-2). The workflow lives in HasRevisionWorkflow; this page adds the
 * live-page link and the warning that a new address redirects the old one (BR-CMS-6).
 *
 * @property Page $record
 */
class EditPage extends EditRecord
{
    use HasRevisionWorkflow;

    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return PageResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return $this->record->isPublished()
            ? 'Live at '.$this->record->path()
            : 'Not published yet';
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions([
            Action::make('viewLive')
                ->label('View live page')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->record->isPublished())
                ->url(fn (): string => url((string) $this->record->path()), shouldOpenInNewTab: true),
        ]);
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'title' => $payload['title'] ?? null,
            'slug' => $payload['slug'] ?? null,
            'template' => $payload['template'] ?? 'standard',
            'legal_review_reference' => $payload['legal_review_reference'] ?? null,
            'blocks' => $payload['blocks'] ?? [],
            'seo' => [
                'title' => $payload['seo']['title'] ?? null,
                'description' => $payload['seo']['description'] ?? null,
                'indexable' => $payload['seo']['indexable'] ?? true,
                'image_asset_id' => $payload['seo']['image_asset_id'] ?? null,
            ],
        ];
    }

    protected function workflowExtraNotice(): string
    {
        $newSlug = $this->workingRevision()?->payload['slug'] ?? null;

        return $this->record->isPublished() && filled($newSlug) && $newSlug !== $this->record->slug
            ? "The old address {$this->record->path()} will redirect permanently to /{$newSlug}."
            : '';
    }
}
