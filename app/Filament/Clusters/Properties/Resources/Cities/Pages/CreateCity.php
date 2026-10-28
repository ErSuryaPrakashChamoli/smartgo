<?php

namespace App\Filament\Clusters\Properties\Resources\Cities\Pages;

use App\Filament\Clusters\Properties\Resources\Cities\CityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCity extends CreateRecord
{
    protected static string $resource = CityResource::class;

    protected static bool $canCreateAnother = false;
}
