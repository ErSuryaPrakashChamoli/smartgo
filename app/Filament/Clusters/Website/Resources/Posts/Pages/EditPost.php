<?php

namespace App\Filament\Clusters\Website\Resources\Posts\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

/**
 * The article editor; the workflow lives in HasRevisionWorkflow.
 *
 * @property Post $record
 */
class EditPost extends EditRecord
{
    use HasRevisionWorkflow;

    protected static string $resource = PostResource::class;

    public function getTitle(): string
    {
        return PostResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return $this->record->isPublished() ? 'Live at '.$this->record->path() : 'Not published yet';
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions([
            Action::make('viewLive')
                ->label('View live article')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->record->isPublished())
                ->url(fn (): string => url((string) $this->record->path()), shouldOpenInNewTab: true),
        ]);
    }

    protected function contentNoun(): string
    {
        return 'article';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'title' => $payload['title'] ?? null,
            'slug' => $payload['slug'] ?? null,
            'excerpt' => $payload['excerpt'] ?? null,
            'blocks' => $payload['blocks'] ?? [],
        ];
    }

    protected function workflowExtraNotice(): string
    {
        $newSlug = $this->workingRevision()?->payload['slug'] ?? null;

        return $this->record->isPublished() && filled($newSlug) && $newSlug !== $this->record->slug
            ? "The old address {$this->record->path()} will redirect permanently to /insights/{$newSlug}."
            : '';
    }
}
