<?php

namespace App\Filament\Clusters\Website\Resources\HomeHighlights;

use App\Filament\Clusters\Website\Resources\HomeHighlights\Pages\CreateHomeHighlight;
use App\Filament\Clusters\Website\Resources\HomeHighlights\Pages\EditHomeHighlight;
use App\Filament\Clusters\Website\Resources\HomeHighlights\Pages\ListHomeHighlights;
use App\Filament\Clusters\Website\Resources\HomeHighlights\Schemas\HomeHighlightForm;
use App\Filament\Clusters\Website\Resources\HomeHighlights\Tables\HomeHighlightsTable;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\HomeHighlight;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The highlights in the moving strip under the home page's banners (OD-169), once published.
 */
class HomeHighlightResource extends Resource
{
    protected static ?string $model = HomeHighlight::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Highlights';

    protected static ?string $modelLabel = 'highlight';

    protected static ?string $pluralModelLabel = 'highlights';

    protected static ?int $navigationSort = 1;

    protected static bool $isGloballySearchable = false;

    /**
     * Hidden on a database not migrated for highlights (OD-169).
     */
    public static function canAccess(): bool
    {
        return HomeHighlight::isAvailable() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return HomeHighlightForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeHighlightsTable::configure($table);
    }

    /**
     * @param  HomeHighlight  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->text ?? $record?->openRevision()?->payload['text'] ?? 'New highlight';
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
            'index' => ListHomeHighlights::route('/'),
            'create' => CreateHomeHighlight::route('/create'),
            'edit' => EditHomeHighlight::route('/{record}/edit'),
        ];
    }
}
