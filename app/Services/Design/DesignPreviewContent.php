<?php

namespace App\Services\Design;

/**
 * Content for the Phase 2 design previews. Facts come only from owner decisions: cities (OD-03,
 * OD-16, OD-18, OD-22), properties (OD-08, OD-13), categories (OD-07) and segments (OD-04).
 * Workflows and units are the proposed defaults of DATABASE_ERD §4.1, not confirmed values.
 * Summaries and steps are draft copy; final copy is written in Phase 6. Database records replace
 * this class once cities, properties and categories exist (Phases 4–5).
 */
class DesignPreviewContent
{
    /**
     * @return list<array{name: string, slug: string, icon: string, summary: string, fulfilment: string, workflow: string, primaryAction: string, priceUnit: string, requiredDetails: list<string>, steps: list<string>}>
     */
    public function categories(): array
    {
        return [
            [
                'name' => 'Private cabins', 'slug' => 'private-cabins', 'icon' => 'building-office',
                'summary' => 'Lockable offices for teams that want their own room.',
                'fulfilment' => 'Monthly or longer agreement', 'workflow' => 'enquiry_quote',
                'primaryAction' => 'Request a proposal', 'priceUnit' => 'month',
                'requiredDetails' => ['Team size', 'Move-in date', 'Company name'],
                'steps' => ['Tell us your team size and move-in date', 'Visit the cabins that fit', 'Receive a written proposal', 'Sign the agreement and move in'],
            ],
            [
                'name' => 'Dedicated desks', 'slug' => 'dedicated-desks', 'icon' => 'computer-desktop',
                'summary' => 'Your own desk in a shared floor, kept for you.',
                'fulfilment' => 'Monthly or longer agreement', 'workflow' => 'enquiry_quote',
                'primaryAction' => 'Request a proposal', 'priceUnit' => 'desk per month',
                'requiredDetails' => ['Number of desks', 'Move-in date'],
                'steps' => ['Tell us how many desks you need', 'Visit and choose your spot', 'Receive a written proposal', 'Sign the agreement and start'],
            ],
            [
                'name' => 'Hot desks', 'slug' => 'hot-desks', 'icon' => 'squares-2x2',
                'summary' => 'Flexible seating: any open desk, when you need it.',
                'fulfilment' => 'Day pass or monthly plan', 'workflow' => 'request_then_approve',
                'primaryAction' => 'Request a day pass', 'priceUnit' => 'day',
                'requiredDetails' => ['Date', 'Number of people'],
                'steps' => ['Choose a property and date', 'Send your request', 'SmartGro confirms it', 'Arrive and take any open desk'],
            ],
            [
                'name' => 'Meeting rooms', 'slug' => 'meeting-rooms', 'icon' => 'user-group',
                'summary' => 'Rooms for client meetings and team discussions.',
                'fulfilment' => 'Booked by the hour', 'workflow' => 'request_then_approve',
                'primaryAction' => 'Request a booking', 'priceUnit' => 'hour',
                'requiredDetails' => ['Date and time', 'Number of attendees'],
                'steps' => ['Choose a room, date and time', 'Send your booking request', 'SmartGro confirms it', 'Meet'],
            ],
            [
                'name' => 'Conference and training rooms', 'slug' => 'conference-training-rooms', 'icon' => 'presentation-chart-bar',
                'summary' => 'Larger rooms for workshops, training and events.',
                'fulfilment' => 'Booked by the half day or day', 'workflow' => 'request_then_approve',
                'primaryAction' => 'Request a booking', 'priceUnit' => 'half day',
                'requiredDetails' => ['Date and session', 'Number of attendees'],
                'steps' => ['Choose a room and session', 'Send your booking request', 'SmartGro confirms it', 'Run your session'],
            ],
            [
                'name' => 'Virtual office', 'slug' => 'virtual-office', 'icon' => 'envelope',
                'summary' => 'A business address for your company, without a desk.',
                'fulfilment' => 'Monthly subscription', 'workflow' => 'enquiry_quote',
                'primaryAction' => 'Enquire', 'priceUnit' => 'month',
                'requiredDetails' => ['Company name', 'Business documents (SmartGro will confirm which)'],
                'steps' => ['Tell us about your company', 'SmartGro confirms the documents needed', 'Documents are checked', 'Your address is set up'],
            ],
            [
                'name' => 'Managed offices', 'slug' => 'managed-offices', 'icon' => 'building-office-2',
                'summary' => 'Dedicated office suites set up and run for enterprises.',
                'fulfilment' => 'Custom agreement', 'workflow' => 'enquiry_quote',
                'primaryAction' => 'Plan your office', 'priceUnit' => 'month',
                'requiredDetails' => ['Seat range', 'Preferred locations', 'Timeline'],
                'steps' => ['Share your requirements', 'Visit shortlisted spaces', 'Receive a tailored proposal', 'Agree the set-up plan'],
            ],
        ];
    }

