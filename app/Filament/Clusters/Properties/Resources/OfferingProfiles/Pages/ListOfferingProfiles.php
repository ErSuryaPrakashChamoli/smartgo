<?php

namespace App\Filament\Clusters\Properties\Resources\OfferingProfiles\Pages;

use App\Filament\Clusters\Properties\Resources\OfferingProfiles\OfferingProfileResource;
use Filament\Resources\Pages\ListRecords;

class ListOfferingProfiles extends ListRecords
{
    protected static string $resource = OfferingProfileResource::class;
}
