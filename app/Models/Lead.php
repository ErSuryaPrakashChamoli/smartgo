<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Crm\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An enquiry (BR-CRM-1): who asked, about which property, workspace category or offering, and what
 * staff have done about it. It never reserves anything and never becomes a booking or a payment.
 * Staff see it only within their scope (BR-CRM-6): enquiries about an assigned property for a Property
 * Manager (read-only) or a restricted Sales/CRM Executive; enquiries without a property only for staff
 * who work on every property. Never deleted by the application.
 */
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    public const string SOURCE_WEBSITE_FORM = 'website_form';

    public const string SOURCE_CITY_WAITLIST = 'city_waitlist';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'consent_snapshot' => 'array',
            'first_responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Offering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(Offering::class);
    }

    /**
     * @return BelongsTo<LeadCloseReason, $this>
     */
    public function closeReason(): BelongsTo
    {
        return $this->belongsTo(LeadCloseReason::class, 'close_reason_id');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<CustomerSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(CustomerSegment::class, 'customer_segment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<LeadActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('id');
    }

    /**
     * The enquiries the user may see for the capability: all of them for staff who work on every
     * property; those about assigned properties for the others; none without the capability.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, Capability $capability = Capability::ViewLeads): void
    {
        if (! $user->can($capability)) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->hasCapabilityForEveryProperty($capability)) {
            $query->whereIn('leads.property_id', $user->assignedProperties()->select('properties.id'));
        }
    }

    /**
     * Whether the user holds the capability for this enquiry's property, or for every property when it
     * has none.
     */
    public function isWithinScopeOf(User $user, Capability $capability): bool
    {
        return $this->property === null
            ? $user->hasCapabilityForEveryProperty($capability)
            : $user->hasCapabilityForProperty($capability, $this->property);
    }

    /**
     * What the visitor asked about, e.g. "Private offices and cabins at SmartGro A-70".
     */
    public function subject(): string
    {
        return match (true) {
            $this->product !== null && $this->property !== null => $this->product->name.' at SmartGro '.$this->property->name,
            $this->property !== null => 'SmartGro '.$this->property->name,
            $this->product !== null => $this->product->name,
            $this->city !== null => 'Opening in '.$this->city->name,
            default => 'General enquiry',
        };
    }
}
