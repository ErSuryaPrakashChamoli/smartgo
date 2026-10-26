<?php

namespace App\Filament\Clusters\Website\Resources\SiteSettings\Pages;

use App\Filament\Clusters\Website\Resources\SiteSettings\SiteSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListSiteSettings extends ListRecords
{
    protected static string $resource = SiteSettingResource::class;
}
