<?php

namespace App\Filament\Clusters\Properties\Resources\Properties;

use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\CreateProperty;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\EditProperty;
use App\Filament\Clusters\Properties\Resources\Properties\Pages\ListProperties;
use App\Filament\Clusters\Properties\Resources\Properties\Schemas\PropertyForm;
use App\Filament\Clusters\Properties\Resources\Properties\Tables\PropertiesTable;
use App\Models\Property;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Properties with their address, status, operating model and the workspace categories they offer
 * (OD-08, SECURITY_MODEL §3.1). Open properties appear on the website.
 */
class PropertyResource extends Resource
{
    protected static ?string $model = Property::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Properties';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    /**
     * A Property Manager lists only their assigned properties.
     *
     * @return Builder<Property>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->visibleTo($user);
    }

    public static function form(Schema $schema): Schema
    {
        return PropertyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertiesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'edit' => EditProperty::route('/{record}/edit'),
        ];
    }
}
