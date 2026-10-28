<?php

namespace App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages;

use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListPropertyProfiles extends ListRecords
{
    protected static string $resource = PropertyProfileResource::class;
}
