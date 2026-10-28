<?php

namespace App\Filament\Clusters\Properties\Resources\PropertyProfiles\Tables;

use App\Enums\Content\RevisionState;
use App\Models\PropertyProfile;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PropertyProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'property.city', 'property.microMarket', 'publishedRevision', 'workingRevision.author', 'workingRevision.reviewer',
            ]))
            ->defaultSort('property_id')
            ->columns([
                TextColumn::make('property.name')
                    ->label('Property')
                    ->weight('semibold')
                    ->formatStateUsing(fn (string $state): string => 'SmartGro '.$state)
                    ->description(fn (PropertyProfile $record): string => collect([$record->property->microMarket?->name, $record->property->city->name])->filter()->join(', ')),
                TextColumn::make('status')
                    ->label('On the website')
                    ->badge()
                    ->formatStateUsing(fn (string $state, PropertyProfile $record): string => match (true) {
                        ! $record->property->isPubliclyListed() => 'Property not open',
                        $state === 'published' => 'Live: v'.$record->publishedRevision?->number,
                        default => 'Not published',
                    })
                    ->color(fn (string $state, PropertyProfile $record): string => $state === 'published' && $record->property->isPubliclyListed() ? 'success' : 'gray'),
                TextColumn::make('workingRevision.state')
                    ->label('In progress')
                    ->badge()
                    ->formatStateUsing(fn (RevisionState $state, PropertyProfile $record): string => 'v'.$record->workingRevision?->number.': '.$state->label())
                    ->color(fn (RevisionState $state): string => $state->color())
                    ->description(fn (PropertyProfile $record): ?string => match (true) {
                        $record->workingRevision === null => null,
                        $record->workingRevision->reviewer !== null => 'Approved by '.$record->workingRevision->reviewer->name,
                        default => 'Written by '.$record->workingRevision->author?->name,
                    })
                    ->placeholder('Nothing in progress'),
                TextColumn::make('published_at')
                    ->label('Last published')
                    ->dateTime('j M Y, H:i', config('backoffice.timezone'))
                    ->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ]);
    }
}
