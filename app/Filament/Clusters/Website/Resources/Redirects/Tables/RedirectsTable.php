<?php

namespace App\Filament\Clusters\Website\Resources\Redirects\Tables;

use App\Enums\Content\RedirectMatch;
use App\Models\Redirect;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Every live redirect with where it came from and how often it is used.
 */
class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')
                    ->label('Old address')
                    ->weight('semibold')
                    ->description(fn (Redirect $record): ?string => $record->match_type === RedirectMatch::Prefix ? 'Every address starting with this' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('to_path')
                    ->label('Goes to')
                    ->placeholder('Gone (410)')
                    ->searchable(),
                TextColumn::make('status_code')
                    ->label('Answer')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        301 => '301 permanent',
                        302 => '302 temporary',
                        default => '410 gone',
                    })
                    ->color(fn (int $state): string => $state === 410 ? 'gray' : 'info'),
                TextColumn::make('source')
                    ->label('From')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Redirect::SOURCE_MANUAL ? 'Manual list' : 'Page address change')
                    ->color(fn (string $state): string => $state === Redirect::SOURCE_MANUAL ? 'warning' : 'gray'),
                TextColumn::make('hits')->label('Used')->numeric()->sortable(),
                TextColumn::make('last_hit_at')
                    ->label('Last used')
                    ->dateTime('j M Y, H:i', config('backoffice.timezone'))
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('From')
                    ->options([Redirect::SOURCE_MANUAL => 'Manual list', Redirect::SOURCE_AUTOMATIC => 'Page address change']),
                SelectFilter::make('status_code')
                    ->label('Answer')
                    ->options([301 => '301 permanent', 302 => '302 temporary', 410 => '410 gone']),
            ])
            ->paginated([25, 50, 100]);
    }
}
