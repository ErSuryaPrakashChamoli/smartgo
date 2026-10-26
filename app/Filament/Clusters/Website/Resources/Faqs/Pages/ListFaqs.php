<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Pages;

use App\Filament\Clusters\Website\Resources\Faqs\FaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaqs extends ListRecords
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New FAQ'),
        ];
    }
}
