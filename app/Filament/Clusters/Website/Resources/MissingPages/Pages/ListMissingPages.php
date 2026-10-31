<?php

namespace App\Filament\Clusters\Website\Resources\MissingPages\Pages;

use App\Filament\Clusters\Website\Resources\MissingPages\MissingPageResource;
use Filament\Resources\Pages\ListRecords;

class ListMissingPages extends ListRecords
{
    protected static string $resource = MissingPageResource::class;
}
