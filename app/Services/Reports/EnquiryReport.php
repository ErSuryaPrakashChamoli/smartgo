<?php

namespace App\Services\Reports;

use App\Enums\Access\Capability;
use App\Enums\Booking\TourStatus;
use App\Enums\Crm\LeadActivityType;
use App\Enums\Crm\LeadStatus;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Property;
use App\Models\TourRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\WeekDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Enquiry and tour totals for the back office (FR-AN-2, the operational part; AC-12.2): counts only,
 * never a visitor's details, within the property scope of `leads.reports.view` (and of `tours.view` for
 * tours). Definitions (also in DECISION_REGISTER OD-132):
 * - a period is whole days in India time, from the first day 00:00 to the end of the last day;
 * - "received" counts enquiries created in the period, whatever their status now (enquiries are never
 *   deleted; spam is never stored);
 * - "first contact" is the earliest call, email, WhatsApp or meeting recorded on an enquiry (a status
 *   change alone is not a contact); its time is counted from receipt; no target is applied (D-09);
 * - "led to a tour" counts enquiries received in the period with at least one tour, whatever its status;
 * - tours "requested or arranged" were created in the period; tours "taking place" start in it;
 * - workload figures are as of now, not for the period.
 */
class EnquiryReport
{
    public const int MAX_DAYS = 366;

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, CarbonImmutable $firstDay, CarbonImmutable $lastDay, ?int $propertyId = null): array
    {
        $zone = (string) config('backoffice.timezone');
        $from = CarbonImmutable::parse($firstDay->toDateString(), $zone)->utc();
        $until = CarbonImmutable::parse($lastDay->toDateString(), $zone)->addDay()->utc();
        $received = fn (): Builder => $this->leads($user, $propertyId)->where('leads.created_at', '>=', $from)->where('leads.created_at', '<', $until);
        $weekly = $firstDay->diffInDays($lastDay) >= 31;

        $report = [
            'period' => ['from' => $firstDay->toDateString(), 'to' => $lastDay->toDateString(), 'zone' => $zone],
            'received' => $received()->count(),
            'over_time_by' => $weekly ? 'week' : 'day',
            'over_time' => $this->overTime($received(), $firstDay, $lastDay, $weekly),
            'by_status' => $this->grouped($received(), 'leads.status', fn (?string $value): string => LeadStatus::tryFrom((string) $value)?->label() ?? 'Unknown'),
            'by_source' => $this->grouped($received(), 'leads.source', fn (?string $value): string => self::SOURCES[$value] ?? (string) $value),
            'by_location' => $this->byLocation($received()),
            'by_campaign' => $this->grouped($received(), 'leads.utm_source', fn (?string $value): string => $value ?? 'No campaign recorded'),
            'first_contact' => $this->firstContact($received()),
            'workload' => $this->workload($user, $propertyId),
            'tours' => null,
        ];

        if (TourRequest::isAvailable() && $user->can(Capability::ViewTours)) {
            $report['led_to_tour'] = $received()->whereHas('tourRequests')->count();
            $report['tours'] = $this->tours($user, $propertyId, $from, $until);
        }

        return $report;
    }

    /**
     * Enquiries within the user's scope for reports, optionally of one property.
     *
     * @return Builder<Lead>
     */
    public function leads(User $user, ?int $propertyId): Builder
    {
        return Lead::query()->visibleTo($user, Capability::ViewLeadReports)->when($propertyId !== null, fn (Builder $query) => $query->where('leads.property_id', $propertyId));
    }

    /**
     * @var array<string, string>
     */
    public const array SOURCES = [Lead::SOURCE_WEBSITE_FORM => 'Website enquiry form', Lead::SOURCE_TOUR_FORM => 'Website tour request', Lead::SOURCE_CITY_WAITLIST => 'City waitlist'];

    /**
     * Counts per day (periods up to 31 days) or per week starting Monday, in the back-office time zone
     * (India time; its offset at the start of the period, as India has no daylight saving), with empty
     * days or weeks shown as zero. Monday is named because Carbon's week otherwise follows the language.
     *
     * @param  Builder<Lead>  $query
     * @return list<array{label: string, total: int}>
     */
    private function overTime(Builder $query, CarbonImmutable $firstDay, CarbonImmutable $lastDay, bool $weekly): array
    {
        $offset = CarbonImmutable::parse($firstDay->toDateString(), (string) config('backoffice.timezone'))->format('P');
        $perDay = $query->toBase()->selectRaw("DATE(CONVERT_TZ(leads.created_at, '+00:00', ?)) AS day, COUNT(*) AS total", [$offset])
            ->groupBy('day')->pluck('total', 'day')->map(fn (mixed $total): int => (int) $total);
        $buckets = [];

        for ($day = $firstDay; $day->lessThanOrEqualTo($lastDay); $day = $day->addDay()) {
            $weekStart = $day->startOfWeek(WeekDay::Monday);
            $key = $weekly ? $weekStart->toDateString() : $day->toDateString();
            $buckets[$key] ??= ['label' => $weekly ? 'Week of '.$weekStart->format('j M Y') : $day->format('D j M Y'), 'total' => 0];
            $buckets[$key]['total'] += $perDay->get($day->toDateString(), 0);
        }

        return array_values($buckets);
    }

    /**
     * @param  Builder<Lead>  $query
     * @param  callable(?string): string  $label
     * @return list<array{label: string, total: int}>
     */
    private function grouped(Builder $query, string $column, callable $label): array
    {
        return $query->toBase()->selectRaw($column.' AS value, COUNT(*) AS total')->groupBy($column)->orderByDesc('total')->get()
            ->map(fn (object $row): array => ['label' => $label($row->value === null ? null : (string) $row->value), 'total' => (int) $row->total])->all();
    }

    /**
     * @param  Builder<Lead>  $query
     * @return list<array{label: string, total: int}>
     */
    private function byLocation(Builder $query): array
    {
        $rows = $query->toBase()->selectRaw('leads.property_id AS value, COUNT(*) AS total')->groupBy('leads.property_id')->orderByDesc('total')->get();
        $names = Property::query()->whereKey($rows->pluck('value')->filter()->all())->pluck('name', 'id');

        return $rows->map(fn (object $row): array => ['label' => $row->value === null ? 'No location chosen' : 'SmartGro '.$names->get($row->value, '#'.$row->value), 'total' => (int) $row->total])->all();
    }

    /**
     * Time from receipt to the first recorded contact, observed only: median and 90th percentile in
     * minutes (nearest rank), with how many have no contact recorded yet.
     *
     * @param  Builder<Lead>  $query
     * @return array{contacted: int, not_contacted: int, median_minutes: int|null, p90_minutes: int|null}
     */
    private function firstContact(Builder $query): array
    {
        $first = LeadActivity::query()->selectRaw('MIN(created_at)')->whereColumn('lead_activities.lead_id', 'leads.id')
            ->whereIn('type', collect(LeadActivityType::cases())->filter->isContact()->map->value->values()->all());

        $rows = $query->toBase()->select('leads.created_at')->selectSub($first->toBase(), 'first_contact_at')->get();
        $minutes = $rows->filter(fn (object $row): bool => $row->first_contact_at !== null)
            ->map(fn (object $row): int => (int) max(0, CarbonImmutable::parse($row->created_at)->diffInMinutes(CarbonImmutable::parse($row->first_contact_at))))
            ->sort()->values();
        $total = $rows->count();

        return [
            'contacted' => $minutes->count(),
            'not_contacted' => $total - $minutes->count(),
            'median_minutes' => $this->rank($minutes, 0.5),
            'p90_minutes' => $this->rank($minutes, 0.9),
        ];
    }

    /**
     * @param  Collection<int, int>  $sorted
     */
    private function rank(Collection $sorted, float $share): ?int
    {
        return $sorted->isEmpty() ? null : $sorted->get((int) ceil($share * $sorted->count()) - 1);
    }

    /**
     * Open enquiries without an owner, and open follow-ups due or overdue, as of now.
     *
     * @return array{unassigned_open: int, follow_ups_overdue: int, follow_ups_due_today: int}
     */
    private function workload(User $user, ?int $propertyId): array
    {
        $open = [LeadStatus::New, LeadStatus::Contacted, LeadStatus::Qualified];
        $followUps = fn (): Builder => LeadActivity::query()->where('type', LeadActivityType::FollowUp)->whereNull('completed_at')
            ->whereIn('lead_id', $this->leads($user, $propertyId)->select('leads.id'));
        $endOfToday = CarbonImmutable::now((string) config('backoffice.timezone'))->addDay()->startOfDay()->utc();

        return [
            'unassigned_open' => $this->leads($user, $propertyId)->whereIn('leads.status', $open)->whereNull('leads.owner_user_id')->count(),
            'follow_ups_overdue' => $followUps()->where('due_at', '<', now())->count(),
            'follow_ups_due_today' => $followUps()->where('due_at', '>=', now())->where('due_at', '<', $endOfToday)->count(),
        ];
    }

    /**
     * @return array{requested: int, requested_by_status: list<array{label: string, total: int}>, requested_by_channel: list<array{label: string, total: int}>, requested_by_location: list<array{label: string, total: int}>, taking_place: int, taking_place_by_status: list<array{label: string, total: int}>}
     */
    private function tours(User $user, ?int $propertyId, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $scope = fn (): Builder => TourRequest::query()->visibleTo($user)->when($propertyId !== null, fn (Builder $query) => $query->where('tour_requests.property_id', $propertyId));
        $created = fn (): Builder => $scope()->where('tour_requests.created_at', '>=', $from)->where('tour_requests.created_at', '<', $until);
        $taking = fn (): Builder => $scope()->where('scheduled_start_at', '>=', $from)->where('scheduled_start_at', '<', $until);
        $status = fn (?string $value): string => TourStatus::tryFrom((string) $value)?->label() ?? 'Unknown';
        $names = Property::query()->pluck('name', 'id');

        return [
            'requested' => $created()->count(),
            'requested_by_status' => $this->groupedTours($created(), 'status', $status),
            'requested_by_channel' => $this->groupedTours($created(), 'channel', fn (?string $value): string => $value === TourRequest::CHANNEL_WEBSITE ? 'Requested on the website' : 'Arranged by staff'),
            'requested_by_location' => $this->groupedTours($created(), 'tour_requests.property_id', fn (?string $value): string => 'SmartGro '.$names->get((int) $value, '#'.$value)),
            'taking_place' => $taking()->count(),
            'taking_place_by_status' => $this->groupedTours($taking(), 'status', $status),
        ];
    }

    /**
     * @param  Builder<TourRequest>  $query
     * @param  callable(?string): string  $label
     * @return list<array{label: string, total: int}>
     */
    private function groupedTours(Builder $query, string $column, callable $label): array
    {
        return $query->toBase()->selectRaw($column.' AS value, COUNT(*) AS total')->groupBy($column)->orderByDesc('total')->get()
            ->map(fn (object $row): array => ['label' => $label($row->value === null ? null : (string) $row->value), 'total' => (int) $row->total])->all();
    }
}
