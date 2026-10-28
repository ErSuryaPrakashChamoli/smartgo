<?php

namespace App\Filament\Clusters\Website\Resources\TwinModels\Tables;

use App\Enums\Twin\CaptureType;
use App\Models\TwinModel;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TwinModelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['property', 'uploader'])->withCount('anchors'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->weight('semibold')
                    ->description(fn (TwinModel $record): string => $record->capture_type->label())
                    ->searchable()
                    ->sortable(),
                TextColumn::make('property.name')->label('Property')->formatStateUsing(fn (string $state): string => 'SmartGro '.$state),
                TextColumn::make('file_bytes')->label('Size')->formatStateUsing(fn (int $state): string => number_format($state / 1024 / 1024, 1).' MB'),
                TextColumn::make('triangle_count')->label('Triangles')->numeric(),
                TextColumn::make('anchors_count')->label('Anchors'),
                TextColumn::make('uploader.name')->label('Uploaded by'),
                TextColumn::make('created_at')->label('Uploaded')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('capture_type')->label('How it was made')->options(CaptureType::options()),
                SelectFilter::make('property')->relationship('property', 'name'),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
            ]);
    }
}
