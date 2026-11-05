<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Enums\Crm\LeadStatus;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public const string SOURCE_TOUR_FORM = 'tour_form';

    /**
     * Where enquiries come from today, as staff read them.
     *
     * @var array<string, string>
     */
    public const array SOURCES = [
        self::SOURCE_WEBSITE_FORM => 'Website form',
        self::SOURCE_CITY_WAITLIST => 'City waitlist',
        self::SOURCE_TOUR_FORM => 'Website tour request',
    ];

    /**
     * The visitor's details as typed on the form, and the contact columns they belong in (BR-CRM-2).
     *
     * @var array<string, array{label: string, submitted: string, contact: string}>
     */
    public const array CONTACT_FIELDS = [
        'name' => ['label' => 'Name', 'submitted' => 'submitted_name', 'contact' => 'name'],
        'email' => ['label' => 'Email', 'submitted' => 'submitted_email', 'contact' => 'email'],
        'phone' => ['label' => 'Phone', 'submitted' => 'submitted_phone', 'contact' => 'phone_e164'],
        'company' => ['label' => 'Company', 'submitted' => 'submitted_company', 'contact' => 'company_name'],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'consent_snapshot' => 'array',
            'first_responded_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
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
     * What the assignment rules decided when the enquiry arrived (FR-CRM-2), if they ran.
     *
     * @return HasOne<LeadAssignment, $this>
     */
    public function assignment(): HasOne
    {
        return $this->hasOne(LeadAssignment::class);
    }

    /**
     * Every enquiry from the same contact, this one included.
     *
     * @return HasMany<Lead, $this>
     */
    public function sameContactLeads(): HasMany
    {
        return $this->hasMany(self::class, 'contact_id', 'contact_id');
    }

    /**
     * The details typed on this enquiry that its contact does not have, or has differently. A later
     * enquiry never changes the contact by itself (BR-CRM-2); staff may copy these across
     * (UpdateContactFromLead).
     *
     * @return array<string, array{label: string, submitted: string, contact: string|null}>
     */
    public function contactDifferences(): array
    {
        return collect(self::CONTACT_FIELDS)
            ->map(fn (array $field): array => ['label' => $field['label'], 'submitted' => $this->{$field['submitted']}, 'contact' => $this->contact->{$field['contact']}])
            ->filter(fn (array $difference): bool => filled($difference['submitted']) && $difference['submitted'] !== $difference['contact'])
            ->all();
    }

    /**
     * Other contacts that already have this enquiry's email or phone: the same visitor recorded more than
     * once, for example under an email by one enquiry and under a phone number by another.
     *
     * @return Collection<int, Contact>
     */
    public function otherContactsWithSameDetails(): Collection
    {
        if (blank($this->submitted_email) && blank($this->submitted_phone)) {
            return new Collection;
        }

        return Contact::query()
            ->whereKeyNot($this->contact_id)
            ->where(fn (Builder $query): Builder => $query
                ->when(filled($this->submitted_email), fn (Builder $query): Builder => $query->orWhere('email', $this->submitted_email))
                ->when(filled($this->submitted_phone), fn (Builder $query): Builder => $query->orWhere('phone_e164', $this->submitted_phone)))
            ->orderBy('id')
            ->get();
    }

    /**
     * The visits arranged for this enquiry (BR-TR-3), soonest first.
     *
     * @return HasMany<TourRequest, $this>
     */
    public function tourRequests(): HasMany
    {
        return $this->hasMany(TourRequest::class)->orderBy('scheduled_start_at');
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
