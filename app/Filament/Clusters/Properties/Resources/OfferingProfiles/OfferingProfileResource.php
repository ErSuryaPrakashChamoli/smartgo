<?php

namespace App\Filament\Clusters\Properties\Resources\OfferingProfiles;

use App\Enums\Access\Capability;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\OfferingProfiles\Pages\EditOfferingProfile;
use App\Filament\Clusters\Properties\Resources\OfferingProfiles\Pages\ListOfferingProfiles;
use App\Filament\Clusters\Properties\Resources\OfferingProfiles\Schemas\OfferingProfileForm;
use App\Filament\Clusters\Properties\Resources\OfferingProfiles\Tables\OfferingProfilesTable;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Models\OfferingProfile;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Website profiles of workspace offerings (FR-CMS-6): one per workspace category that operations staff
 * record at a property, written, approved and published like other content. A Property Manager lists
 * and edits only those of their assigned properties.
 */
class OfferingProfileResource extends Resource
{
    protected static ?string $model = OfferingProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Workspace offerings';

    protected static ?string $modelLabel = 'workspace offering';

    protected static ?string $slug = 'workspace-offerings';

    protected static ?int $navigationSort = 4;

    protected static bool $isGloballySearchable = false;

    /**
     * @return Builder<OfferingProfile>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->whereHas('offering.property', fn (Builder $query): Builder => $query->visibleTo($user, Capability::ViewProfiles));
    }

    public static function form(Schema $schema): Schema
    {
        return OfferingProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfferingProfilesTable::configure($table);
    }

    /**
     * @param  OfferingProfile  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->offering?->displayName() ?? '';
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
            'index' => ListOfferingProfiles::route('/'),
            'edit' => EditOfferingProfile::route('/{record}/edit'),
        ];
    }
}
