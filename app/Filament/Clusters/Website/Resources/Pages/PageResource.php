<?php

namespace App\Filament\Clusters\Website\Resources\Pages;

use App\Filament\Clusters\Website\Resources\Pages\Pages\CreatePage;
use App\Filament\Clusters\Website\Resources\Pages\Pages\EditPage;
use App\Filament\Clusters\Website\Resources\Pages\Pages\ListPages;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\Resources\Pages\Schemas\PageForm;
use App\Filament\Clusters\Website\Resources\Pages\Tables\PagesTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\Page;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Website pages (FR-CMS-1, FR-CMS-2). The live record is never edited here: every change is a revision
 * that goes through draft, approval and publication.
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $modelLabel = 'page';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PagesTable::configure($table);
    }

    /**
     * @param  Page  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->title ?? $record?->openRevision()?->payload['title'] ?? 'Untitled page';
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
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
