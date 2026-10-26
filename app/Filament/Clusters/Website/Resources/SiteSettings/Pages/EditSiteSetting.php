<?php

namespace App\Filament\Clusters\Website\Resources\SiteSettings\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\SiteSettings\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Resources\Pages\EditRecord;

/**
 * The editor of one settings group; the workflow lives in HasRevisionWorkflow. For legal details the
 * approval is the verification against the registration documents (OD-55).
 *
 * @property SiteSetting $record
 */
class EditSiteSetting extends EditRecord
{
    use HasRevisionWorkflow;

    protected static string $resource = SiteSettingResource::class;

    public function getTitle(): string
    {
        return $this->record->group()['name'];
    }

    public function getSubheading(): ?string
    {
        return $this->record->group()['purpose'];
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions();
    }

    protected function contentNoun(): string
    {
        return 'settings';
    }

    protected function approvalLabel(): string
    {
        return $this->record->handle === 'legal' ? 'Verify' : 'Approve';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return $payload;
    }

    protected function workflowExtraNotice(): string
    {
        return match ($this->record->handle) {
            'legal' => 'Verifying means checking every value against the registration documents; someone who edited this version cannot verify it.',
            'tracking' => 'Published IDs are not added to the website until cookie consent is decided (OD-100).',
            default => '',
        };
    }
}
