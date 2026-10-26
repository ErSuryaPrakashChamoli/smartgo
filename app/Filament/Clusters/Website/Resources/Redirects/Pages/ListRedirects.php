<?php

namespace App\Filament\Clusters\Website\Resources\Redirects\Pages;

use App\Filament\Clusters\Website\Resources\RedirectMaps\RedirectMapResource;
use App\Filament\Clusters\Website\Resources\Redirects\RedirectResource;
use App\Models\RedirectMap;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    public function getSubheading(): ?string
    {
        return 'Live redirects. A page\'s change of address adds one automatically; others are made in the manual list, which needs approval before it goes live (OD-66).';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editManualList')
                ->label('Manual redirects')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(fn (): string => RedirectMapResource::getUrl('edit', ['record' => RedirectMap::query()->firstOrFail()])),
        ];
    }
}
