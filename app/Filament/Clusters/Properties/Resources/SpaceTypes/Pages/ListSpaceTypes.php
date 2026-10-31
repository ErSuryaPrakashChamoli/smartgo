<?php

namespace App\Filament\Clusters\Properties\Resources\SpaceTypes\Pages;

use App\Filament\Clusters\Properties\Resources\SpaceTypes\SpaceTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpaceTypes extends ListRecords
{
    protected static string $resource = SpaceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New space type'),
        ];
    }
}
