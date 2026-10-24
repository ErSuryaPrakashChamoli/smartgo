<?php

namespace App\Filament\Clusters\Settings\Resources\Activities;

use App\Filament\Clusters\Settings\Resources\Activities\Pages\ManageActivities;
use App\Filament\Clusters\Settings\SettingsCluster;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit log viewer. Entries are written by the application and can never be edited
 * or deleted from the back office (SECURITY_MODEL §6).
 */
class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit log entry';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?string $recordTitleAttribute = 'description';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('When')
                    ->dateTime(),
                TextEntry::make('log_name')
                    ->label('Log'),
                TextEntry::make('event'),
                TextEntry::make('description'),
                TextEntry::make('causer.name')
                    ->label('By')
                    ->placeholder('System'),
                TextEntry::make('subject_type')
                    ->label('Record')
                    ->formatStateUsing(fn (Activity $record): string => class_basename($record->subject_type).' #'.$record->subject_id)
                    ->placeholder('—'),
                TextEntry::make('attribute_changes')
                    ->label('Changes')
                    ->state(fn (Activity $record): ?string => $record->attribute_changes?->isNotEmpty() ? $record->attribute_changes->toJson(JSON_PRETTY_PRINT) : null)
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('properties')
                    ->label('Details')
                    ->state(fn (Activity $record): ?string => $record->properties?->isNotEmpty() ? $record->properties->toJson(JSON_PRETTY_PRINT) : null)
                    ->fontFamily(FontFamily::Mono)
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge(),
                TextColumn::make('event')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Log')
                    ->options(fn (): array => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name', 'log_name')->filter()->all()),
                SelectFilter::make('event')
                    ->options(fn (): array => Activity::query()->distinct()->orderBy('event')->pluck('event', 'event')->filter()->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivities::route('/'),
        ];
    }
}
