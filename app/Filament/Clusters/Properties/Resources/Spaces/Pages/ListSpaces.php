<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces\Pages;

use App\Filament\Clusters\Properties\Resources\Spaces\SpaceResource;
use Filament\Resources\Pages\ListRecords;

class ListSpaces extends ListRecords
{
    protected static string $resource = SpaceResource::class;

    public function getSubheading(): ?string
    {
        return 'Availability is worked out from each space\'s status, seats and allocations; it is never typed in.';
    }
}
