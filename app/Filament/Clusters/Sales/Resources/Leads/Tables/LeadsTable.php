<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\Tables;

use App\Enums\Access\Capability;
use App\Enums\Crm\LeadActivityType;
use App\Enums\Crm\LeadStatus;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
use App\Services\Crm\FirstResponseClock;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The enquiry inbox. The query is already narrowed to the user's scope (LeadResource); the property
 * filter offers only properties within that scope, and a crafted filter value cannot widen it. Each
 * enquiry shows its next open follow-up, and "Follow-up due" lists those due by the end of today in
 * India time, overdue ones included: the reminders of FR-CRM-3 inside the back office (no message is
 * sent, O-1). An enquiry from a contact who has sent others is marked as a repeat (FR-CRM-1, BR-CRM-2).
 * "Waiting" shows how long an enquiry without a first response has waited, and the filter lists them
 * (FR-CRM-1, P9-C); a due time, "overdue" and its filter appear only once the owner has set a target (A-41).
 */
class LeadsTable
{
    public static function configure(Table $table): Table
    {
        $timezone = config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['property', 'product', 'city', 'owner'])->withCount('sameContactLeads')
                ->withMin(['activities as next_follow_up_at' => fn (Builder $activities): Builder => $activities->where('type', LeadActivityType::FollowUp)->whereNull('completed_at')], 'due_at'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')
                    ->weight('semibold')
                    ->searchable()
                    ->description(fn (Lead $record): string => $record->subject()),
                TextColumn::make('submitted_name')
                    ->label('From')
                    ->searchable(['submitted_name', 'submitted_email', 'submitted_phone', 'submitted_company'])
                    ->description(fn (Lead $record): ?string => collect([
                        $record->submitted_company,
                        $record->same_contact_leads_count > 1 ? 'Repeat contact: '.($record->same_contact_leads_count - 1).' other '.str('enquiry')->plural($record->same_contact_leads_count - 1) : null,
                    ])->filter()->join(' · ') ?: null),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (LeadStatus $state): string => $state->label())
                    ->color(fn (LeadStatus $state): string => $state->color()),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned'),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since($timezone)
                    ->dateTimeTooltip('j M Y, H:i', $timezone)
                    ->sortable(),
                TextColumn::make('next_follow_up_at')
                    ->label('Next follow-up')
                    ->dateTime('j M, H:i', $timezone)
                    ->color(fn (Lead $record): ?string => $record->next_follow_up_at !== null && now()->greaterThan($record->next_follow_up_at) ? 'danger' : null)
                    ->description(fn (Lead $record): ?string => $record->next_follow_up_at !== null && now()->greaterThan($record->next_follow_up_at) ? 'Overdue' : null)
                    ->placeholder('None')
                    ->toggleable(),
                TextColumn::make('first_responded_at')
                    ->label('First response')
                    ->since($timezone)
                    ->placeholder('Not yet')
                    ->description(fn (Lead $record): ?string => $record->first_responded_at === null ? null : 'after '.app(FirstResponseClock::class)->describe(app(FirstResponseClock::class)->waitedMinutes($record))
                        .(app(FirstResponseClock::class)->respondedLate($record) ? ', after its target' : ''))
                    ->toggleable(),
                TextColumn::make('waiting')
                    ->label('Waiting')
                    ->state(fn (Lead $record): ?string => $record->first_responded_at === null ? app(FirstResponseClock::class)->describe(app(FirstResponseClock::class)->waitedMinutes($record)) : null)
                    ->placeholder('—')
                    ->description(fn (Lead $record): ?string => self::dueNote($record))
                    ->color(fn (Lead $record): ?string => app(FirstResponseClock::class)->isOverdue($record) ? 'danger' : null)
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->options(LeadStatus::options()),
                SelectFilter::make('property_id')
                    ->label('Location')
                    ->options(function (): array {
                        /** @var User $user */
                        $user = auth()->user();

                        return Property::query()->visibleTo($user, Capability::ViewLeads)->orderBy('code')->pluck('name', 'id')->all();
                    }),
                SelectFilter::make('product_id')
                    ->label('Workspace type')
                    ->options(fn (): array => Product::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                Filter::make('awaiting_first_response')
                    ->label('Waiting for a first response')
                    ->query(fn (Builder $query): Builder => $query->whereNull('first_responded_at')),
                Filter::make('first_response_overdue')
                    ->label('First response overdue')
                    ->visible(fn (): bool => app(FirstResponseClock::class)->hasTarget())
                    ->query(function (Builder $query): Builder {
                        /** @var User $user */
                        $user = auth()->user();

                        return $query->whereKey(app(FirstResponseClock::class)->overdueLeadIds($user));
                    }),
                Filter::make('follow_up_due')
                    ->label('Follow-up due by today')
                    ->query(fn (Builder $query): Builder => $query->whereHas('activities', fn (Builder $activities): Builder => $activities
                        ->where('type', LeadActivityType::FollowUp)->whereNull('completed_at')
                        ->where('due_at', '<', now(config('backoffice.timezone'))->addDay()->startOfDay()->utc()))),
                TernaryFilter::make('mine')
                    ->label('Owner')
                    ->placeholder('Anyone')
                    ->trueLabel('Mine')
                    ->falseLabel('Unassigned')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('owner_user_id', auth()->id()),
                        false: fn (Builder $query): Builder => $query->whereNull('owner_user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()->label('Open'),
            ])
            ->emptyStateHeading('No enquiries')
            ->emptyStateDescription('Enquiries sent from the website about the locations you work on appear here.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    /**
     * "Due by 10:45" or "Overdue since 10:45" once the owner has set a target (A-41); nothing before.
     */
    private static function dueNote(Lead $record): ?string
    {
        $clock = app(FirstResponseClock::class);
        $due = $record->first_responded_at === null ? $clock->dueAt($record) : null;

        if ($due === null) {
            return null;
        }

        return ($clock->isOverdue($record) ? 'Overdue since ' : 'Due by ').$due->setTimezone(config('backoffice.timezone'))->format('j M, H:i');
    }
}
