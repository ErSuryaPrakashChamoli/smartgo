<?php

namespace App\Filament\Clusters\Website\Resources\SpaceListings\Pages;

use App\Filament\Clusters\Website\Resources\SpaceListings\SpaceListingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpaceListings extends ListRecords
{
    protected static string $resource = SpaceListingResource::class;

    public function getSubheading(): ?string
    {
        return 'The spaces under Spaces in the website\'s menu, by space type and location. Switching one off hides it at once; switching it on sends it for approval, and it shows once someone else approves and publishes it.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New space'),
        ];
    }
}
