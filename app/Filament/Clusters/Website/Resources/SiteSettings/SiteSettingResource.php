<?php

namespace App\Filament\Clusters\Website\Resources\SiteSettings;

use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Filament\Clusters\Website\Resources\SiteSettings\Pages\ListSiteSettings;
use App\Filament\Clusters\Website\Resources\SiteSettings\Schemas\SiteSettingForm;
use App\Filament\Clusters\Website\Resources\SiteSettings\Tables\SiteSettingsTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Public settings (FR-CMS-8, BR-CMS-9): contact details, social links, tracking IDs and legal details,
 * each changed through draft, approval and publication with its own permissions.
 */
class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Settings';

    protected static string|UnitEnum|null $navigationGroup = 'Site setup';

    protected static ?string $modelLabel = 'settings group';

    protected static ?string $pluralModelLabel = 'settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 8;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return SiteSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteSettingsTable::configure($table);
    }

    /**
     * @param  SiteSetting  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->group()['name'] ?? 'Settings';
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
            'index' => ListSiteSettings::route('/'),
            'edit' => EditSiteSetting::route('/{record}/edit'),
        ];
    }
}
