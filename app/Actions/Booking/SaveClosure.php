<?php

namespace App\Actions\Booking;

use App\Enums\Access\Capability;
use App\Models\Property;
use App\Models\PropertyClosure;
use App\Models\TourRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Records, changes or removes a closure of a property (DATABASE_ERD §5), entered in India time and
 * stored in UTC. No tour is offered while a closure runs (BR-TR-1). Closures are part of running the
 * property, so they need `properties.manage` for it (interpretation, §3.1 "Properties, buildings,
 * floors"). Nothing already arranged is cancelled: the caller is told how many tours fall inside, for
 * staff to move or cancel. Audited with the reason.
 */
class SaveClosure
{
    /**
     * @param  array<string, mixed>  $data  starts_at, ends_at (Y-m-d H:i, India time), reason, is_public
     * @return array{closure: PropertyClosure, tours_inside: int}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Property $property, ?PropertyClosure $closure, array $data): array
    {
        $this->authorize($actor, $property, $closure);
        $zone = (string) config('backoffice.timezone');

        $validated = Validator::make($data, [
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:200', 'not_regex:/[<>]/'],
            'is_public' => ['boolean'],
        ], [
            'ends_at.after' => 'The closure must end after it starts.',
            'reason.not_regex' => 'Use plain text: < and > are not allowed.',
        ], ['starts_at' => 'start', 'ends_at' => 'end'])->validate();

        $startsAt = Carbon::parse($validated['starts_at'], $zone)->utc();
        $endsAt = Carbon::parse($validated['ends_at'], $zone)->utc();

        if ($endsAt->isPast() || $startsAt->greaterThan(now()->addYears(2))) {
            throw ValidationException::withMessages(['ends_at' => 'Record a closure that ends in the future and starts within the next two years.']);
        }

        return DB::transaction(function () use ($actor, $property, $closure, $validated, $startsAt, $endsAt): array {
            Property::query()->lockForUpdate()->findOrFail($property->getKey());
            $closure = $closure === null ? new PropertyClosure : PropertyClosure::query()->lockForUpdate()->findOrFail($closure->getKey());
            $before = $closure->exists ? ['starts_at' => $closure->starts_at->toIso8601String(), 'ends_at' => $closure->ends_at->toIso8601String(), 'reason' => $closure->reason] : null;

            $closure->forceFill([
                'property_id' => $property->getKey(),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $validated['reason'],
                'is_public' => (bool) ($validated['is_public'] ?? false),
                ...($closure->exists ? [] : ['created_by' => $actor->getKey()]),
            ])->save();

            $toursInside = TourRequest::query()->where('property_id', $property->getKey())->holdingPlace()->overlapping($startsAt, $endsAt)->count();

            activity('properties')
                ->performedOn($closure)
                ->causedBy($actor)
                ->event($before === null ? 'closure_added' : 'closure_changed')
                ->withProperties(['property' => $property->code, 'reason' => $validated['reason'], 'old' => $before, 'new' => ['starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String()], 'tours_inside' => $toursInside])
                ->log($before === null ? 'Closure recorded' : 'Closure changed');

            return ['closure' => $closure, 'tours_inside' => $toursInside];
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function remove(User $actor, PropertyClosure $closure): void
    {
        $this->authorize($actor, $closure->property, $closure);

        DB::transaction(function () use ($actor, $closure): void {
            Property::query()->lockForUpdate()->findOrFail($closure->property_id);
            $closure = PropertyClosure::query()->lockForUpdate()->findOrFail($closure->getKey());

            activity('properties')
                ->performedOn($closure)
                ->causedBy($actor)
                ->event('closure_removed')
                ->withProperties(['property' => $closure->property->code, 'old' => ['starts_at' => $closure->starts_at->toIso8601String(), 'ends_at' => $closure->ends_at->toIso8601String(), 'reason' => $closure->reason]])
                ->log('Closure removed');

            $closure->delete();
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor, Property $property, ?PropertyClosure $closure): void
    {
        if (($closure !== null && $closure->property_id !== $property->getKey()) || ! $actor->hasCapabilityForProperty(Capability::ManageProperties, $property)) {
            throw new AuthorizationException('You cannot record closures of this location.');
        }
    }
}
