<?php

namespace App\Services\Website;

use App\Models\City;
use App\Models\MediaAsset;
use App\Models\Offering;
use App\Models\Product;
use App\Models\Property;
use App\Models\PropertyProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What the public website shows about workspace categories, properties and offerings, built only from
 * published records: active categories (OD-07), properties whose state and published profile allow it
 * (BR-AV-6), and offerings with a published profile at those properties. The one-line descriptions say
 * what a workspace type is, never what it costs or whether it is free. Capacity, availability and
 * prices are never shown: they come with inventory and approved pricing.
 */
class WorkspaceCatalogue
{
    /**
     * Groups shown as sections on the home page, in order, by category key.
     *
     * @var array<string, array{title: string, anchor: string, keys: list<string>, description: string}>
     */
    public const array GROUPS = [
        'private' => ['title' => 'Private offices and cabins', 'anchor' => 'private-offices', 'keys' => ['private_office'],
            'description' => 'A lockable office or cabin for your team, with room to focus and to hold private conversations.'],
        'desks' => ['title' => 'Dedicated and hot desks', 'anchor' => 'desks', 'keys' => ['dedicated_desk', 'hot_desk'],
            'description' => 'A desk kept for you in a shared workspace, or flexible seating where you take any open desk when you need one.'],
        'meetings' => ['title' => 'Meeting and conference rooms', 'anchor' => 'meeting-rooms', 'keys' => ['meeting_room', 'conference_room'],
            'description' => 'Rooms for client meetings and team discussions, and larger rooms for workshops, training and events.'],
        'virtual' => ['title' => 'Virtual office', 'anchor' => 'virtual-office', 'keys' => ['virtual_office'],
            'description' => 'A business address for your company without renting a desk.'],
        'managed' => ['title' => 'Managed offices', 'anchor' => 'managed-offices', 'keys' => ['managed_office'],
            'description' => 'Office space set up and run for your organisation, for teams that want their own floor or suite.'],
    ];

    /**
     * A one-line description and icon per category key; unknown keys get neither.
     *
     * @var array<string, array{icon: string, summary: string}>
     */
    private const array CATEGORY_COPY = [
        'private_office' => ['icon' => 'building-office', 'summary' => 'Lockable offices and cabins for teams that want a room of their own.'],
        'dedicated_desk' => ['icon' => 'computer-desktop', 'summary' => 'A desk of your own in a shared workspace, kept for you.'],
        'hot_desk' => ['icon' => 'squares-2x2', 'summary' => 'Flexible seating: take any open desk when you need one.'],
        'meeting_room' => ['icon' => 'user-group', 'summary' => 'Rooms for client meetings and team discussions.'],
        'conference_room' => ['icon' => 'presentation-chart-bar', 'summary' => 'Larger rooms for workshops, training and events.'],
        'virtual_office' => ['icon' => 'envelope', 'summary' => 'A business address for your company, without a desk.'],
        'managed_office' => ['icon' => 'building-office-2', 'summary' => 'Office space set up and run for your organisation.'],
    ];

    /**
     * Open properties with what the website shows about them.
     *
     * @return Collection<int, Property>
     */
    public function listedProperties(?City $city = null): Collection
    {
        return Property::query()
            ->publiclyListed()
            ->when($city !== null, fn ($query) => $query->whereBelongsTo($city))
            ->with(['city', 'microMarket', 'primaryBuilding', 'profile', 'publishedOfferings.product', 'publishedOfferings.profile'])
            ->orderBy('city_id')
            ->orderBy('code')
            ->get();
    }

    /**
     * Published offerings, optionally of one property, category or city, with what their pages need.
     *
     * @return Collection<int, Offering>
     */
    public function publishedOfferings(?Property $property = null, ?Product $product = null, ?City $city = null): Collection
    {
        return Offering::query()
            ->publiclyListed()
            ->when($property !== null, fn (Builder $query) => $query->where('property_id', $property?->getKey()))
            ->when($product !== null, fn (Builder $query) => $query->where('product_id', $product?->getKey()))
            ->when($city !== null, fn (Builder $query) => $query->whereHas('property', fn (Builder $query) => $query->where('city_id', $city?->getKey())))
            ->with(['product', 'profile', 'property.city', 'property.microMarket', 'property.profile'])
            ->get()
            ->sortBy(fn (Offering $offering): string => sprintf('%05d-%s-%05d', $offering->product->sort_order, $offering->property->code, $offering->getKey()))
            ->values();
    }

