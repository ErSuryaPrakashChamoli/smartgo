<?php

namespace App\Filament\Clusters\Website\Resources\NavigationMenus\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\NavigationMenus\NavigationMenuResource;
use App\Models\NavigationMenu;
use Filament\Resources\Pages\EditRecord;

/**
 * The menu editor. The workflow lives in HasRevisionWorkflow, as for pages.
 *
 * @property NavigationMenu $record
 */
class EditNavigationMenu extends EditRecord
{
    use HasRevisionWorkflow;

    protected static string $resource = NavigationMenuResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->structure()['purpose'];
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions();
    }

    protected function contentNoun(): string
    {
        return 'menu';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'items' => $payload['items'] ?? [],
        ];
    }
}
