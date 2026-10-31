<?php

namespace App\Actions\Booking;

use App\Enums\Access\Capability;
use App\Models\Property;
use App\Models\TourAvailabilityRule;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Adds, changes or removes a window of a property's tour hours (BR-TR-1) from SmartGro's tour rules: no
 * hours, slot length or number of parallel tours is assumed (D-28). Needs `tours.manage` for the
 * property (§3.1 "Tours"). The property's row is locked, so two windows of the same day can never
 * overlap. Tours already arranged are never changed. Audited.
 */
class SaveTourRule
{
    /**
     * @param  array<string, mixed>  $data  weekday, start_time, end_time (HH:MM, India time), slot_minutes, max_concurrent_tours
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Property $property, ?TourAvailabilityRule $rule, array $data): TourAvailabilityRule
    {
        $this->authorize($actor, $property, $rule);

        $validated = Validator::make($data, [
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'between:10,240'],
            'max_concurrent_tours' => ['required', 'integer', 'between:1,20'],
        ], [
            'end_time.after' => 'The last tour must end after the first one starts.',
            'slot_minutes.between' => 'A tour lasts from 10 to 240 minutes.',
            'max_concurrent_tours.between' => 'From 1 to 20 tours can run at a time.',
        ], ['weekday' => 'day', 'start_time' => 'first tour', 'end_time' => 'last tour ends', 'slot_minutes' => 'tour length', 'max_concurrent_tours' => 'tours at a time'])->validate();

        [$startHour, $startMinute] = array_map(intval(...), explode(':', $validated['start_time']));
        [$endHour, $endMinute] = array_map(intval(...), explode(':', $validated['end_time']));

        if (($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute) < (int) $validated['slot_minutes']) {
            throw ValidationException::withMessages(['end_time' => 'The window is shorter than one tour.']);
        }

        return DB::transaction(function () use ($actor, $property, $rule, $validated): TourAvailabilityRule {
            Property::query()->lockForUpdate()->findOrFail($property->getKey());

            $overlapping = TourAvailabilityRule::query()->where('property_id', $property->getKey())->where('weekday', $validated['weekday'])
                ->when($rule !== null, fn ($query) => $query->whereKeyNot($rule->getKey()))
                ->where('start_time', '<', $validated['end_time'].':00')->where('end_time', '>', $validated['start_time'].':00')
                ->exists();

            if ($overlapping) {
                throw ValidationException::withMessages(['start_time' => 'These hours overlap other tour hours on '.TourAvailabilityRule::WEEKDAYS[(int) $validated['weekday']].'. Change those instead.']);
            }

            $rule = $rule === null ? new TourAvailabilityRule : TourAvailabilityRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            $before = $rule->exists ? $rule->summary() : null;

            $rule->forceFill([
                'property_id' => $property->getKey(),
                'weekday' => (int) $validated['weekday'],
                'start_time' => $validated['start_time'].':00',
                'end_time' => $validated['end_time'].':00',
                'slot_minutes' => (int) $validated['slot_minutes'],
                'max_concurrent_tours' => (int) $validated['max_concurrent_tours'],
                ...($rule->exists ? ['updated_by' => $actor->getKey()] : ['created_by' => $actor->getKey()]),
            ])->save();

            activity('tours')
                ->performedOn($rule)
                ->causedBy($actor)
                ->event($before === null ? 'tour_hours_added' : 'tour_hours_changed')
                ->withProperties(['property' => $property->code, 'old' => $before, 'new' => $rule->summary()])
                ->log($before === null ? 'Tour hours added' : 'Tour hours changed');

            return $rule;
        });
    }

    /**
     * Removes a window; tours already arranged in it stay as they are.
     *
     * @throws AuthorizationException
     */
    public function remove(User $actor, TourAvailabilityRule $rule): void
    {
        $this->authorize($actor, $rule->property, $rule);

        DB::transaction(function () use ($actor, $rule): void {
            Property::query()->lockForUpdate()->findOrFail($rule->property_id);
            $rule = TourAvailabilityRule::query()->lockForUpdate()->findOrFail($rule->getKey());

            activity('tours')
                ->performedOn($rule)
                ->causedBy($actor)
                ->event('tour_hours_removed')
                ->withProperties(['property' => $rule->property->code, 'old' => $rule->summary()])
                ->log('Tour hours removed');

            $rule->delete();
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor, Property $property, ?TourAvailabilityRule $rule): void
    {
        if (($rule !== null && $rule->property_id !== $property->getKey()) || ! $actor->hasCapabilityForProperty(Capability::ManageTours, $property)) {
            throw new AuthorizationException('You cannot change the tour hours of this location.');
        }
    }
}