    /**
     * @return array{name: string, slug: string, icon: string, summary: string, fulfilment: string, workflow: string, primaryAction: string, priceUnit: string, requiredDetails: list<string>, steps: list<string>}|null
     */
    public function category(string $slug): ?array
    {
        return collect($this->categories())->firstWhere('slug', $slug);
    }

    /**
     * The four confirmed properties: each address is its own property, all owned and operated.
     *
     * @return list<array{name: string, slug: string, area: string, city: string, citySlug: string}>
     */
    public function properties(): array
    {
        return [
            ['name' => 'A-70', 'slug' => 'a-70', 'area' => 'Sector 2', 'city' => 'Noida', 'citySlug' => 'noida'],
            ['name' => 'A-71', 'slug' => 'a-71', 'area' => 'Sector 2', 'city' => 'Noida', 'citySlug' => 'noida'],
            ['name' => 'A-23', 'slug' => 'a-23', 'area' => 'Sector 3', 'city' => 'Noida', 'citySlug' => 'noida'],
            ['name' => 'H-105', 'slug' => 'h-105', 'area' => 'Sector 62', 'city' => 'Noida', 'citySlug' => 'noida'],
        ];
    }

    /**
     * @return array{name: string, slug: string, area: string, city: string, citySlug: string}|null
     */
    public function property(string $citySlug, string $slug): ?array
    {
        return collect($this->properties())->first(fn (array $property): bool => $property['citySlug'] === $citySlug && $property['slug'] === $slug);
    }

    /**
     * Canonical cities (OD-16). "Operating" is derived from published properties; the others are
     * hidden by default (OD-18). Gurugram's preview shows the state an administrator would get by
     * making it visible before a property opens (OD-22); it is not a claim that SmartGro is there.
     *
     * @return list<array{name: string, slug: string, preview: 'operating'|'coming-soon-example'|'hidden', aliases: list<string>}>
     */
    public function cities(): array
    {
        return [
            ['name' => 'Noida', 'slug' => 'noida', 'preview' => 'operating', 'aliases' => []],
            ['name' => 'Gurugram', 'slug' => 'gurugram', 'preview' => 'coming-soon-example', 'aliases' => ['gurgaon']],
            ['name' => 'Delhi', 'slug' => 'delhi', 'preview' => 'hidden', 'aliases' => []],
            ['name' => 'Faridabad', 'slug' => 'faridabad', 'preview' => 'hidden', 'aliases' => []],
            ['name' => 'Ghaziabad', 'slug' => 'ghaziabad', 'preview' => 'hidden', 'aliases' => []],
        ];
    }

    /**
     * @return array{name: string, slug: string, preview: 'operating'|'coming-soon-example'|'hidden', aliases: list<string>}|null
     */
    public function city(string $slug): ?array
    {
        return collect($this->cities())->firstWhere('slug', $slug);
    }

    public function canonicalCitySlug(string $alias): ?string
    {
        return collect($this->cities())->first(fn (array $city): bool => in_array($alias, $city['aliases'], true))['slug'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function segments(): array
    {
        return [
            'Startups and small businesses',
            'Freelancers and independent professionals',
            'SMEs and growing companies',
            'Large enterprises and corporates',
            'Remote and hybrid employees',
            'Consultants and business travellers',
            'Companies seeking managed offices',
            'Businesses needing a virtual office or registered address',
        ];
    }
}
