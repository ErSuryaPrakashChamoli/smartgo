<?php

namespace App\Filament\Clusters\Website\Resources\HomeBanners\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HomeBannersTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table->emptyStateHeading('No home banners yet')->emptyStateDescription('Add a banner with a heading, an image from the media library and up to two buttons. It joins the carousel at the top of the home page once someone else has approved it and it is published. Until then the home page shows its single hero.'), 'heading', 'Heading', 'New home banner', extraColumns: [
            TextColumn::make('sort_order')->label('Position')->sortable(),
        ]);
    }
}
