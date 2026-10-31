<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqsTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table->emptyStateHeading('No questions yet')->emptyStateDescription('Add a question with its answer. It appears on the FAQ page, or on a location\'s or workspace type\'s page, once someone else has approved it and it is published.'), 'question', 'Question', 'New FAQ', extraColumns: [
            TextColumn::make('sort_order')->label('Position')->sortable(),
        ]);
    }
}
