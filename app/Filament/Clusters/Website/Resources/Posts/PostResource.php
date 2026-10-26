<?php

namespace App\Filament\Clusters\Website\Resources\Posts;

use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\Resources\Posts\Pages\CreatePost;
use App\Filament\Clusters\Website\Resources\Posts\Pages\EditPost;
use App\Filament\Clusters\Website\Resources\Posts\Pages\ListPosts;
use App\Filament\Clusters\Website\Resources\Posts\Schemas\PostForm;
use App\Filament\Clusters\Website\Resources\Posts\Tables\PostsTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Insights articles (FR-CMS-7), published through draft, approval and publication like pages.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Insights';

    protected static ?string $modelLabel = 'article';

    protected static ?string $slug = 'insights';

    protected static ?int $navigationSort = 4;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return PostForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    /**
     * @param  Post  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->title ?? $record?->openRevision()?->payload['title'] ?? 'Untitled article';
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/create'),
            'edit' => EditPost::route('/{record}/edit'),
        ];
    }
}
