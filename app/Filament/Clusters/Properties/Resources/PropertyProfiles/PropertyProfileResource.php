<?php

namespace App\Filament\Clusters\Properties\Resources\PropertyProfiles;

use App\Enums\Access\Capability;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages\EditPropertyProfile;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Pages\ListPropertyProfiles;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Schemas\PropertyProfileForm;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Tables\PropertyProfilesTable;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Website profiles of properties (FR-CMS-6): headline, summary, directions and photos, changed through
 * draft, approval and publication. Each property has one, created with the property. A Property
 * Manager lists and edits only the profiles of their assigned properties.
 */
class PropertyProfileResource extends Resource
{
    protected static ?string $model = PropertyProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Website profiles';

    protected static ?string $modelLabel = 'website profile';

    protected static ?string $slug = 'website-profiles';

    protected static ?int $navigationSort = 3;

    protected static bool $isGloballySearchable = false;

    /**
     * @return Builder<PropertyProfile>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->whereHas('property', fn (Builder $query): Builder => $query->visibleTo($user, Capability::ViewProfiles));
    }

    public static function form(Schema $schema): Schema
    {
        return PropertyProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropertyProfilesTable::configure($table);
    }

    /**
     * @param  PropertyProfile  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return 'SmartGro '.($record?->property?->name ?? '');
    }

    /**
     * The profile's editor for a property, or null when the property has none.
     */
    public static function urlFor(Property $property): ?string
    {
        return $property->profile === null ? null : static::getUrl('edit', ['record' => $property->profile]);
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
            'index' => ListPropertyProfiles::route('/'),
            'edit' => EditPropertyProfile::route('/{record}/edit'),
        ];
    }
}
