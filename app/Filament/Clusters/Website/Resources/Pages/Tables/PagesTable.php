<?php

namespace App\Filament\Clusters\Website\Resources\Pages\Tables;

use App\Enums\Content\RevisionState;
use App\Models\Page;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pages with their live version and the version being worked on. Titles and addresses of pages that
 * were never published come from their draft.
 */
class PagesTable
{
    public static function configure(Table $table): Table
    {
        $timezone = config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'publishedRevision.author', 'publishedRevision.reviewer', 'publishedRevision.publisher',
                'workingRevision.author', 'workingRevision.reviewer', 'workingRevision.scheduler',
            ]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Page')
                    ->weight('semibold')
                    ->getStateUsing(fn (Page $record): string => $record->title ?? $record->workingRevision?->payload['title'] ?? 'Untitled page')
                    ->description(fn (Page $record): string => $record->path() ?? '/'.($record->workingRevision?->payload['slug'] ?? '…').' (not published)')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('revisions', fn (Builder $query): Builder => $query
                            ->whereIn('state', RevisionState::open())
                            ->where(fn (Builder $query): Builder => $query
                                ->where('payload->title', 'like', "%{$search}%")
                                ->orWhere('payload->slug', 'like', "%{$search}%")))))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('On the website')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Page $record): string => $state === 'published' ? 'Live: v'.$record->publishedRevision?->number : 'Not published')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray')
                    ->description(fn (Page $record): ?string => $record->publishedRevision?->publisher === null ? null : 'Published by '.$record->publishedRevision->publisher->name),
                TextColumn::make('workingRevision.state')
                    ->label('In progress')
                    ->badge()
                    ->formatStateUsing(fn (RevisionState $state, Page $record): string => 'v'.$record->workingRevision?->number.': '.$state->label())
                    ->color(fn (RevisionState $state): string => $state->color())
                    ->description(fn (Page $record): ?string => match (true) {
                        $record->workingRevision === null => null,
                        $record->workingRevision->reviewer !== null => 'Approved by '.$record->workingRevision->reviewer->name,
                        default => 'Written by '.$record->workingRevision->author?->name,
                    })
                    ->placeholder('Nothing in progress'),
                TextColumn::make('workingRevision.scheduled_for')
                    ->label('Scheduled for')
                    ->dateTime('j M Y, H:i', $timezone)
                    ->description(fn (Page $record): ?string => $record->workingRevision?->scheduler === null ? null : 'by '.$record->workingRevision->scheduler->name)
                    ->placeholder('—'),
                TextColumn::make('published_at')
                    ->label('Last published')
                    ->dateTime('j M Y, H:i', $timezone)
                    ->sortable()
                    ->placeholder('Never'),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('On the website')
                    ->options(['published' => 'Live', 'draft' => 'Not published']),
                SelectFilter::make('workflow')
                    ->label('In progress')
                    ->options(collect(RevisionState::open())->mapWithKeys(fn (RevisionState $state): array => [$state->value => $state->label()])->all())
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'] ?? null)
                        ? $query
                        : $query->whereHas('revisions', fn (Builder $query): Builder => $query->where('state', $data['value']))),
                TernaryFilter::make('scheduled')
                    ->label('Scheduled')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('revisions', fn (Builder $query): Builder => $query->where('state', RevisionState::Approved)->whereNotNull('scheduled_for')),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('revisions', fn (Builder $query): Builder => $query->where('state', RevisionState::Approved)->whereNotNull('scheduled_for')),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ]);
    }
}
