<?php

namespace App\Filament\Clusters\Website\Resources\CaseStudies\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use App\Models\CaseStudy;
use Filament\Tables\Table;

class CaseStudiesTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table, 'title', 'Customer story', 'Untitled customer story',
            description: fn (CaseStudy $record): ?string => $record->company_name ?? $record->workingRevision?->payload['company_name'] ?? null);
    }
}
