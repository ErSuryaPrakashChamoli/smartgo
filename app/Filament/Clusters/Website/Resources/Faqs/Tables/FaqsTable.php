<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqsTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table, 'question', 'Question', 'New FAQ', extraColumns: [
            TextColumn::make('sort_order')->label('Position')->sortable(),
        ]);
    }
}
