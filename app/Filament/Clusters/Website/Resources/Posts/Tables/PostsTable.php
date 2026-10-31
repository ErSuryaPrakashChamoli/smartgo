<?php

namespace App\Filament\Clusters\Website\Resources\Posts\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use App\Models\Post;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table->emptyStateHeading('No articles yet')->emptyStateDescription('Write an article and submit it for approval. Insights lists it once someone else has approved it and it is published.'), 'title', 'Article', 'Untitled article',
            description: fn (Post $record): string => $record->path() ?? '/insights/'.($record->workingRevision?->payload['slug'] ?? '…').' (not published)');
    }
}
