<?php

namespace App\Actions\Booking;

use App\Enums\Access\Capability;
use App\Enums\Booking\TourStatus;
use App\Models\Lead;
use App\Models\Property;
use App\Models\TourRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A tour staff arrange with an enquirer from the enquiry's page (UJ-10): confirmed at once, because the
 * time is agreed with the visitor. Needs `tours.manage` for the property and sight of the enquiry; the
 * time must be free under the property's lock (ClaimTourSlot). Audited.
 */
class ScheduleTour
{
    public function __construct(private ClaimTourSlot $claimTourSlot) {}

    /**
     * @param  array<string, mixed>  $data  property_id, starts_at (an instant), visitors, notes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Lead $lead, array $data): TourRequest
    {
        $validated = Validator::make($data, [
            'property_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'visitors' => ['required', 'integer', 'between:1,'.config('tours.max_visitors')],
            'notes' => ['nullable', 'string', 'max:'.RequestTour::MAX_NOTES_LENGTH, 'not_regex:/[<>]/'],
        ], [
            'starts_at.required' => 'Choose a free time.',
            'visitors.between' => 'Tours are for 1 to '.config('tours.max_visitors').' people.',
            'notes.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['property_id' => 'location', 'starts_at' => 'time', 'visitors' => 'number of people'])->validate();

        $property = Property::query()->find($validated['property_id']);

        if ($property === null || ! Gate::forUser($actor)->allows('view', $lead) || ! $actor->hasCapabilityForProperty(Capability::ManageTours, $property)) {
            throw new AuthorizationException('You cannot arrange tours of this location for this enquiry.');
        }

        return $this->claimTourSlot->handle(
            $property,
            CarbonImmutable::parse($validated['starts_at'])->utc(),
            fn (): Lead => $lead,
            TourStatus::Confirmed,
            ['visitor_count' => (int) $validated['visitors'], 'notes' => $validated['notes'] ?? null, 'channel' => TourRequest::CHANNEL_STAFF, 'created_by' => $actor->getKey()],
            $actor,
        );
    }
}
