<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\Pages;

use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New property'),
        ];
    }
}
