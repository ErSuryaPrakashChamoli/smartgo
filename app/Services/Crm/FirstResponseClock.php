<?php

namespace App\Services\Crm;

use App\Models\Lead;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * How long a new enquiry waits for its first response (FR-CRM-1, BR-CRM-4, UJ-10 "SLA visible"). The wait
 * is always plain time since the enquiry arrived, until `first_responded_at` (set by the first recorded
 * contact or status change; whether a status change alone should count is an open question, KPI_DEFINITIONS
 * §2.2). A target applies only once the owner has set both its minutes and which hours count
 * (`crm.first_response`, D-09, A-41):
 * - "calendar": the target runs every minute;
 * - "location_hours": the target runs only during the enquiry's location's opening hours (`operating_hours`,
 *   India time); an enquiry without a location, or at a location without recorded hours, then has no due
 *   time rather than a guessed one.
 * It reads only; it sends nothing (O-1).
 */
class FirstResponseClock
{
    public const string CALENDAR = 'calendar';

    public const string LOCATION_HOURS = 'location_hours';

    /**
     * How far ahead the opening hours are searched for the due time.
     */
    private const int SEARCH_DAYS = 14;

    /**
     * Whether the owner has set both the target and the hours it counts in.
     */
    public function hasTarget(): bool
    {
        $target = config('crm.first_response.target_minutes');

        return is_int($target) && $target > 0 && in_array(config('crm.first_response.clock'), [self::CALENDAR, self::LOCATION_HOURS], true);
    }

    /**
     * Minutes from arrival to the first response, or to now while there is none, in plain time.
     */
    public function waitedMinutes(Lead $lead, ?CarbonInterface $now = null): int
    {
        return (int) floor($lead->created_at->diffInMinutes($lead->first_responded_at ?? $now ?? now(), true));
    }

    /**
     * When the first response is due, or null without a target, or when the location's hours give none.
     */
    public function dueAt(Lead $lead): ?CarbonImmutable
    {
        if (! $this->hasTarget()) {
            return null;
        }

        $received = CarbonImmutable::parse($lead->created_at);
        $target = (int) config('crm.first_response.target_minutes');

        if (config('crm.first_response.clock') === self::CALENDAR) {
            return $received->addMinutes($target);
        }

        return $this->withinOpeningHours($received, $target, $lead->property?->operating_hours ?? []);
    }

    /**
     * Whether the enquiry still has no first response after its due time.
     */
    public function isOverdue(Lead $lead, ?CarbonInterface $now = null): bool
    {
        $due = $lead->first_responded_at === null ? $this->dueAt($lead) : null;

        return $due !== null && ($now ?? now())->greaterThan($due);
    }

    /**
     * Whether a first response came after its due time; null without a response or a due time.
     */
    public function respondedLate(Lead $lead): ?bool
    {
        $due = $lead->first_responded_at === null ? null : $this->dueAt($lead);

        return $due === null ? null : $lead->first_responded_at->greaterThan($due);
    }

    /**
     * The enquiries the user may see that are past their due time without a first response.
     *
     * @return list<int>
     */
    public function overdueLeadIds(User $user, ?CarbonInterface $now = null): array
    {
        if (! $this->hasTarget()) {
            return [];
        }

        return Lead::query()->visibleTo($user)->whereNull('first_responded_at')->with('property')->get()
            ->filter(fn (Lead $lead): bool => $this->isOverdue($lead, $now))
            ->modelKeys();
    }

    /**
     * A wait as staff read it: "8 min", "2 h 15 min", "3 d 4 h".
     */
    public function describe(int $minutes): string
    {
        return match (true) {
            $minutes < 60 => $minutes.' min',
            $minutes < 1440 => intdiv($minutes, 60).' h'.($minutes % 60 > 0 ? ' '.($minutes % 60).' min' : ''),
            default => intdiv($minutes, 1440).' d'.(intdiv($minutes % 1440, 60) > 0 ? ' '.intdiv($minutes % 1440, 60).' h' : ''),
        };
    }

    /**
     * The moment the given minutes of opening hours have passed since arrival.
     *
     * @param  array<string, array{opens?: string|null, closes?: string|null}>  $hours
     */
    private function withinOpeningHours(CarbonImmutable $received, int $minutes, array $hours): ?CarbonImmutable
    {
        $local = $received->setTimezone(config('backoffice.timezone'));
        $remaining = $minutes;

        for ($offset = 0; $offset < self::SEARCH_DAYS; $offset++) {
            $day = $local->startOfDay()->addDays($offset);
            $today = $hours[strtolower($day->format('D'))] ?? [];

            if (blank($today['opens'] ?? null) || blank($today['closes'] ?? null)) {
                continue;
            }

            $opens = $day->setTimeFromTimeString($today['opens']);
            $closes = $day->setTimeFromTimeString($today['closes']);
            $start = $opens->max($local);

            if ($start->greaterThanOrEqualTo($closes)) {
                continue;
            }

            $available = (int) floor($start->diffInMinutes($closes, true));

            if ($available >= $remaining) {
                return $start->addMinutes($remaining)->utc();
            }

            $remaining -= $available;
        }

        return null;
    }
}
