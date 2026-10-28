<?php

namespace App\Filament\Clusters\Properties\Resources\OfferingProfiles\Pages;

use App\Filament\Clusters\Properties\Resources\OfferingProfiles\OfferingProfileResource;
use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Concerns\TakesContentDown;
use App\Models\OfferingProfile;
use Filament\Resources\Pages\EditRecord;

/**
 * The workspace offering editor; the workflow lives in HasRevisionWorkflow.
 *
 * @property OfferingProfile $record
 */
class EditOfferingProfile extends EditRecord
{
    use HasRevisionWorkflow, TakesContentDown;

    protected static string $resource = OfferingProfileResource::class;

    public function getTitle(): string
    {
        return $this->record->offering->displayName();
    }

    public function getSubheading(): ?string
    {
        $offering = $this->record->offering;

        return match (true) {
            $offering->isPubliclyListed() => 'On the website at '.$offering->path(),
            ! $offering->is_active => 'Operations staff no longer record this category at the property, so it cannot be shown.',
            $this->record->isPublished() => 'Published. It appears on the website once the property\'s own profile is published and its state allows it.',
            default => 'Not on the website: it appears once this profile is approved and published and the property is on the website.',
        };
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions([$this->takeDownAction()]);
    }

    protected function contentNoun(): string
    {
        return 'workspace offering';
    }

    protected function workflowExtraNotice(): string
    {
        return $this->takeDownNotice();
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'headline' => $payload['headline'] ?? null,
            'description' => $payload['description'] ?? null,
            'included' => $payload['included'] ?? [],
            'not_included' => $payload['not_included'] ?? [],
            'hero_asset_id' => $payload['hero_asset_id'] ?? null,
            'gallery_asset_ids' => array_map(strval(...), $payload['gallery_asset_ids'] ?? []),
            'seo_title' => $payload['seo_title'] ?? null,
            'seo_description' => $payload['seo_description'] ?? null,
        ];
    }
}
