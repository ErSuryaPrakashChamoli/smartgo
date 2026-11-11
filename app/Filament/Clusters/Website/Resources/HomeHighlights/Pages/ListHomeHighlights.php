<?php

namespace App\Filament\Clusters\Website\Resources\HomeHighlights\Pages;

use App\Filament\Clusters\Website\Resources\HomeHighlights\HomeHighlightResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeHighlights extends ListRecords
{
    protected static string $resource = HomeHighlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New highlight'),
        ];
    }
}
