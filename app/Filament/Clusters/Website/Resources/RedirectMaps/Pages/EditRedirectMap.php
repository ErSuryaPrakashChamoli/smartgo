<?php

namespace App\Filament\Clusters\Website\Resources\RedirectMaps\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\RedirectMaps\RedirectMapResource;
use App\Filament\Clusters\Website\Resources\Redirects\RedirectResource;
use App\Models\RedirectMap;
use Filament\Resources\Pages\EditRecord;

/**
 * The manual redirect list editor; the workflow lives in HasRevisionWorkflow. Publishing replaces the
 * live manual redirects with the version's list.
 *
 * @property RedirectMap $record
 */
class EditRedirectMap extends EditRecord
{
    use HasRevisionWorkflow;

    protected static string $resource = RedirectMapResource::class;

    public function getTitle(): string
    {
        return 'Manual redirects';
    }

    public function getSubheading(): ?string
    {
        return 'Changes go live only after someone other than the author approves them and someone other than the approver publishes them (OD-66).';
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [RedirectResource::getUrl('index') => 'Redirects', 'Manual redirects'];
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions();
    }

    protected function contentNoun(): string
    {
        return 'redirect list';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return ['redirects' => $payload['redirects'] ?? []];
    }

    protected function workflowExtraNotice(): string
    {
        return 'Every new address must already be published when the list is submitted and published.';
    }
}
