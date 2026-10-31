<?php

namespace App\Services\Booking;

use App\Models\Property;
use App\Models\PropertyClosure;
use App\Models\TourAvailabilityRule;
use App\Models\TourRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The tour times of a property (BR-TR-1): generated from its tour hours, minus closures, minus slots
 * already holding as many tours as may run at once. Times are worked out in India time and returned in
 * UTC. Only future times are offered, and only within the window visitors may book (config
 * tours.window_days). A time shown as free is advisory: claiming it checks again under the property's
 * lock (BR-TR-2, ClaimTourSlot).
 */
class TourSlots
{
    /**
     * Every slot from the first day to the last (inclusive, India dates), with whether it is free.
     *
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, minutes: int, free: bool}>
     */
    public function between(Property $property, CarbonImmutable $firstDay, CarbonImmutable $lastDay, ?TourRequest $ignoring = null): Collection
    {
        $timezone = (string) config('backoffice.timezone');
        $rules = $property->tourRules()->get()->groupBy('weekday');

        if ($rules->isEmpty()) {
            return collect();
        }

        $from = CarbonImmutable::parse($firstDay->toDateString(), $timezone)->utc();
        $to = CarbonImmutable::parse($lastDay->toDateString(), $timezone)->addDay()->utc();
        $closures = PropertyClosure::query()->where('property_id', $property->getKey())->overlapping(Carbon::instance($from), Carbon::instance($to))->get();
        $tours = TourRequest::query()->where('property_id', $property->getKey())->holdingPlace()->overlapping(Carbon::instance($from), Carbon::instance($to))
            ->when($ignoring !== null, fn ($query) => $query->whereKeyNot($ignoring->getKey()))
            ->get(['id', 'scheduled_start_at', 'duration_minutes']);
        $earliest = CarbonImmutable::now()->addMinutes((int) config('tours.minimum_notice_minutes'));
        $slots = collect();

        for ($day = CarbonImmutable::parse($firstDay->toDateString(), $timezone); $day->lessThan($to); $day = $day->addDay()) {
            /** @var TourAvailabilityRule $rule */
            foreach ($rules->get($day->isoWeekday(), collect()) as $rule) {
                $end = $day->setTimeFromTimeString((string) $rule->end_time);

                for ($start = $day->setTimeFromTimeString((string) $rule->start_time); $start->addMinutes($rule->slot_minutes)->lessThanOrEqualTo($end); $start = $start->addMinutes($rule->slot_minutes)) {
                    $startsAt = $start->utc();
                    $endsAt = $startsAt->addMinutes($rule->slot_minutes);

                    if ($startsAt->lessThanOrEqualTo($earliest) || $closures->contains(fn (PropertyClosure $closure): bool => $closure->starts_at->lessThan($endsAt) && $closure->ends_at->greaterThan($startsAt))) {
                        continue;
                    }

                    $taken = $tours->filter(fn (TourRequest $tour): bool => $tour->scheduled_start_at->lessThan($endsAt) && $tour->scheduledEndAt()->greaterThan($startsAt))->count();
                    $slots->push(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'minutes' => $rule->slot_minutes, 'free' => $taken < $rule->max_concurrent_tours]);
                }
            }
        }

        return $slots->sortBy(fn (array $slot): int => $slot['starts_at']->getTimestamp())->values();
    }

    /**
     * The slots visitors may pick now: from today (India date) for the configured number of days.
     *
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, minutes: int, free: bool}>
     */
    public function bookable(Property $property, ?TourRequest $ignoring = null): Collection
    {
        $today = CarbonImmutable::parse(CarbonImmutable::now((string) config('backoffice.timezone'))->toDateString());

        return $this->between($property, $today, $today->addDays((int) config('tours.window_days') - 1), $ignoring);
    }

    /**
     * The free bookable slot starting at that instant, or null when there is none.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, minutes: int, free: bool}|null
     */
    public function freeSlotAt(Property $property, CarbonImmutable $startsAt, ?TourRequest $ignoring = null): ?array
    {
        return $this->bookable($property, $ignoring)->first(fn (array $slot): bool => $slot['free'] && $slot['starts_at']->equalTo($startsAt));
    }

    /**
     * The next few free slots after an instant, for "Here are the next available times".
     *
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, minutes: int, free: bool}>
     */
    public function nextFree(Property $property, CarbonImmutable $after, int $count = 3): Collection
    {
        return $this->bookable($property)->filter(fn (array $slot): bool => $slot['free'] && $slot['starts_at']->greaterThan($after))->take($count)->values();
    }

    /**
     * Free bookable times as choices, keyed by the instant, e.g. "Tue 2 Mar, 10:00 to 10:30".
     *
     * @return array<string, string>
     */
    public function options(Property $property, ?TourRequest $ignoring = null): array
    {
        $timezone = (string) config('backoffice.timezone');

        return $this->bookable($property, $ignoring)->filter(fn (array $slot): bool => $slot['free'])
            ->mapWithKeys(fn (array $slot): array => [$slot['starts_at']->toAtomString() => $slot['starts_at']->timezone($timezone)->format('D j M, H:i').' to '.$slot['ends_at']->timezone($timezone)->format('H:i')])
            ->all();
    }

    /**
     * Whether the property has tour hours at all.
     */
    public function hasHours(Property $property): bool
    {
        return TourRequest::isAvailable() && $property->tourRules()->exists();
    }
}
