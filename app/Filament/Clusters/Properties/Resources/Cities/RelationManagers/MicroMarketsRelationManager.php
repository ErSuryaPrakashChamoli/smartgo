<?php

namespace App\Filament\Clusters\Properties\Resources\Cities\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * The sectors of a city, such as "Sector 62", used to group its properties.
 */
class MicroMarketsRelationManager extends RelationManager
{
    protected static string $relationship = 'microMarkets';

    protected static ?string $title = 'Sectors';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('slug')->required()->maxLength(80)->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('city_id', $this->getOwnerRecord()->getKey())),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->weight('semibold'),
                TextColumn::make('properties_count')->counts('properties')->label('Properties'),
            ])
            ->headerActions([CreateAction::make()->label('New sector')])
            ->recordActions([EditAction::make()]);
    }
}
