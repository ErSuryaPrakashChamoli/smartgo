<?php

namespace App\Filament\Clusters\Website\Resources\NavigationMenus\Pages;

use App\Filament\Clusters\Website\Resources\NavigationMenus\NavigationMenuResource;
use Filament\Resources\Pages\ListRecords;

class ListNavigationMenus extends ListRecords
{
    protected static string $resource = NavigationMenuResource::class;
}
