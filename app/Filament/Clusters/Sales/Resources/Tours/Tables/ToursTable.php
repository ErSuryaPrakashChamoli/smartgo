<?php

namespace App\Filament\Clusters\Sales\Resources\Tours\Tables;

use App\Enums\Access\Capability;
use App\Enums\Booking\TourStatus;
use App\Models\Property;
use App\Models\TourRequest;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The tour list, soonest first and grouped by day in India time (the day view of FR-BK-5 for tours),
 * showing tours still to come unless the filter asks for past ones. The query is already narrowed to the
 * user's scope (TourResource); the location filter offers only properties within it, and "Hosting" shows
 * the tours the user hosts.
 */
class ToursTable
{
    public static function configure(Table $table): Table
    {
        $timezone = (string) config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['property', 'lead', 'host']))
            ->defaultSort('scheduled_start_at')
            ->groups([
                Group::make('scheduled_start_at')
                    ->label('Day')
                    ->getKeyFromRecordUsing(fn (TourRequest $record): string => $record->scheduled_start_at->copy()->timezone($timezone)->toDateString())
                    ->getTitleFromRecordUsing(fn (TourRequest $record): string => $record->scheduled_start_at->copy()->timezone($timezone)->format('l j F Y'))
                    ->collapsible(),
            ])
            ->defaultGroup('scheduled_start_at')
            ->groupingSettingsHidden()
            ->columns([
                TextColumn::make('reference')
                    ->weight('semibold')
                    ->searchable()
                    ->description(fn (TourRequest $record): string => 'SmartGro '.$record->property->name),
                TextColumn::make('scheduled_start_at')
                    ->label('When (India time)')
                    ->dateTime('D j M Y, H:i', $timezone)
                    ->sortable(),
                TextColumn::make('lead.submitted_name')
                    ->label('Visitor')
                    ->searchable(['submitted_name', 'submitted_company'])
                    ->description(fn (TourRequest $record): string => $record->visitor_count.' '.str('person')->plural($record->visitor_count)),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TourStatus $state): string => $state->label())
                    ->color(fn (TourStatus $state): string => $state->color()),
                TextColumn::make('host.name')
                    ->label('Host')
                    ->placeholder('Not named'),
            ])
            ->filters([
                TernaryFilter::make('upcoming')
                    ->label('When')
                    ->placeholder('All')
                    ->trueLabel('Still to come')
                    ->falseLabel('Past')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('scheduled_start_at', '>', now()->subHours(2)),
                        false: fn (Builder $query): Builder => $query->where('scheduled_start_at', '<=', now()->subHours(2)),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('status')
                    ->multiple()
                    ->options(TourStatus::options()),
                TernaryFilter::make('hosting')
                    ->label('Hosting')
                    ->placeholder('Anyone')
                    ->trueLabel('Me')
                    ->falseLabel('No host named')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('host_user_id', auth()->id()),
                        false: fn (Builder $query): Builder => $query->whereNull('host_user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('property_id')
                    ->label('Location')
                    ->options(function (): array {
                        /** @var User $user */
                        $user = auth()->user();

                        return Property::query()->visibleTo($user, Capability::ViewTours)->orderBy('code')->pluck('name', 'id')->all();
                    }),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
            ])
            ->emptyStateHeading('No tours')
            ->emptyStateDescription('Tours arranged from an enquiry, and visitors\' requests once tour times are offered on the website, appear here.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }
}