    /**
     * Active categories with their description, their page when one exists (at least one published
     * offering, BR-PRODCFG-4), and the published offerings at the given properties.
     *
     * @param  Collection<int, Property>  $properties  listed properties, loaded by listedProperties()
     * @return list<array{key: string, slug: string, name: string, icon: string|null, summary: string|null, group: string|null, path: string|null, offeredAt: list<array{name: string, path: string}>}>
     */
    public function categories(Collection $properties): array
    {
        $offerings = $properties->flatMap(fn (Property $property): Collection => $property->publishedOfferings);

        return Product::query()->shown()->get()->map(fn (Product $product): array => [
            'key' => $product->key,
            'slug' => $product->slug,
            'name' => $product->name,
            'icon' => self::CATEGORY_COPY[$product->key]['icon'] ?? null,
            'summary' => self::CATEGORY_COPY[$product->key]['summary'] ?? null,
            'group' => collect(self::GROUPS)->search(fn (array $group): bool => in_array($product->key, $group['keys'], true)) ?: null,
            'path' => $offerings->contains('product_id', $product->getKey()) ? '/workspaces/'.$product->slug : null,
            'offeredAt' => $offerings
                ->where('product_id', $product->getKey())
                ->map(fn (Offering $offering): array => ['name' => $offering->property->name, 'path' => (string) $offering->path()])
                ->values()
                ->all(),
        ])->all();
    }

    /**
     * A shown category with at least one published offering, by its address; null otherwise.
     */
    public function categoryWithPage(string $slug): ?Product
    {
        return Product::query()->shown()->where('slug', $slug)
            ->whereHas('offerings', fn (Builder $query) => $query->publiclyListed())
            ->first();
    }

    /**
     * The one-line description and icon of a category.
     *
     * @return array{icon: string|null, summary: string|null}
     */
    public function categoryCopy(Product $product): array
    {
        return ['icon' => self::CATEGORY_COPY[$product->key]['icon'] ?? null, 'summary' => self::CATEGORY_COPY[$product->key]['summary'] ?? null];
    }

    /**
     * The home page sections, each with its active categories; a group without one is left out.
     *
     * @param  list<array<string, mixed>>  $categories
     * @return list<array{title: string, anchor: string, description: string, categories: list<array<string, mixed>>}>
     */
    public function groups(array $categories): array
    {
        return collect(self::GROUPS)
            ->map(fn (array $group, string $key): array => [
                'title' => $group['title'],
                'anchor' => $group['anchor'],
                'description' => $group['description'],
                'categories' => array_values(array_filter($categories, fn (array $category): bool => $category['group'] === $key)),
            ])
            ->filter(fn (array $group): bool => $group['categories'] !== [])
            ->values()
            ->all();
    }

