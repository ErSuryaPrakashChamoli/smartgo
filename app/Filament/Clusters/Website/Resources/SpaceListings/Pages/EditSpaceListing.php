<?php

namespace App\Filament\Clusters\Website\Resources\SpaceListings\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Concerns\TakesContentDown;
use App\Filament\Clusters\Website\Resources\SpaceListings\SpaceListingResource;
use App\Models\SpaceListing;
use Filament\Resources\Pages\EditRecord;

/**
 * The space listing editor; the workflow lives in HasRevisionWorkflow, "Take off the website" in TakesContentDown.
 *
 * @property SpaceListing $record
 */
class EditSpaceListing extends EditRecord
{
    use HasRevisionWorkflow, TakesContentDown;

    protected static string $resource = SpaceListingResource::class;

    public function getTitle(): string
    {
        return SpaceListingResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return match ($this->record->status) {
            'published' => 'On the website at '.$this->record->path().($this->record->is_sample ? ', marked as an example space' : ''),
            'unpublished' => 'Switched off: not on the website',
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
        return 'space';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'property_id' => $payload['property_id'] ?? null,
            'space_type_id' => $payload['space_type_id'] ?? null,
            'space_id' => $payload['space_id'] ?? null,
            'name' => $payload['name'] ?? null,
            'slug' => $payload['slug'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'details' => $payload['details'] ?? null,
            'seats' => $payload['seats'] ?? null,
            'features' => $payload['features'] ?? [],
            'media_asset_id' => $payload['media_asset_id'] ?? null,
            'is_sample' => (bool) ($payload['is_sample'] ?? false),
            'sort_order' => $payload['sort_order'] ?? 0,
        ];
    }
}
