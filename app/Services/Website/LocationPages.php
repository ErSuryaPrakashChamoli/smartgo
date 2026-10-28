<?php

namespace App\Services\Website;

use App\Models\Offering;
use App\Models\Property;
use App\Services\Content\SiteNavigation;
use Illuminate\Contracts\View\View;

/**
 * Builds the property and offering pages, for visitors from published records and for staff as a
 * signed preview of one profile version (BR-CMS-4). A preview is never indexed and has no canonical
 * address.
 */
class LocationPages
{
    public function __construct(private WorkspaceCatalogue $catalogue, private SiteNavigation $siteNavigation) {}

    /**
     * The property page, live or as a staff preview of a profile version.
     *
     * @param  array<string, mixed>|null  $profile
     * @param  iterable<int, Property>  $nearby
     * @param  array{number: int, state: string, expires_at: string}|null  $preview
     */
    public function property(Property $property, ?array $profile, ?array $preview, iterable $nearby = []): View
    {
        $navigation = $this->siteNavigation->present();

        return view('pages.locations.property', [
            'property' => $property,
            'profile' => $profile,
            'offerings' => $property->publishedOfferings->sortBy(fn (Offering $offering): int => $offering->product->sort_order)->values()->map(fn (Offering $offering): array => [
                'offering' => $offering,
                'profile' => $this->catalogue->offeringProfile($offering->profile->toRevisionPayload(), $offering),
            ])->all(),
            'nearby' => $nearby,
            'enquiryHref' => route('enquiries.create', ['property' => $property->getKey()]),
            'mapHref' => $this->mapHref($property),
            'preview' => $preview,
            'contact' => $this->contactFor($property, $profile, $navigation['contact']),
            'navigation' => $navigation,
            'canonical' => $preview === null ? $this->canonical((string) $property->path()) : null,
            'structuredData' => $preview === null ? $this->structuredData($property) : null,
        ]);
    }

    /**
     * The offering page, live or as a staff preview of an offering profile version.
     *
     * @param  array<string, mixed>  $profile
     * @param  array{number: int, state: string, expires_at: string}|null  $preview
     */
    public function offering(Offering $offering, array $profile, ?array $preview): View
    {
        $navigation = $this->siteNavigation->present();
        $otherHere = $preview === null ? $this->catalogue->publishedOfferings(property: $offering->property)->reject(fn (Offering $other): bool => $other->is($offering))->values() : collect();

        return view('pages.locations.offering', [
            'offering' => $offering,
            'property' => $offering->property,
            'profile' => $profile,
            'otherHere' => $otherHere,
            'elsewhere' => $preview === null ? $this->catalogue->publishedOfferings(product: $offering->product)->reject(fn (Offering $other): bool => $other->property_id === $offering->property_id)->values() : collect(),
            'enquiryHref' => route('enquiries.create', ['property' => $offering->property_id, 'workspace' => $offering->product->slug]),
            'preview' => $preview,
            'contact' => $this->contactFor($offering->property, $this->catalogue->publishedProfile($offering->property), $navigation['contact']),
            'navigation' => $navigation,
            'canonical' => $preview === null ? $this->canonical((string) $offering->path()) : null,
        ]);
    }

    /**
     * The location's own phone, WhatsApp and email from the shown profile version, or SmartGro's main
     * published contact details when it has none.
     *
     * @param  array<string, mixed>|null  $profile
     * @param  list<array{label: string, text: string, href: string|null}>  $siteContact
     * @return array{title: string, intro: string|null, details: list<array{label: string, text: string, href: string|null}>}
     */
    private function contactFor(Property $property, ?array $profile, array $siteContact): array
    {
        $own = $this->siteNavigation->contactDetails(['phone' => $profile['phone'] ?? null, 'whatsapp' => $profile['whatsapp'] ?? null, 'email' => $profile['email'] ?? null]);

        return $own === []
            ? ['title' => 'Talk to SmartGro', 'intro' => null, 'details' => $siteContact]
            : ['title' => 'Talk to SmartGro '.$property->name, 'intro' => 'Questions about this location? Contact SmartGro '.$property->name.' directly.', 'details' => $own];
    }

    /**
     * Schema.org data for a live property page (FR-DISC-7): its name, address and, once recorded, the
     * building's coordinates (GeoCoordinates). Only what the page itself shows or links to.
     *
     * @return array<string, mixed>
     */
    private function structuredData(Property $property): array
    {
        $building = $property->primaryBuilding;
        $street = collect([$building?->address_line_1 ?? $property->name, $building?->address_line_2, $building?->locality ?? $property->microMarket?->name])->filter()->unique()->join(', ');

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => 'SmartGro '.$property->name,
            'url' => $this->canonical((string) $property->path()),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $street,
                'addressLocality' => $property->city->name,
                'postalCode' => $building?->postal_code,
                'addressCountry' => $building?->country_code ?? 'IN',
            ]),
            'geo' => $building?->latitude !== null && $building?->longitude !== null
                ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $building->latitude, 'longitude' => (float) $building->longitude]
                : null,
        ]);
    }

    /**
     * A map link for the recorded coordinates; nothing is loaded from the map service until it is opened.
     */
    private function mapHref(Property $property): ?string
    {
        $building = $property->primaryBuilding;

        return $building?->latitude !== null && $building?->longitude !== null
            ? 'https://www.openstreetmap.org/?mlat='.$building->latitude.'&mlon='.$building->longitude.'#map=18/'.$building->latitude.'/'.$building->longitude
            : null;
    }

    private function canonical(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
