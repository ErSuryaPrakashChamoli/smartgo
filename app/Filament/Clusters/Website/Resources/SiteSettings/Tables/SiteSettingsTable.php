<?php

namespace App\Filament\Clusters\Website\Resources\SiteSettings\Tables;

use App\Enums\Content\RevisionState;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The settings groups the user may open, with their live version and the version being worked on.
 */
class SiteSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                /** @var User $user */
                $user = auth()->user();

                return $query->whereIn('handle', SiteSetting::handlesVisibleTo($user))->with(['publishedRevision.publisher', 'workingRevision']);
            })
            ->defaultSort('id')
            ->columns([
                TextColumn::make('handle')
                    ->label('Settings')
                    ->weight('semibold')
                    ->formatStateUsing(fn (SiteSetting $record): string => $record->group()['name'])
                    ->description(fn (SiteSetting $record): string => $record->group()['purpose'])
                    ->wrap(),
                TextColumn::make('published_revision_id')
                    ->label('In use')
                    ->badge()
                    ->getStateUsing(fn (SiteSetting $record): string => $record->isPublished() ? 'Live: v'.$record->publishedRevision?->number : 'Not published')
                    ->color(fn (SiteSetting $record): string => $record->isPublished() ? 'success' : 'gray')
                    ->description(fn (SiteSetting $record): ?string => $record->publishedRevision?->publisher === null ? null : 'Published by '.$record->publishedRevision->publisher->name),
                TextColumn::make('workingRevision.state')
                    ->label('In progress')
                    ->badge()
                    ->formatStateUsing(fn (RevisionState $state, SiteSetting $record): string => 'v'.$record->workingRevision?->number.': '.$state->label())
                    ->color(fn (RevisionState $state): string => $state->color())
                    ->placeholder('Nothing in progress'),
                TextColumn::make('published_at')
                    ->label('Last published')
                    ->dateTime('j M Y, H:i', config('backoffice.timezone'))
                    ->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ])
            ->paginated(false);
    }
}