    /**
     * A property profile as the property page shows it: the published version, or a version in a staff
     * preview.
     *
     * @param  array<string, mixed>  $payload
     * @return array{headline: string|null, summary: string|null, gettingThere: string|null, gettingHere: list<array{label: string, text: string}>, phone: string|null, whatsapp: string|null, email: string|null, seoTitle: string|null, seoDescription: string|null, photos: list<array<string, mixed>>}
     */
    public function profile(array $payload, Property $property): array
    {
        return [
            'headline' => $payload['headline'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'gettingThere' => $payload['getting_there'] ?? null,
            'gettingHere' => $this->gettingHere($payload),
            'phone' => $payload['phone'] ?? null,
            'whatsapp' => $payload['whatsapp'] ?? null,
            'email' => $payload['email'] ?? null,
            'seoTitle' => $payload['seo_title'] ?? null,
            'seoDescription' => $payload['seo_description'] ?? null,
            'photos' => $this->photos($payload, $property),
        ];
    }

    /**
     * A location's "Getting here" details that were filled in, labelled, in a fixed order (FR-DISC-7).
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{label: string, text: string}>
     */
    private function gettingHere(array $payload): array
    {
        $minutes = (int) ($payload['metro_walk_minutes'] ?? 0);

        return array_values(array_filter([
            filled($payload['nearest_metro'] ?? null) ? ['label' => 'Nearest metro station', 'text' => (string) $payload['nearest_metro']] : null,
            filled($payload['nearest_metro'] ?? null) && $minutes > 0 ? ['label' => 'Walk from the metro', 'text' => $minutes.' '.str('minute')->plural($minutes)] : null,
            filled($payload['buses'] ?? null) ? ['label' => 'Buses', 'text' => (string) $payload['buses']] : null,
            filled($payload['parking'] ?? null) ? ['label' => 'Parking', 'text' => (string) $payload['parking']] : null,
            filled($payload['accessibility'] ?? null) ? ['label' => 'Accessibility', 'text' => (string) $payload['accessibility']] : null,
        ]));
    }

    /**
     * The published profile of a property, as the property page shows it.
     *
     * @return array{headline: string|null, summary: string|null, gettingThere: string|null, gettingHere: list<array{label: string, text: string}>, phone: string|null, whatsapp: string|null, email: string|null, seoTitle: string|null, seoDescription: string|null, photos: list<array<string, mixed>>}|null
     */
    public function publishedProfile(Property $property): ?array
    {
        return $property->profile?->isPublished() ? $this->profile($property->profile->toRevisionPayload(), $property) : null;
    }

    /**
     * A workspace offering profile as its pages show it: the published version, or a version in a
     * staff preview.
     *
     * @param  array<string, mixed>  $payload
     * @return array{headline: string|null, description: string|null, included: list<string>, notIncluded: list<string>, seoTitle: string|null, seoDescription: string|null, photos: list<array<string, mixed>>}
     */
    public function offeringProfile(array $payload, Offering $offering): array
    {
        return [
            'headline' => $payload['headline'] ?? null,
            'description' => $payload['description'] ?? null,
            'included' => array_values($payload['included'] ?? []),
            'notIncluded' => array_values($payload['not_included'] ?? []),
            'seoTitle' => $payload['seo_title'] ?? null,
            'seoDescription' => $payload['seo_description'] ?? null,
            'photos' => $this->photos($payload, $offering->property),
        ];
    }

    /**
     * The main photo and further photos of a profile. Each is checked again here, so one that is no
     * longer real photography of this property or whose licence has expired is left out (BR-MED-2,
     * BR-MED-3).
     *
     * @param  array<string, mixed>  $payload
     * @return list<array{src: string, srcset: string, alt: string, credit: string|null, dimensions: array{width: int, height: int}|null}>
     */
    public function photos(array $payload, Property $property): array
    {
        $assetIds = array_values(array_unique(array_filter([$payload['hero_asset_id'] ?? null, ...($payload['gallery_asset_ids'] ?? [])], is_numeric(...))));
        $assets = MediaAsset::query()->with('media')->whereKey($assetIds)
            ->whereIn('origin', PropertyProfile::PHOTO_ORIGINS)
            ->where('depicts_property_id', $property->getKey())
            ->get()->keyBy('id');

        return collect($assetIds)
            ->map(fn (int|string $id): ?MediaAsset => $assets->get((int) $id))
            ->filter(fn (?MediaAsset $asset): bool => $asset !== null && ! $asset->isLicenceExpired() && $asset->file() !== null)
            ->map(fn (MediaAsset $asset): array => [
                'src' => $asset->renditionPath('w960'),
                'srcset' => collect(MediaAsset::RENDITIONS)->map(fn (int $width, string $name): string => $asset->renditionPath($name).' '.$width.'w')->join(', '),
                'alt' => $asset->is_decorative ? '' : (string) $asset->alt_text,
                'credit' => $asset->credit_line,
                'dimensions' => $asset->dimensions(),
            ])
            ->values()
            ->all();
    }

    /**
     * Cities switched visible without a property on the website yet: they show a "Coming soon" page
     * with a register-interest form (OD-22, BR-PROP-4).
     *
     * @return Collection<int, City>
     */
    public function comingSoonCities(): Collection
    {
        return City::query()->where('is_publicly_visible', true)->whereDoesntHave('properties', fn (Builder $query) => $query->publiclyListed())->orderBy('name')->get();
    }

    /**
     * Cities shown on the website: those with an open property, and those switched visible before
     * one opens (OD-18, OD-22).
     *
     * @return Collection<int, City>
     */
    public function publicCities(): Collection
    {
        return City::query()
            ->where(fn ($query) => $query->operating()->orWhere('is_publicly_visible', true))
            ->withCount(['properties as open_properties_count' => fn ($query) => $query->publiclyListed()])
            ->orderByDesc('open_properties_count')
            ->orderBy('name')
            ->get();
    }
}
