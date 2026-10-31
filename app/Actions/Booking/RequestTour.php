<?php

namespace App\Actions\Booking;

use App\Enums\Booking\TourStatus;
use App\Enums\Crm\LeadStatus;
use App\Models\Lead;
use App\Models\Property;
use App\Models\TourRequest;
use App\Services\Booking\TourSlots;
use App\Services\Crm\VisitorIntake;
use App\Services\Website\EnquiryForm;
use App\Services\Website\TourForm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * A visitor's tour request from the website (FR-BK-2, the approved tour journey). The visitor picks a
 * free time; the place is claimed under the property's lock (ClaimTourSlot) and held until staff
 * confirm or cancel it, unless confirming at once is switched on (D-07, config tours.confirm_on_request).
 * The request belongs to an enquiry (BR-TR-3): the visitor's open enquiry about the same property, or a
 * new one with the source "tour form", their agreement and where they came from. Details are taken in
 * as for an enquiry (VisitorIntake). If the time was just taken, nothing is stored and the form keeps
 * what the visitor typed. A repeat of the same request within ten minutes returns the first one.
 * Requests for one property queue on its row, locked first (ADR-0004); the contact locks keep the same
 * visitor's requests apart, so the other reads are plain (locking reads would deadlock two visitors).
 */
class RequestTour
{
    public const int DUPLICATE_MINUTES = 10;

    public const int MAX_NOTES_LENGTH = 1000;

    public function __construct(
        private EnquiryForm $enquiryForm,
        private TourForm $tourForm,
        private TourSlots $slots,
        private VisitorIntake $intake,
        private ClaimTourSlot $claimTourSlot,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return TourRequest|null the request, or null when the submission is treated as spam (nothing stored)
     *
     * @throws ValidationException
     */
    public function handle(array $input, ?string $ipAddress): ?TourRequest
    {
        $notice = $this->enquiryForm->privacyNotice();

        if ($notice === null || ! $this->tourForm->isOpen()) {
            throw ValidationException::withMessages(['form' => 'Tour requests open once SmartGro publishes its privacy notice. Please use the contact details on this page.']);
        }

        $validated = Validator::make($this->intake->normalized($input), [
            'property' => ['required', 'integer'],
            'starts_at' => ['required', 'string', 'max:40'],
            'visitors' => ['required', 'integer', 'between:1,'.config('tours.max_visitors')],
            ...$this->intake->contactRules(),
            'notes' => ['nullable', 'string', 'max:'.self::MAX_NOTES_LENGTH, 'not_regex:/[<>]/'],
            'consent' => ['accepted'],
            'from' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)[^\s]*$/'],
            ...collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])->mapWithKeys(fn (string $key): array => [$key => $this->intake->plainText(false, 120)])->all(),
        ], [
            ...$this->intake->contactMessages(),
            'starts_at.required' => 'Choose a time for your visit.',
            'visitors.between' => 'Tours are for 1 to '.config('tours.max_visitors').' people.',
            'consent.accepted' => 'Tick the box so SmartGro may contact you about this tour.',
            'from.regex' => 'The form could not be read. Please send it again.',
        ], ['visitors' => 'number of people', 'starts_at' => 'time'])->validate();

        if ($this->intake->looksAutomated($input)) {
            return null;
        }

        $property = Property::query()->publiclyListed()->with('city')->find($validated['property']);

        if ($property === null || ! $this->tourForm->isOpenFor($property)) {
            throw ValidationException::withMessages(['property' => 'Choose a SmartGro location from the list.']);
        }

        $startsAt = $this->instant((string) $validated['starts_at']);
        $this->intake->limitPerContact($validated);

        return $this->intake->whileHoldingContactLocks($validated, fn (): TourRequest => DB::transaction(function () use ($validated, $property, $startsAt, $notice, $ipAddress): TourRequest {
            Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $contact = $this->intake->contact($validated);

            $duplicate = TourRequest::query()
                ->whereIn('lead_id', Lead::query()->where('contact_id', $contact->getKey())->select('id'))
                ->where('property_id', $property->getKey())->where('scheduled_start_at', $startsAt)
                ->where('created_at', '>=', now()->subMinutes(self::DUPLICATE_MINUTES))
                ->holdingPlace()->latest('id')->first();

            if ($duplicate !== null) {
                return $duplicate;
            }

            $consent = $this->intake->consentSnapshot($notice, $ipAddress);

            return $this->claimTourSlot->handle(
                $property,
                $startsAt,
                fn (): Lead => $this->enquiry($validated, $contact->getKey(), $property, $consent),
                config('tours.confirm_on_request') ? TourStatus::Confirmed : TourStatus::Requested,
                ['visitor_count' => (int) $validated['visitors'], 'notes' => $validated['notes'] ?? null, 'channel' => TourRequest::CHANNEL_WEBSITE, 'created_by' => null],
                null,
            );
        }));
    }

    /**
     * The visitor's open enquiry about this property, with the new agreement audited, or a new one.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $consent
     */
    private function enquiry(array $validated, int $contactId, Property $property, array $consent): Lead
    {
        $open = Lead::query()->where('contact_id', $contactId)->where('property_id', $property->getKey())
            ->whereNotIn('status', [LeadStatus::Lost, LeadStatus::Disqualified])
            ->latest('id')->first();

        if ($open !== null) {
            activity('leads')
                ->performedOn($open)
                ->event('lead_tour_requested')
                ->withProperties(['reference' => $open->reference, 'consent' => $consent])
                ->log('Tour requested on the website');

            return $open;
        }

        $lead = (new Lead)->forceFill([
            'reference' => $this->intake->reference('ENQ'),
            'contact_id' => $contactId,
            'source' => Lead::SOURCE_TOUR_FORM,
            'status' => LeadStatus::New,
            'submitted_name' => $validated['name'],
            'submitted_email' => $validated['email'] ?? null,
            'submitted_phone' => $validated['phone'] ?? null,
            'submitted_company' => $validated['company'] ?? null,
            'property_id' => $property->getKey(),
            'city_id' => $property->city_id,
            ...collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])->mapWithKeys(fn (string $key): array => [$key => $validated[$key] ?? null])->all(),
            'landing_path' => $validated['from'] ?? null,
            'consent_snapshot' => $consent,
        ]);
        $lead->save();

        return $lead;
    }

    /**
     * The chosen time as an instant; anything else is refused.
     *
     * @throws ValidationException
     */
    private function instant(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat(DATE_ATOM, $value)?->utc() ?? throw new InvalidArgumentException;
        } catch (Throwable) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a time for your visit.']);
        }
    }
}
