<?php

namespace App\Filament\Clusters\Website\Resources\NavigationMenus;

use App\Filament\Clusters\Website\Resources\NavigationMenus\Pages\EditNavigationMenu;
use App\Filament\Clusters\Website\Resources\NavigationMenus\Pages\ListNavigationMenus;
use App\Filament\Clusters\Website\Resources\NavigationMenus\Schemas\NavigationMenuForm;
use App\Filament\Clusters\Website\Resources\NavigationMenus\Tables\NavigationMenusTable;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\NavigationMenu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The website's menus (FR-CMS-4): header links, header buttons, footer columns and legal links. Every
 * change is a revision that goes through draft, approval and publication.
 */
class NavigationMenuResource extends Resource
{
    protected static ?string $model = NavigationMenu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Navigation';

    protected static string|UnitEnum|null $navigationGroup = 'Site setup';

    protected static ?string $modelLabel = 'menu';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return NavigationMenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NavigationMenusTable::configure($table);
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
            'index' => ListNavigationMenus::route('/'),
            'edit' => EditNavigationMenu::route('/{record}/edit'),
        ];
    }
}
