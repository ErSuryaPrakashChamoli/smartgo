<?php

namespace App\Actions\Booking;

use App\Enums\Booking\TourStatus;
use App\Events\Booking\TourScheduled;
use App\Models\Lead;
use App\Models\Property;
use App\Models\TourRequest;
use App\Models\User;
use App\Services\Booking\TourSlots;
use App\Services\Crm\VisitorIntake;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a place in a tour slot (BR-TR-2): the property's row is locked first, then the slot is checked
 * again against its tour hours, closures and the tours already holding a place, so two people can never
 * take the last place at the same instant (ADR-0004). The enquiry is resolved inside the same
 * transaction, so a refused claim stores nothing. Audited; TourScheduled follows the commit.
 */
class ClaimTourSlot
{
    public const string SLOT_TAKEN = 'That time is no longer available: someone took the last place a moment ago. Please choose another time.';

    public function __construct(private TourSlots $slots, private VisitorIntake $intake) {}

    /**
     * @param  Closure(): Lead  $lead  finds or adds the enquiry the tour belongs to
     * @param  array{visitor_count: int, notes: string|null, channel: string, created_by: int|null}  $attributes
     *
     * @throws ValidationException when the slot is no longer free
     */
    public function handle(Property $property, CarbonImmutable $startsAt, Closure $lead, TourStatus $status, array $attributes, ?User $actor): TourRequest
    {
        return DB::transaction(function () use ($property, $startsAt, $lead, $status, $attributes, $actor): TourRequest {
            $property = Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $slot = $this->slots->freeSlotAt($property, $startsAt);

            if ($slot === null) {
                throw ValidationException::withMessages(['starts_at' => self::SLOT_TAKEN]);
            }

            $enquiry = $lead();
            $tour = (new TourRequest)->forceFill([
                'reference' => $this->intake->reference('TOUR', TourRequest::class),
                'lead_id' => $enquiry->getKey(),
                'property_id' => $property->getKey(),
                'scheduled_start_at' => $slot['starts_at'],
                'duration_minutes' => $slot['minutes'],
                'status' => $status,
                'confirmed_at' => $status === TourStatus::Confirmed ? now() : null,
                'confirmed_by' => $status === TourStatus::Confirmed ? $actor?->getKey() : null,
                ...$attributes,
            ]);
            $tour->save();

            activity('tours')
                ->performedOn($tour)
                ->causedBy($actor)
                ->event($status === TourStatus::Confirmed ? 'tour_scheduled' : 'tour_requested')
                ->withProperties([
                    'reference' => $tour->reference,
                    'lead' => $enquiry->reference,
                    'property' => $property->code,
                    'starts_at' => $tour->scheduled_start_at->toIso8601String(),
                    'visitor_count' => $tour->visitor_count,
                    'channel' => $tour->channel,
                ])
                ->log($status === TourStatus::Confirmed ? 'Tour arranged and confirmed' : 'Tour requested on the website');

            TourScheduled::dispatch($tour);

            return $tour;
        });
    }
}
