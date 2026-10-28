<?php

namespace App\Filament\Clusters\Website\Resources\TwinModels\Pages;

use App\Filament\Clusters\Website\Resources\TwinModels\TwinModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTwinModels extends ListRecords
{
    protected static string $resource = TwinModelResource::class;

    public function getSubheading(): ?string
    {
        return 'Models are checked at upload and are not shown on the website: a published scene is needed first.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Upload 3D model'),
        ];
    }
}
