<?php

namespace App\Filament\Clusters\Properties\Resources\Offers\Pages;

use App\Filament\Clusters\Properties\Resources\Offers\OfferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOffers extends ListRecords
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New offer'),
        ];
    }
}
