<?php

namespace App\Filament\Clusters\Website\Resources\Testimonials\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use App\Models\Testimonial;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TestimonialsTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table, 'person_name', 'From', 'Unnamed',
            description: fn (Testimonial $record): ?string => ($quote = $record->quote ?? $record->workingRevision?->payload['quote'] ?? null) === null ? null : Str::limit($quote, 90));
    }
}
