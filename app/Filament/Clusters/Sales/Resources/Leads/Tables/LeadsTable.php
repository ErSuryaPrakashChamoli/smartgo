<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\Tables;

use App\Enums\Access\Capability;
use App\Enums\Crm\LeadActivityType;
use App\Enums\Crm\LeadStatus;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Property;
use App\Models\User;
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
 * sent, O-1).
 */
class LeadsTable
{
    public static function configure(Table $table): Table
    {
        $timezone = config('backoffice.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['property', 'product', 'city', 'owner'])
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
                    ->description(fn (Lead $record): ?string => $record->submitted_company),
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
}
