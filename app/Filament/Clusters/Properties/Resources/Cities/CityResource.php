<?php

namespace App\Filament\Clusters\Properties\Resources\Cities;

use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\Cities\Pages\CreateCity;
use App\Filament\Clusters\Properties\Resources\Cities\Pages\EditCity;
use App\Filament\Clusters\Properties\Resources\Cities\Pages\ListCities;
use App\Filament\Clusters\Properties\Resources\Cities\RelationManagers\MicroMarketsRelationManager;
use App\Models\City;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Market cities and their sectors (OD-03, OD-16, OD-18). A city with an open property is operating and
 * shown; any other city is shown only when switched visible, as "coming soon" (OD-22).
 */
class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('City')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(60)->helperText('The current official name (OD-16).'),
                TextInput::make('slug')->label('Website address')->prefix(url('/locations').'/')->required()->maxLength(60)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->notIn(City::RESERVED_SLUGS)->unique(ignoreRecord: true),
                TextInput::make('state')->maxLength(60),
                TagsInput::make('aliases')->helperText('Old names that redirect here, for example gurgaon.'),
                Toggle::make('is_target_market')->label('Target market')->inline(false),
                Toggle::make('is_publicly_visible')
                    ->label('Show on the website before a property opens')
                    ->helperText('A city with an open property is always shown. Otherwise, when on, it shows as coming soon (OD-22).')
                    ->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['properties as open_properties_count' => fn (Builder $query) => $query->where('operational_status', 'open')]))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (City $record): ?string => $record->state)->searchable(),
                TextColumn::make('open_properties_count')
                    ->label('Open properties')
                    ->description(fn (City $record): string => $record->open_properties_count > 0 ? 'Operating: shown' : ($record->is_publicly_visible ? 'Shown as coming soon' : 'Hidden')),
                IconColumn::make('is_target_market')->label('Target market')->boolean(),
                IconColumn::make('is_publicly_visible')->label('Visible before opening')->boolean(),
            ])
            ->recordActions([EditAction::make()->label('Open')]);
    }

    public static function getRelations(): array
    {
        return [MicroMarketsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCities::route('/'),
            'create' => CreateCity::route('/create'),
            'edit' => EditCity::route('/{record}/edit'),
        ];
    }
}
