<?php

namespace App\Filament\Clusters\Website\Resources\HomeBanners\Pages;

use App\Filament\Clusters\Website\Resources\HomeBanners\HomeBannerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHomeBanners extends ListRecords
{
    protected static string $resource = HomeBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New home banner'),
        ];
    }
}
