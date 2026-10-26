<?php

namespace App\Filament\Clusters\Website\Resources\Posts\Tables;

use App\Filament\Clusters\Website\Tables\RevisionedContentTable;
use App\Models\Post;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return RevisionedContentTable::configure($table, 'title', 'Article', 'Untitled article',
            description: fn (Post $record): string => $record->path() ?? '/insights/'.($record->workingRevision?->payload['slug'] ?? '…').' (not published)');
    }
}
