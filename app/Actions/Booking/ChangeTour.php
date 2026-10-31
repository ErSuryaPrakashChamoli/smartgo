<?php

namespace App\Actions\Booking;

use App\Enums\Access\Capability;
use App\Enums\Booking\TourStatus;
use App\Models\Property;
use App\Models\TourRequest;
use App\Models\User;
use App\Services\Booking\TourSlots;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Confirms, moves, cancels or records the outcome of a tour, or names its host (§11 `tours.manage`:
 * "Confirm, reschedule and cancel tours"), and lets a visitor cancel or move their own tour from their
 * private link. The property's row is locked first, then the tour's, and the status the person saw is
 * checked again, so a stale screen cannot undo someone else's change. A new time must be free under the
 * lock (BR-TR-2); a time a visitor picks waits for staff again, unless confirming at once is switched on
 * (D-07). Cancelling needs a reason; nothing is deleted. Every change is audited.
 */
class ChangeTour
{
    public const array STAFF_CHANGES = ['confirm', 'reschedule', 'cancel', 'complete', 'no_show', 'assign_host'];

    public const array VISITOR_CHANGES = ['reschedule', 'cancel'];

    public function __construct(private TourSlots $slots) {}

    /**
     * @param  array<string, mixed>  $data  starts_at for reschedule, reason for cancel, host_user_id for assign_host
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(?User $actor, TourRequest $tour, string $change, TourStatus $expected, array $data = []): TourRequest
    {
        if (! in_array($change, $actor === null ? self::VISITOR_CHANGES : self::STAFF_CHANGES, true)) {
            throw new AuthorizationException('This change is not available.');
        }

        if ($actor !== null && ! $tour->isWithinScopeOf($actor, Capability::ManageTours)) {
            throw new AuthorizationException('You cannot change tours of this location.');
        }

        $data = Validator::make($data, [
            'starts_at' => [$change === 'reschedule' ? 'required' : 'nullable', 'date'],
            'reason' => [$change === 'cancel' ? 'required' : 'nullable', 'string', 'max:300', 'not_regex:/[<>]/'],
            'host_user_id' => ['nullable', 'integer'],
        ], [
            'starts_at.required' => 'Choose a free time.',
            'reason.required' => 'Give the reason for cancelling.',
            'reason.not_regex' => 'Use plain text: < and > are not allowed.',
        ])->validate();

        return DB::transaction(function () use ($actor, $tour, $change, $expected, $data): TourRequest {
            $property = Property::query()->lockForUpdate()->findOrFail($tour->property_id);
            $tour = TourRequest::query()->lockForUpdate()->findOrFail($tour->getKey());

            if ($tour->status !== $expected) {
                throw ValidationException::withMessages(['status' => 'This tour was changed meanwhile. Reload it and try again.']);
            }

            $before = ['status' => $tour->status->value, 'starts_at' => $tour->scheduled_start_at->toIso8601String(), 'host_user_id' => $tour->host_user_id];
            [$event, $description] = match ($change) {
                'confirm' => $this->confirm($tour, $actor),
                'reschedule' => $this->reschedule($property, $tour, $actor, CarbonImmutable::parse((string) $data['starts_at'])->utc()),
                'cancel' => $this->cancel($tour, (string) $data['reason']),
                'complete', 'no_show' => $this->outcome($tour, $change === 'complete' ? TourStatus::Completed : TourStatus::NoShow),
                'assign_host' => $this->assignHost($property, $tour, isset($data['host_user_id']) ? (int) $data['host_user_id'] : null),
            };
            $tour->save();

            activity('tours')
                ->performedOn($tour)
                ->causedBy($actor)
                ->event($event)
                ->withProperties([
                    'reference' => $tour->reference,
                    'channel' => $actor === null ? TourRequest::CHANNEL_WEBSITE : TourRequest::CHANNEL_STAFF,
                    'old' => $before,
                    'new' => ['status' => $tour->status->value, 'starts_at' => $tour->scheduled_start_at->toIso8601String(), 'host_user_id' => $tour->host_user_id],
                    'reason' => $change === 'cancel' ? $data['reason'] : null,
                ])
                ->log($description);

            return $tour;
        });
    }

    /**
     * @return array{string, string}
     */
    private function confirm(TourRequest $tour, User $actor): array
    {
        $this->require($tour->status === TourStatus::Requested && $tour->scheduled_start_at->isFuture(), 'Only a requested tour that has not started can be confirmed.');
        $tour->forceFill(['status' => TourStatus::Confirmed, 'confirmed_at' => now(), 'confirmed_by' => $actor->getKey()]);

        return ['tour_confirmed', 'Tour confirmed'];
    }

    /**
     * @return array{string, string}
     */
    private function reschedule(Property $property, TourRequest $tour, ?User $actor, CarbonImmutable $startsAt): array
    {
        $this->require($tour->status->isUpcoming() && $tour->scheduled_start_at->isFuture(), 'Only a tour that has not started can be moved.');
        $slot = $this->slots->freeSlotAt($property, $startsAt, ignoring: $tour);
        $this->require($slot !== null && ! $slot['starts_at']->equalTo($tour->scheduled_start_at), ClaimTourSlot::SLOT_TAKEN);
        $waitsForStaff = $actor === null && ! config('tours.confirm_on_request');

        $tour->forceFill([
            'scheduled_start_at' => $slot['starts_at'],
            'duration_minutes' => $slot['minutes'],
            ...($waitsForStaff ? ['status' => TourStatus::Requested, 'confirmed_at' => null, 'confirmed_by' => null] : []),
        ]);

        return ['tour_rescheduled', $actor === null ? 'Tour moved by the visitor' : 'Tour moved'];
    }

    /**
     * @return array{string, string}
     */
    private function cancel(TourRequest $tour, string $reason): array
    {
        $this->require($tour->status->isUpcoming() && $tour->scheduled_start_at->isFuture(), 'Only a tour that has not started can be cancelled.');
        $tour->forceFill(['status' => TourStatus::Cancelled, 'cancelled_at' => now(), 'cancellation_reason' => $reason]);

        return ['tour_cancelled', 'Tour cancelled'];
    }

    /**
     * @return array{string, string}
     */
    private function outcome(TourRequest $tour, TourStatus $outcome): array
    {
        $this->require($tour->status === TourStatus::Confirmed && ! $tour->scheduled_start_at->isFuture(), 'The outcome can be recorded once a confirmed tour has started.');
        $tour->forceFill(['status' => $outcome]);

        return [$outcome === TourStatus::Completed ? 'tour_completed' : 'tour_no_show', $outcome === TourStatus::Completed ? 'Tour completed' : 'Visitor did not come'];
    }

    /**
     * @return array{string, string}
     */
    private function assignHost(Property $property, TourRequest $tour, ?int $hostId): array
    {
        $this->require($tour->status->isUpcoming(), 'A host can be named only for a tour that is still to come.');

        if ($hostId !== null) {
            $host = User::query()->whereKey($hostId)->where('is_active', true)->first();
            $this->require($host !== null && $host->hasCapabilityForProperty(Capability::ViewTours, $property), 'Choose an active member of staff who works on this location\'s tours.');
        }

        $tour->forceFill(['host_user_id' => $hostId]);

        return ['tour_host_assigned', $hostId === null ? 'Tour host removed' : 'Tour host named'];
    }

    /**
     * @throws ValidationException
     */
    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['tour' => $message]);
        }
    }
}
