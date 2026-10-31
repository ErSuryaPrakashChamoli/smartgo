<?php

namespace App\Filament\Clusters\Properties\Resources\PricePlans\Pages;

use App\Filament\Clusters\Properties\Resources\PricePlans\PricePlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPricePlans extends ListRecords
{
    protected static string $resource = PricePlanResource::class;

    public function getSubheading(): ?string
    {
        return 'Prices are not shown on the website until SmartGro decides how prices are displayed (D-04).';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New price plan'),
        ];
    }
}
