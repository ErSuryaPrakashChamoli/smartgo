<?php

namespace App\Filament\Clusters\Website\Resources\MediaAssets\Tables;

use App\Enums\Content\MediaOrigin;
use App\Models\MediaAsset;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MediaAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['media', 'uploader']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('preview')
                    ->label('')
                    ->getStateUsing(fn (MediaAsset $record): string => $record->renditionPath('w480'))
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->weight('semibold')
                    ->description(fn (MediaAsset $record): string => $record->origin->label().($record->origin->isConcept() ? ' · Concept visual' : ''))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('alt_text')
                    ->label('Default alt text')
                    ->limit(50)
                    ->placeholder(fn (MediaAsset $record): string => $record->is_decorative ? 'Decorative' : 'Missing'),
                TextColumn::make('licence_expires_at')
                    ->label('Licence ends')
                    ->date('j M Y')
                    ->color(fn (MediaAsset $record): ?string => $record->isLicenceExpired() ? 'danger' : null)
                    ->description(fn (MediaAsset $record): ?string => $record->isLicenceExpired() ? 'Expired: hidden from the website' : null)
                    ->placeholder('No end date')
                    ->sortable(),
                TextColumn::make('uploader.name')->label('Added by'),
                TextColumn::make('created_at')->label('Added')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('origin')
                    ->options(collect(MediaOrigin::cases())->mapWithKeys(fn (MediaOrigin $origin): array => [$origin->value => $origin->label()])->all()),
                TernaryFilter::make('licence_expired')
                    ->label('Licence expired')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereDate('licence_expires_at', '<', today()),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->whereNull('licence_expires_at')->orWhereDate('licence_expires_at', '>=', today())),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ]);
    }
}
