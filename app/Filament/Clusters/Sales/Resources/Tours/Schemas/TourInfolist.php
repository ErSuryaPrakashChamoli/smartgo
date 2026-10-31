<?php

namespace App\Filament\Clusters\Sales\Resources\Tours\Schemas;

use App\Enums\Booking\TourStatus;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\TourRequest;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

/**
 * One tour: when and where, who is coming and from which enquiry, and its history from the audit log.
 */
class TourInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = (string) config('backoffice.timezone');

        return $schema
            ->columns(2)
            ->components([
                Section::make('Tour')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')->weight('semibold'),
                        TextEntry::make('status')->badge()->formatStateUsing(fn (TourStatus $state): string => $state->label())->color(fn (TourStatus $state): string => $state->color()),
                        TextEntry::make('when')->label('When')->state(fn (TourRequest $record): string => $record->whenLabel())->columnSpanFull(),
                        TextEntry::make('property.name')->label('Location')->formatStateUsing(fn (string $state): string => 'SmartGro '.$state),
                        TextEntry::make('visitor_count')->label('People'),
                        TextEntry::make('host.name')->label('Host')->placeholder('Not named'),
                        TextEntry::make('channel')->label('Arranged')->formatStateUsing(fn (string $state): string => $state === TourRequest::CHANNEL_WEBSITE ? 'Requested on the website' : 'By staff, with the visitor'),
                        TextEntry::make('confirmed_at')->label('Confirmed')->dateTime('j M Y, H:i T', $timezone)->placeholder('Not yet'),
                        TextEntry::make('cancellation_reason')->label('Why it was cancelled')->visible(fn (TourRequest $record): bool => filled($record->cancellation_reason)),
                        TextEntry::make('notes')->label('Visitor\'s notes')->placeholder('None')->columnSpanFull(),
                    ]),
                Section::make('Enquiry')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lead.reference')
                            ->label('Reference')
                            ->url(fn (TourRequest $record): ?string => Gate::allows('view', $record->lead) ? LeadResource::getUrl('view', ['record' => $record->lead]) : null),
                        TextEntry::make('lead.submitted_name')->label('Name'),
                        TextEntry::make('lead.submitted_company')->label('Company')->placeholder('—'),
                        TextEntry::make('lead.submitted_phone')->label('Phone')->placeholder('—')->copyable(),
                        TextEntry::make('lead.submitted_email')->label('Email')->placeholder('—')->copyable(),
                    ]),
                Section::make('History')
                    ->description('Who changed what, from the audit log.')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('history')
                            ->hiddenLabel()
                            ->state(fn (TourRequest $record): array => Activity::query()->inLog('tours')->whereMorphedTo('subject', $record)->with('causer')->latest('id')->get()
                                ->map(fn (Activity $entry): array => [
                                    'when' => $entry->created_at?->copy()->timezone($timezone)->format('j M Y, H:i'),
                                    'what' => $entry->description.(filled($entry->properties['reason'] ?? null) ? ': '.$entry->properties['reason'] : ''),
                                    'who' => $entry->causer?->name ?? 'The visitor',
                                ])->all())
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
}
