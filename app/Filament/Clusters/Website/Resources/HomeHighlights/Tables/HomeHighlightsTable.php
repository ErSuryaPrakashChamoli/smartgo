<?php

namespace App\Filament\Clusters\Website\Resources\HomeHighlights\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomeHighlightsTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table->emptyStateHeading('No highlights yet')->emptyStateDescription('Add a short line, such as a facility or a service. It joins the moving strip under the home page\'s banners once someone else has approved it and it is published. Until then the strip shows the workspace types and locations on the website.'), 'text', 'Highlight', 'New highlight', extraColumns: [
            IconColumn::make('is_sample')->label('Example text')->boolean(),
            TextColumn::make('sort_order')->label('Position')->sortable(),
        ]);
    }
}
