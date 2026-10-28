<?php

namespace App\Filament\Clusters\Properties\Resources\Cities\Pages;

use App\Filament\Clusters\Properties\Resources\Cities\CityResource;
use Filament\Resources\Pages\EditRecord;

class EditCity extends EditRecord
{
    protected static string $resource = CityResource::class;
}
