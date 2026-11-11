<?php

namespace App\Filament\Clusters\Website\Resources\HomeBanners\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Concerns\TakesContentDown;
use App\Filament\Clusters\Website\Resources\HomeBanners\HomeBannerResource;
use App\Models\HomeBanner;
use Filament\Resources\Pages\EditRecord;

/**
 * The home banner editor; the workflow lives in HasRevisionWorkflow.
 *
 * @property HomeBanner $record
 */
class EditHomeBanner extends EditRecord
{
    use HasRevisionWorkflow, TakesContentDown;

    protected static string $resource = HomeBannerResource::class;

    public function getTitle(): string
    {
        return HomeBannerResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return match ($this->record->status) {
            'published' => 'Live in the carousel at the top of the home page',
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
        return 'home banner';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'heading' => $payload['heading'] ?? null,
            'text' => $payload['text'] ?? null,
            'media_asset_id' => $payload['media_asset_id'] ?? null,
            'image_alt' => $payload['image_alt'] ?? null,
            'primary_link' => $payload['primary_link'] ?? null,
            'secondary_link' => $payload['secondary_link'] ?? null,
            'sort_order' => $payload['sort_order'] ?? 0,
        ];
    }
}
