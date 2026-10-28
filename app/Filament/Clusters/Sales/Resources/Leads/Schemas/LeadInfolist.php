<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\Schemas;

use App\Enums\Crm\LeadStatus;
use App\Models\Lead;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Activitylog\Models\Activity;

/**
 * One enquiry: what was asked, by whom, the agreement recorded, where it came from and its history.
 */
class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $timezone = config('backoffice.timezone');

        return $schema
            ->columns(2)
            ->components([
                Section::make('Enquiry')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')->weight('semibold'),
                        TextEntry::make('status')->badge()->formatStateUsing(fn (LeadStatus $state): string => $state->label())->color(fn (LeadStatus $state): string => $state->color()),
                        TextEntry::make('subject')->label('About')->state(fn (Lead $record): string => $record->subject())->columnSpanFull(),
                        TextEntry::make('owner.name')->label('Owner')->placeholder('Unassigned'),
                        TextEntry::make('created_at')->label('Received')->dateTime('j M Y, H:i T', $timezone),
                        TextEntry::make('first_responded_at')->label('First response')->dateTime('j M Y, H:i T', $timezone)->placeholder('Not yet'),
                        TextEntry::make('closeReason.label')->label('Reason')->visible(fn (Lead $record): bool => $record->close_reason_id !== null),
                        TextEntry::make('closed_reason')->label('Details')->visible(fn (Lead $record): bool => filled($record->closed_reason)),
                        TextEntry::make('message')->placeholder('No message')->columnSpanFull(),
                    ]),
                Section::make('Visitor')
                    ->description('As typed on the form. Earlier enquiries from the same email or phone are linked to the same contact.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('submitted_name')->label('Name'),
                        TextEntry::make('submitted_company')->label('Company')->placeholder('—'),
                        TextEntry::make('submitted_email')->label('Email')->placeholder('—')->copyable(),
                        TextEntry::make('submitted_phone')->label('Phone')->placeholder('—')->copyable(),
                        TextEntry::make('team_size')->label('Team size')->placeholder('—'),
                        TextEntry::make('segment.name')->label('Describes themselves as')->placeholder('—'),
                        TextEntry::make('other_enquiries')
                            ->label('Other enquiries from this contact')
                            ->state(fn (Lead $record): int => Lead::query()->where('contact_id', $record->contact_id)->whereKeyNot($record->getKey())->count())
                            ->columnSpanFull(),
                    ]),
                Section::make('Agreement and source')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('consent_text')->label('Agreed to')->state(fn (Lead $record): ?string => $record->consent_snapshot['text'] ?? null)->columnSpanFull(),
                        TextEntry::make('consent_notice')->label('Privacy notice shown')->state(fn (Lead $record): string => ($record->consent_snapshot['notice_path'] ?? '—').' (version '.($record->consent_snapshot['notice_revision'] ?? '—').')'),
                        TextEntry::make('consent_marketing')->label('Marketing messages')->state(fn (Lead $record): string => ($record->consent_snapshot['marketing'] ?? false) ? 'Agreed' : 'Not asked, not agreed'),
                        TextEntry::make('source')->formatStateUsing(fn (string $state): string => $state === Lead::SOURCE_CITY_WAITLIST ? 'City waitlist' : 'Website form'),
                        TextEntry::make('landing_path')->label('Came from')->placeholder('—'),
                        TextEntry::make('utm')->label('Campaign')->state(fn (Lead $record): ?string => collect([$record->utm_source, $record->utm_medium, $record->utm_campaign])->filter()->join(' · ') ?: null)->placeholder('—'),
                    ]),
                Section::make('History')
                    ->description('Who changed what, from the audit log.')
                    ->collapsible()
                    ->schema([
                        RepeatableEntry::make('history')
                            ->hiddenLabel()
                            ->state(fn (Lead $record): array => Activity::query()->inLog('leads')->whereMorphedTo('subject', $record)->with('causer')->latest('id')->get()
                                ->map(fn (Activity $entry): array => [
                                    'when' => $entry->created_at?->copy()->timezone(config('backoffice.timezone'))->format('j M Y, H:i'),
                                    'what' => $entry->description,
                                    'who' => $entry->causer?->name ?? 'System',
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
