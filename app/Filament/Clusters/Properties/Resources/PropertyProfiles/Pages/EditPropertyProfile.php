<?php

namespace App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages;

use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Concerns\TakesContentDown;
use App\Models\PropertyProfile;
use Filament\Resources\Pages\EditRecord;

/**
 * The property profile editor; the workflow lives in HasRevisionWorkflow. Publishing the profile puts
 * the property on the website (while its state allows it); taking it down removes the property at once.
 *
 * @property PropertyProfile $record
 */
class EditPropertyProfile extends EditRecord
{
    use HasRevisionWorkflow, TakesContentDown;

    protected static string $resource = PropertyProfileResource::class;

    public function getTitle(): string
    {
        return 'Website profile: SmartGro '.$this->record->property->name;
    }

    public function getSubheading(): ?string
    {
        $property = $this->record->property;

        return match (true) {
            $property->isPubliclyListed() => 'On the website at '.$property->path(),
            $this->record->isPublished() => 'Published, but the property\'s state keeps it off the website ('.strtolower($property->operational_status->label()).').',
            default => 'Not on the website: the property appears once this profile is approved and published.',
        };
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions([$this->takeDownAction()]);
    }

    protected function contentNoun(): string
    {
        return 'profile';
    }

    protected function workflowExtraNotice(): string
    {
        return $this->takeDownNotice();
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'headline' => $payload['headline'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'getting_there' => $payload['getting_there'] ?? null,
            'nearest_metro' => $payload['nearest_metro'] ?? null,
            'metro_walk_minutes' => $payload['metro_walk_minutes'] ?? null,
            'buses' => $payload['buses'] ?? null,
            'parking' => $payload['parking'] ?? null,
            'accessibility' => $payload['accessibility'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'whatsapp' => $payload['whatsapp'] ?? null,
            'email' => $payload['email'] ?? null,
            'hero_asset_id' => $payload['hero_asset_id'] ?? null,
            'gallery_asset_ids' => array_map(strval(...), $payload['gallery_asset_ids'] ?? []),
            'seo_title' => $payload['seo_title'] ?? null,
            'seo_description' => $payload['seo_description'] ?? null,
        ];
    }
}
