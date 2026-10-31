<?php

namespace App\Filament\Clusters\Sales\Resources\Tours\Pages;

use App\Filament\Clusters\Sales\Resources\Tours\TourResource;
use Filament\Resources\Pages\ListRecords;

class ListTours extends ListRecords
{
    protected static string $resource = TourResource::class;

    public function getSubheading(): ?string
    {
        return 'A tour is a visit, not a booking: nothing is reserved, priced or charged.';
    }
}
