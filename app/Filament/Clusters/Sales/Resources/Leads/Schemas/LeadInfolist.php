<?php

namespace App\Filament\Clusters\Sales\Resources\Leads\Schemas;

use App\Enums\Crm\AssignmentOutcome;
use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Sales\Resources\Leads\LeadResource;
use App\Models\AssignmentRule;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\User;
use App\Services\Crm\FirstResponseClock;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * One enquiry: what was asked, by whom, the agreement recorded, where it came from and its history.
 * Duplicates (FR-CRM-1, BR-CRM-2): the contact's other enquiries the user may see, linked, and how many
 * others exist; details typed here that the contact does not have; and other contacts with the same
 * email or phone, which may be the same visitor.
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
                        TextEntry::make('assignment_decision')
                            ->label('Assignment rules')
                            ->state(fn (Lead $record): string => self::assignmentExplanation($record))
                            ->visible(fn (): bool => AssignmentRule::isAvailable())
                            ->columnSpanFull(),
                        TextEntry::make('created_at')->label('Received')->dateTime('j M Y, H:i T', $timezone),
                        TextEntry::make('first_response')
                            ->label('First response')
                            ->state(fn (Lead $record): string => self::firstResponse($record))
                            ->color(fn (Lead $record): ?string => app(FirstResponseClock::class)->isOverdue($record) ? 'danger' : null),
                        TextEntry::make('closeReason.label')->label('Reason')->visible(fn (Lead $record): bool => $record->close_reason_id !== null),
                        TextEntry::make('closed_reason')->label('Details')->visible(fn (Lead $record): bool => filled($record->closed_reason)),
                        TextEntry::make('message')->placeholder('No message')->columnSpanFull(),
                    ]),
                Section::make('Visitor')
                    ->description('As typed on the form. Enquiries from the same email or phone share one contact, which a later enquiry never changes by itself.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('submitted_name')->label('Name'),
                        TextEntry::make('submitted_company')->label('Company')->placeholder('—'),
                        TextEntry::make('submitted_email')->label('Email')->placeholder('—')->copyable(),
                        TextEntry::make('submitted_phone')->label('Phone')->placeholder('—')->copyable(),
                        TextEntry::make('team_size')->label('Team size')->placeholder('—'),
                        TextEntry::make('segment.name')->label('Describes themselves as')->placeholder('—'),
                        TextEntry::make('contact_differences')
                            ->label('Not on the contact record yet')
                            ->state(fn (Lead $record): array => collect($record->contactDifferences())
                                ->map(fn (array $difference): string => $difference['label'].': '.$difference['submitted'].' (the contact has '.($difference['contact'] ?? 'none').')')
                                ->values()->all())
                            ->listWithLineBreaks()
                            ->visible(fn (Lead $record): bool => $record->contactDifferences() !== [])
                            ->columnSpanFull(),
                        TextEntry::make('other_contacts')
                            ->label('Possibly the same visitor')
                            ->state(fn (Lead $record): array => $record->otherContactsWithSameDetails()->loadCount('leads')
                                ->map(fn (Contact $contact): string => 'Another contact has the same '.collect(['email' => $contact->email === $record->submitted_email, 'phone' => $contact->phone_e164 === $record->submitted_phone])->filter()->keys()->join(' and ').' ('.$contact->leads_count.' '.str('enquiry')->plural($contact->leads_count).')')
                                ->all())
                            ->listWithLineBreaks()
                            ->color('warning')
                            ->visible(fn (Lead $record): bool => $record->otherContactsWithSameDetails()->isNotEmpty())
                            ->columnSpanFull(),
                        RepeatableEntry::make('other_enquiries')
                            ->label('Other enquiries from this contact')
                            ->state(fn (Lead $record): Collection => self::otherEnquiries($record))
                            ->schema([
                                TextEntry::make('reference')->hiddenLabel()->weight('semibold')->url(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record])),
                                TextEntry::make('subject')->hiddenLabel()->state(fn (Lead $record): string => $record->subject()),
                                TextEntry::make('status')->hiddenLabel()->badge()->formatStateUsing(fn (LeadStatus $state): string => $state->label())->color(fn (LeadStatus $state): string => $state->color()),
                                TextEntry::make('created_at')->hiddenLabel()->color('gray')->dateTime('j M Y', $timezone),
                            ])
                            ->columns(4)
                            ->placeholder('None')
                            ->columnSpanFull(),
                        TextEntry::make('other_enquiries_elsewhere')
                            ->hiddenLabel()
                            ->color('gray')
                            ->state(fn (Lead $record): string => ($count = self::otherEnquiriesCount($record) - self::otherEnquiries($record)->count()).' more about '.str('location')->plural($count).' you do not work on.')
                            ->visible(fn (Lead $record): bool => self::otherEnquiriesCount($record) > self::otherEnquiries($record)->count())
                            ->columnSpanFull(),
                    ]),
                Section::make('Agreement and source')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('consent_text')->label('Agreed to')->state(fn (Lead $record): ?string => $record->consent_snapshot['text'] ?? null)->columnSpanFull(),
                        TextEntry::make('consent_notice')->label('Privacy notice shown')->state(fn (Lead $record): string => ($record->consent_snapshot['notice_path'] ?? '—').' (version '.($record->consent_snapshot['notice_revision'] ?? '—').')'),
                        TextEntry::make('consent_marketing')->label('Marketing messages')->state(fn (Lead $record): string => ($record->consent_snapshot['marketing'] ?? false) ? 'Agreed' : 'Not asked, not agreed'),
                        TextEntry::make('source')->formatStateUsing(fn (string $state): string => Lead::SOURCES[$state] ?? Lead::SOURCES[Lead::SOURCE_WEBSITE_FORM]),
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

    /**
     * The first response and how long it took, or how long the enquiry has waited (FR-CRM-1, UJ-10 "SLA
     * visible"); with the due time and whether it is overdue once the owner has set a target (A-41).
     */
    private static function firstResponse(Lead $record): string
    {
        $clock = app(FirstResponseClock::class);
        $timezone = config('backoffice.timezone');
        $waited = $clock->describe($clock->waitedMinutes($record));

        if ($record->first_responded_at !== null) {
            $late = $clock->respondedLate($record);

            return $record->first_responded_at->setTimezone($timezone)->format('j M Y, H:i T').', after '.$waited
                .match ($late) {
                    true => ', after its target', false => ', within its target', null => ''
                }.'.';
        }

        $text = 'Not yet: waiting '.$waited.' since it arrived.';

        if (! $clock->hasTarget()) {
            return $text;
        }

        $due = $clock->dueAt($record);

        return match (true) {
            $due === null && $record->property === null => $text.' No due time: an enquiry without a location has no opening hours to count.',
            $due === null => $text.' No due time: the location has no opening hours recorded.',
            $clock->isOverdue($record) => $text.' Overdue since '.$due->setTimezone($timezone)->format('j M, H:i').'.',
            default => $text.' Due by '.$due->setTimezone($timezone)->format('j M, H:i').'.',
        };
    }

    /**
     * Why the enquiry has, or has not, an owner from the assignment rules (FR-CRM-2): the rule's name and
     * how it picks, never its other people or settings.
     */
    private static function assignmentExplanation(Lead $record): string
    {
        $decision = $record->assignment()->with(['rule', 'assignedUser'])->first();
        $rule = $decision?->rule === null ? null : '"'.$decision->rule->name.'" ('.strtolower($decision->rule->strategy->label()).')';

        return match ($decision?->outcome) {
            null => 'The rules have not run for this enquiry: it arrived before they existed, or they could not run.',
            AssignmentOutcome::Assigned => 'Given to '.($decision->assignedUser->name ?? 'a former member of staff').' by the rule '.$rule.'.',
            AssignmentOutcome::NoRuleMatched => 'No active rule matched, so it went to the unassigned queue.',
            AssignmentOutcome::NoEligibleAssignee => 'The rule '.$rule.' matched, but none of its people could take it (deactivated, or not working on this location), so it stays unassigned.',
            AssignmentOutcome::StrategyUnavailable => 'The rule '.$rule.' matched, but a least-loaded rule cannot assign until workload is defined, so it stays unassigned.',
            AssignmentOutcome::AlreadyOwned => 'It already had an owner when the rules ran.',
        };
    }

    /**
     * The contact's other enquiries the signed-in user may see (BR-CRM-6), newest first.
     *
     * @return Collection<int, Lead>
     */
    private static function otherEnquiries(Lead $record): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return once(fn (): Collection => Lead::query()->visibleTo($user)->with(['property', 'product', 'offering'])
            ->where('contact_id', $record->contact_id)->whereKeyNot($record->getKey())->latest('id')->get());
    }

    private static function otherEnquiriesCount(Lead $record): int
    {
        return once(fn (): int => Lead::query()->where('contact_id', $record->contact_id)->whereKeyNot($record->getKey())->count());
    }
}
