<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces;

use App\Enums\Inventory\InventoryMode;
use App\Enums\Inventory\SpaceStatus;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\Spaces\Pages\EditSpace;
use App\Filament\Clusters\Properties\Resources\Spaces\Pages\ListSpaces;
use App\Filament\Clusters\Properties\Resources\Spaces\Pages\ViewSpace;
use App\Filament\Clusters\Properties\Resources\Spaces\RelationManagers\AllocationsRelationManager;
use App\Filament\Clusters\Properties\Resources\Spaces\RelationManagers\SeatsRelationManager;
use App\Filament\Clusters\Properties\Resources\Spaces\Schemas\SpaceInfolist;
use App\Models\Property;
use App\Models\Space;
use App\Models\SpaceType;
use App\Models\User;
use App\Services\Inventory\SpaceAvailability;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every space the user may see, across properties, with today's availability (FR-INV-6). A space's
 * page has its details, seats and allocations; spaces are added on their property's page. Hidden on a
 * database without the inventory tables (the owner's server, OD-120).
 */
class SpaceResource extends Resource
{
    protected static ?string $model = Space::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Spaces';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'code';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return Space::isAvailable() && parent::canAccess();
    }

    /**
     * A Property Manager or restricted Sales/CRM Executive sees only their assigned properties' spaces.
     *
     * @return Builder<Space>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->visibleTo($user);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SpaceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['property', 'floor', 'spaceType', 'seats']))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->weight('semibold')->searchable()->sortable()->description(fn (Space $record): ?string => $record->name),
                TextColumn::make('property.name')->label('Property')->formatStateUsing(fn (string $state): string => 'SmartGro '.$state),
                TextColumn::make('floor.label')->label('Floor'),
                TextColumn::make('spaceType.name')->label('Type')->description(fn (Space $record): string => $record->inventory_mode->label()),
                TextColumn::make('operational_status')->label('Status')->badge()
                    ->formatStateUsing(fn (SpaceStatus $state): string => $state->label())
                    ->color(fn (SpaceStatus $state): string => $state->color()),
                TextColumn::make('today')->label('Today')
                    ->state(fn (Space $record): string => app(SpaceAvailability::class)->on($record)->summary($record->inventory_mode === InventoryMode::Exclusive)),
            ])
            ->filters([
                SelectFilter::make('property_id')->label('Property')
                    ->options(fn (): array => Property::query()->visibleTo(auth()->user())->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('space_type_id')->label('Type')->options(fn (): array => SpaceType::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                SelectFilter::make('operational_status')->label('Status')->options(SpaceStatus::options()),
            ])
            ->emptyStateHeading('No spaces recorded')
            ->emptyStateDescription('Spaces are added from real inventory on each property\'s page, after its floors.')
            ->recordActions([Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowRight)->url(fn (Space $record): string => self::getUrl('view', ['record' => $record]))]);
    }

    public static function getRelations(): array
    {
        return [
            SeatsRelationManager::class,
            AllocationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpaces::route('/'),
            'view' => ViewSpace::route('/{record}'),
            'edit' => EditSpace::route('/{record}/edit'),
        ];
    }
}
