<?php

namespace App\Filament\Clusters\Website\Resources\HomeBanners;

use App\Filament\Clusters\Website\Resources\HomeBanners\Pages\CreateHomeBanner;
use App\Filament\Clusters\Website\Resources\HomeBanners\Pages\EditHomeBanner;
use App\Filament\Clusters\Website\Resources\HomeBanners\Pages\ListHomeBanners;
use App\Filament\Clusters\Website\Resources\HomeBanners\Schemas\HomeBannerForm;
use App\Filament\Clusters\Website\Resources\HomeBanners\Tables\HomeBannersTable;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\HomeBanner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The home page's banners (OD-169): the slides of the carousel at the top of the home page, once published.
 */
class HomeBannerResource extends Resource
{
    protected static ?string $model = HomeBanner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Home banners';

    protected static ?string $modelLabel = 'home banner';

    protected static ?string $pluralModelLabel = 'home banners';

    protected static ?int $navigationSort = 1;

    protected static bool $isGloballySearchable = false;

    /**
     * Hidden on a database not migrated for banners (OD-169).
     */
    public static function canAccess(): bool
    {
        return HomeBanner::isAvailable() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return HomeBannerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeBannersTable::configure($table);
    }

    /**
     * @param  HomeBanner  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->heading ?? $record?->openRevision()?->payload['heading'] ?? 'New home banner';
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
            'index' => ListHomeBanners::route('/'),
            'create' => CreateHomeBanner::route('/create'),
            'edit' => EditHomeBanner::route('/{record}/edit'),
        ];
    }
}
