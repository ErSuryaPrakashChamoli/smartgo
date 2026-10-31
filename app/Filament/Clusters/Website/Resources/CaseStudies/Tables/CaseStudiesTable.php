<?php

namespace App\Filament\Clusters\Website\Resources\CaseStudies\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use App\Models\CaseStudy;
use Filament\Tables\Table;

class CaseStudiesTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table->emptyStateHeading('No customer stories yet')->emptyStateDescription('Add a story only with the customer\'s consent on file. It reaches the website once someone else has approved it and it is published.'), 'title', 'Customer story', 'Untitled customer story',
            description: fn (CaseStudy $record): ?string => $record->company_name ?? $record->workingRevision?->payload['company_name'] ?? null);
    }
}
