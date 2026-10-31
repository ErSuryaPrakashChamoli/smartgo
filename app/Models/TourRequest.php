<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Booking\TourStatus;
use Database\Factories\TourRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * A site visit (DATABASE_ERD §5, FR-BK-2): a visitor's request from the website, or a tour staff
 * arranged with an enquirer. It belongs to an enquiry (BR-TR-3) and to one property, and staff see it
 * only within their scope, like enquiries (BR-CRM-6). Requested and confirmed tours hold their place in
 * the slot (BR-TR-1). Never deleted.
 */
class TourRequest extends Model
{
    /** @use HasFactory<TourRequestFactory> */
    use HasFactory;

    public const string CHANNEL_WEBSITE = 'website';

    public const string CHANNEL_STAFF = 'staff';

    /**
     * The tables tours need; the owner's server does not have them (OD-120), so screens and pages check.
     *
     * @var list<string>
     */
    public const array TABLES = ['tour_availability_rules', 'property_closures', 'tour_requests'];

    /**
     * Whether this database has the tour tables.
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => collect(self::TABLES)->every(fn (string $table): bool => Schema::hasTable($table)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TourStatus::class,
            'scheduled_start_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'visitor_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scheduledEndAt(): Carbon
    {
        return $this->scheduled_start_at->copy()->addMinutes($this->duration_minutes);
    }

    /**
     * Tours that take a place in a slot.
     *
     * @param  Builder<self>  $query
     */
    public function scopeHoldingPlace(Builder $query): void
    {
        $query->whereIn('status', TourStatus::holdingPlace());
    }

    /**
     * Tours whose time overlaps the period.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->where('scheduled_start_at', '<', $to)
            ->whereRaw('DATE_ADD(scheduled_start_at, INTERVAL duration_minutes MINUTE) > ?', [$from->copy()->utc()->format('Y-m-d H:i:s')]);
    }

    /**
     * The tours the user may see for the capability: all of them for staff who work on every property;
     * those of assigned properties for the others; none without the capability.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Capability $capability = Capability::ViewTours): void
    {
        if (! $user->can($capability)) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->hasCapabilityForEveryProperty($capability)) {
            $query->whereIn('tour_requests.property_id', $user->assignedProperties()->select('properties.id'));
        }
    }

    public function isWithinScopeOf(User $user, Capability $capability): bool
    {
        return $user->hasCapabilityForProperty($capability, $this->property);
    }

    /**
     * E.g. "Tuesday 2 March 2027, 10:00 to 10:30 (India time)".
     */
    public function whenLabel(): string
    {
        $timezone = (string) config('backoffice.timezone');

        return $this->scheduled_start_at->copy()->timezone($timezone)->format('l j F Y, H:i').' to '.$this->scheduledEndAt()->timezone($timezone)->format('H:i').' (India time)';
    }
}
