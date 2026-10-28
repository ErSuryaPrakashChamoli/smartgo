<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\Tables;

use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use App\Models\Property;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PropertiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['city', 'microMarket', 'primaryBuilding', 'profile'])->withCount('activeProducts'))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('name')
                    ->label('Property')
                    ->weight('semibold')
                    ->description(fn (Property $record): string => $record->addressLine())
                    ->searchable(['name', 'code'])
                    ->sortable(),
                TextColumn::make('operational_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (PropertyStatus $state): string => $state->label())
                    ->color(fn (PropertyStatus $state): string => $state->color())
                    ->description(fn (Property $record): string => match (true) {
                        $record->isPubliclyListed() => 'On the website',
                        $record->profile?->isPublished() !== true => 'Not on the website: profile not published',
                        default => 'Not on the website: state does not allow it',
                    }),
                TextColumn::make('operating_model')
                    ->label('Operating model')
                    ->formatStateUsing(fn (OperatingModel $state): string => $state->label()),
                TextColumn::make('active_products_count')
                    ->label('Categories offered')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'None recorded' : (string) $state),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('operational_status')
                    ->label('Status')
                    ->options(collect(PropertyStatus::cases())->mapWithKeys(fn (PropertyStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('city_id')->label('City')->relationship('city', 'name'),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ]);
    }
}
