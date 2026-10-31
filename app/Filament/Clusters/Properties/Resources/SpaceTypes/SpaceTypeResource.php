<?php

namespace App\Filament\Clusters\Properties\Resources\SpaceTypes;

use App\Enums\Inventory\InventoryMode;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\SpaceTypes\Pages\CreateSpaceType;
use App\Filament\Clusters\Properties\Resources\SpaceTypes\Pages\EditSpaceType;
use App\Filament\Clusters\Properties\Resources\SpaceTypes\Pages\ListSpaceTypes;
use App\Models\Product;
use App\Models\Space;
use App\Models\SpaceType;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The admin-managed space types (DATABASE_ERD §3.1) and the ways their spaces may be counted. A type
 * keeps every way its existing spaces are counted, and is deleted only while unused. Changes are
 * audited by the model. Hidden on a database without the inventory tables.
 */
class SpaceTypeResource extends Resource
{
    protected static ?string $model = SpaceType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Space types';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return SpaceType::isAvailable() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Space type')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(80),
                TextInput::make('key')->required()->maxLength(40)->regex('/^[a-z0-9_]+$/')->unique(ignoreRecord: true)
                    ->disabledOn('edit')->helperText('Permanent internal name, for example meeting_room.'),
                CheckboxList::make('allowed_inventory_modes')
                    ->label('Its spaces may be counted as')
                    ->options(InventoryMode::options())
                    ->required()
                    ->live()
                    ->rule(fn (?SpaceType $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $kept = $record === null ? collect() : Space::query()->where('space_type_id', $record->getKey())->whereNotIn('inventory_mode', is_array($value) ? $value : [])->pluck('code');

                        if ($kept->isNotEmpty()) {
                            $fail('These spaces of this type are counted in a way no longer ticked; change them first: '.$kept->implode(', ').'.');
                        }
                    }),
                Select::make('default_inventory_mode')
                    ->label('Suggested when adding a space')
                    ->options(fn (Get $get): array => collect(InventoryMode::options())->only($get('allowed_inventory_modes') ?? [])->all())
                    ->required()
                    ->in(fn (Get $get): array => $get('allowed_inventory_modes') ?? []),
                Toggle::make('is_bookable_by_time')->label('Booked by the hour or day')->inline(false)
                    ->helperText('Meeting and training rooms; used when bookings arrive (Phase 8).'),
                TextInput::make('sort_order')->label('Position')->integer()->minValue(0)->maxValue(9999)->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn (Builder $query): Builder => Product::hasSpaceTypes() ? $query->with('products') : $query)
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (SpaceType $record): string => $record->key),
                TextColumn::make('allowed_inventory_modes')->label('Counted as')
                    ->state(fn (SpaceType $record): string => collect($record->allowed_inventory_modes)->map(fn (string $mode): string => InventoryMode::from($mode)->label())->implode(', ')),
                TextColumn::make('sold_as')->label('Can be sold as')
                    ->state(fn (SpaceType $record): string => Product::hasSpaceTypes() ? ($record->products->sortBy('sort_order')->pluck('name')->implode(', ') ?: 'No category yet') : '')
                    ->visible(fn (): bool => Product::hasSpaceTypes()),
                TextColumn::make('spaces_count')->counts('spaces')->label('Spaces'),
            ])
            ->emptyStateHeading('No space types yet')
            ->emptyStateDescription('Space types are reference data, such as "Meeting room". Add one here, or load the proposed types with the reference data step. Which categories each type can be sold as is set on each workspace category.')
            ->recordActions([EditAction::make()->label('Open'), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpaceTypes::route('/'),
            'create' => CreateSpaceType::route('/create'),
            'edit' => EditSpaceType::route('/{record}/edit'),
        ];
    }
}
