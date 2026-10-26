<?php

namespace App\Filament\Clusters\Website\Tables;

use App\Enums\Content\RevisionState;
use Closure;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The list of a revisioned content type (posts, case studies, testimonials, FAQs): what is live and
 * what is in progress. An item that was never published is named from its draft.
 */
class RevisionedContentTable
{
    /**
     * @param  string  $nameField  the field that names an item
     * @param  Closure(Model): ?string|null  $description  shown under the name
     * @param  list<Column>  $extraColumns  shown after the name
     */
    public static function configure(Table $table, string $nameField, string $nameLabel, string $untitled, ?Closure $description = null, array $extraColumns = []): Table
    {
        $timezone = config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'publishedRevision.publisher', 'workingRevision.author', 'workingRevision.reviewer',
            ]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make($nameField)
                    ->label($nameLabel)
                    ->weight('semibold')
                    ->limit(80)
                    ->getStateUsing(fn (Model $record): string => $record->getAttribute($nameField) ?? $record->workingRevision?->payload[$nameField] ?? $untitled)
                    ->description($description)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->where($nameField, 'like', "%{$search}%")
                        ->orWhereHas('revisions', fn (Builder $query): Builder => $query
                            ->whereIn('state', RevisionState::open())
                            ->where("payload->{$nameField}", 'like', "%{$search}%"))))
                    ->sortable(),
                ...$extraColumns,
                TextColumn::make('status')
                    ->label('On the website')
                    ->badge()
                    ->formatStateUsing(fn (string $state, Model $record): string => $state === 'published' ? 'Live: v'.$record->publishedRevision?->number : 'Not published')
                    ->color(fn (string $state): string => $state === 'published' ? 'success' : 'gray')
                    ->description(fn (Model $record): ?string => $record->publishedRevision?->publisher === null ? null : 'Published by '.$record->publishedRevision->publisher->name),
                TextColumn::make('workingRevision.state')
                    ->label('In progress')
                    ->badge()
                    ->formatStateUsing(fn (RevisionState $state, Model $record): string => 'v'.$record->workingRevision?->number.': '.$state->label())
                    ->color(fn (RevisionState $state): string => $state->color())
                    ->description(fn (Model $record): ?string => match (true) {
                        $record->workingRevision === null => null,
                        $record->workingRevision->reviewer !== null => 'Approved by '.$record->workingRevision->reviewer->name,
                        default => 'Written by '.$record->workingRevision->author?->name,
                    })
                    ->placeholder('Nothing in progress'),
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
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
            ]);
    }
}
