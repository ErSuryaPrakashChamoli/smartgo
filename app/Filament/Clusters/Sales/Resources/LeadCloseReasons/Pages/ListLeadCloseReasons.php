<?php

namespace App\Filament\Clusters\Sales\Resources\LeadCloseReasons\Pages;

use App\Filament\Clusters\Sales\Resources\LeadCloseReasons\LeadCloseReasonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeadCloseReasons extends ListRecords
{
    protected static string $resource = LeadCloseReasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New closing reason'),
        ];
    }
}
