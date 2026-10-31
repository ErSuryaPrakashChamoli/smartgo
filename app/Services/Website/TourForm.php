<?php

namespace App\Services\Website;

use App\Models\Property;
use App\Models\TourRequest;
use Illuminate\Support\Collection;

/**
 * Whether visitors can request a tour on the website (FR-BK-2). Like the enquiry form it collects
 * personal data, so it opens only once the privacy notice is published (EnquiryForm, OD-111 c); and
 * only for a property on the website whose tour hours staff have entered (BR-TR-1, D-28). Never on a
 * database without the tour tables.
 */
class TourForm
{
    public function __construct(private EnquiryForm $enquiryForm, private WorkspaceCatalogue $catalogue) {}

    public function isOpen(): bool
    {
        return TourRequest::isAvailable() && $this->enquiryForm->isOpen();
    }

    /**
     * Whether a visitor can request a tour now: the form is open and a property on the website has
     * tour hours. One query, whatever the number of properties, so a link to the form never leads to
     * a form without a location.
     */
    public function isOffered(): bool
    {
        return $this->isOpen() && Property::query()->publiclyListed()->whereHas('tourRules')->exists();
    }

    /**
     * The properties on the website that offer tour times now.
     *
     * @return Collection<int, Property>
     */
    public function properties(): Collection
    {
        if (! $this->isOpen()) {
            return collect();
        }

        return $this->catalogue->listedProperties()->filter(fn (Property $property): bool => $property->tourRules()->exists())->values();
    }

    public function isOpenFor(Property $property): bool
    {
        return $this->properties()->contains(fn (Property $listed): bool => $listed->is($property));
    }
}
