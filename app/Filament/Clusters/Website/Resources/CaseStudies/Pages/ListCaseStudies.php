<?php

namespace App\Filament\Clusters\Website\Resources\CaseStudies\Pages;

use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCaseStudies extends ListRecords
{
    protected static string $resource = CaseStudyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New customer story'),
        ];
    }
}
