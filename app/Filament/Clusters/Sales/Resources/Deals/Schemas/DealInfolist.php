<?php

namespace App\Filament\Clusters\Sales\Resources\Deals\Schemas;

use App\Enums\Crm\PipelineOutcome;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\Deal;
use App\Models\PricePlan;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * One deal: its enquiry, stage, owner and the values staff typed, how it closed, and its history, which
 * includes the allocations its win recorded.
 */
class DealInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = config('backoffice.timezone');

        return $schema
            ->columns(2)
            ->components([
                Section::make('Deal')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('enquiry')
                            ->label('Enquiry')
                            ->state(fn (Deal $record): string => $record->lead->reference.': '.$record->lead->submitted_name)
                            ->url(fn (Deal $record): string => LeadResource::getUrl('view', ['record' => $record->lead]))
                            ->weight('semibold'),
                        TextEntry::make('about')->label('About')->state(fn (Deal $record): string => $record->lead->subject()),
                        TextEntry::make('stage')
                            ->label('Stage')
                            ->state(fn (Deal $record): string => $record->stage->displayName())
                            ->badge()
                            ->color(fn (Deal $record): string => $record->outcome()->color()),
                        TextEntry::make('outcome')->label('Open or closed')->state(fn (Deal $record): string => $record->outcome()->label()),
                        TextEntry::make('owner.name')->label('Owner')->placeholder('Nobody'),
                        TextEntry::make('opened')
                            ->label('Opened')
                            ->state(fn (Deal $record): string => $record->created_at->copy()->timezone($timezone)->format('j M Y, H:i T').' by '.($record->creator?->name ?? 'a former member of staff')),
                        TextEntry::make('seats')->placeholder('Not set'),
                        TextEntry::make('monthly_value_minor')->label('Monthly value')->formatStateUsing(fn (int $state): string => PricePlan::format($state))->placeholder('Not set'),
                        TextEntry::make('expected_close_on')->label('Expected close')->date('j M Y')->placeholder('Not set'),
                    ]),
                Section::make('Closed')
                    ->columns(2)
                    ->visible(fn (Deal $record): bool => ! $record->isOpen())
                    ->schema([
                        TextEntry::make('closed_at')
                            ->label(fn (Deal $record): string => $record->outcome() === PipelineOutcome::Won ? 'Won' : 'Lost')
                            ->state(fn (Deal $record): string => ($record->won_at ?? $record->lost_at)->copy()->timezone($timezone)->format('j M Y, H:i T')),
                        TextEntry::make('agreement_reference')->label('Agreement')->visible(fn (Deal $record): bool => filled($record->agreement_reference)),
                        TextEntry::make('lost_reason')->label('Reason')->visible(fn (Deal $record): bool => filled($record->lost_reason))->columnSpanFull(),
                    ]),
                Section::make('History')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('history')
                            ->hiddenLabel()
                            ->state(fn (Deal $record): array => self::history($record, (string) $timezone))
                            ->schema([
                                TextEntry::make('what')->hiddenLabel()->weight('semibold'),
                                TextEntry::make('who')->hiddenLabel()->color('gray'),
                                TextEntry::make('when')->hiddenLabel()->color('gray'),
                            ])
                            ->columns(3)
                            ->placeholder('No changes yet'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * The deal's audit entries and those of the allocations its win recorded, newest first.
     *
     * @return list<array{what: string, who: string, when: string|null}>
     */
    private static function history(Deal $deal, string $timezone): array
    {
        return Activity::query()
            ->with('causer')
            ->where(fn ($query) => $query
                ->where(fn ($subject) => $subject->inLog('leads')->whereMorphedTo('subject', $deal))
                ->orWhere(fn ($allocation) => $allocation->inLog('inventory')->where('event', 'allocation_added')->where('properties->deal_id', $deal->getKey())))
            ->latest('id')
            ->get()
            ->map(fn (Activity $entry): array => [
                'what' => $entry->description
                    .(filled($entry->properties['space'] ?? null) && $entry->log_name === 'inventory' ? ', '.$entry->properties['space'] : '')
                    .(filled($entry->properties['reason'] ?? null) ? ': '.$entry->properties['reason'] : ''),
                'who' => $entry->causer?->name ?? 'The system',
                'when' => $entry->created_at?->copy()->timezone($timezone)->format('j M Y, H:i'),
            ])
            ->values()
            ->all();
    }
}
