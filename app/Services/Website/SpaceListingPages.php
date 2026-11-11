<?php

namespace App\Services\Website;

use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\PropertyProfile;
use App\Models\SpaceListing;
use Illuminate\Support\Collection;

/**
 * What the website shows of space listings (OD-177): a space's own page, and the spaces of each space type for the
 * Spaces menu, grouped by location. Only published listings at a location that may be listed are shown (BR-CMS-9).
 */
class SpaceListingPages
{
    public function __construct(private EnquiryForm $enquiryForm, private TourForm $tourForm) {}

    /**
     * A space's page: the live listing, or a version's payload for a staff preview.
     *
     * @return array{name: string, summary: string|null, paragraphs: list<string>, features: list<string>, seats: int|null, sample: bool, type: string|null, location: array{name: string, area: string, path: string|null}, photo: array{src: string, srcset: string, alt: string, credit: string|null, dimensions: array{width: int, height: int}|null, label: string|null}|null, enquiryHref: string|null, tourHref: string|null, others: list<array{name: string, href: string, location: string}>}
     */
    public function present(SpaceListing $listing): array
    {
        $listing->loadMissing(['property.city', 'property.microMarket', 'property.profile', 'spaceType.products']);
        // The enquiry form gets the workspace type chosen only when the space type belongs to exactly one; otherwise
        // the visitor chooses it there.
        $products = $listing->spaceType?->products->filter(fn (Product $product): bool => $product->is_active) ?? collect();
        $product = $products->count() === 1 ? $products->first() : null;
        $property = $listing->property;
        $isListed = $property?->isPubliclyListed() === true;

        return [
            'name' => (string) $listing->name,
            'summary' => $listing->summary,
            'paragraphs' => array_values(array_filter(array_map(trim(...), preg_split('/\R\s*\R/', (string) $listing->details) ?: []), filled(...))),
            'features' => array_values(array_filter($listing->features ?? [], filled(...))),
            'seats' => $listing->seats,
            'sample' => (bool) $listing->is_sample,
            'type' => $listing->spaceType?->name,
            'location' => [...$listing->locationLabel(), 'path' => $isListed ? $property->path() : null],
            'photo' => $this->photo($listing),
            'enquiryHref' => $this->enquiryForm->isOpen() ? route('enquiries.create', array_filter(['workspace' => $product?->slug, 'property' => $isListed ? $property->getKey() : null]), false) : null,
            'tourHref' => $this->tourForm->isOffered() ? route('tours.create', array_filter(['property' => $isListed ? $property->getKey() : null]), false) : null,
            'others' => $listing->space_type_id === null ? [] : SpaceListing::query()->shown()
                ->where('space_type_id', $listing->space_type_id)
                ->when($listing->getKey() !== null, fn ($query) => $query->whereKeyNot($listing->getKey()))
                ->with(['property.city', 'property.microMarket'])
                ->orderByRaw('property_id = ? desc', [(int) $listing->property_id])
                ->orderBy('sort_order')->orderBy('name')
                ->limit(6)->get()
                ->map(fn (SpaceListing $other): array => ['name' => (string) $other->name, 'href' => (string) $other->path(), 'location' => $other->locationLabel()['name']])
                ->all(),
        ];
    }

    /**
     * The space's photo as the page shows it (OD-177 e), while its licence holds and its file exists: photography of
     * its location, or a stock photo labelled as not the location, as on location pages (OD-176).
     *
     * @return array{src: string, srcset: string, alt: string, credit: string|null, dimensions: array{width: int, height: int}|null, label: string|null}|null
     */
    private function photo(SpaceListing $listing): ?array
    {
        $asset = SpaceListing::hasPhotos() && $listing->media_asset_id !== null ? MediaAsset::query()->find($listing->media_asset_id) : null;
        $isStock = $asset !== null && in_array($asset->origin, PropertyProfile::SAMPLE_PHOTO_ORIGINS, true);

        if ($asset === null || (! $isStock && (int) $asset->depicts_property_id !== (int) $listing->property_id) || $asset->isLicenceExpired() || $asset->file() === null) {
            return null;
        }

        return [
            'src' => $asset->renditionPath('w960'),
            'srcset' => collect(MediaAsset::RENDITIONS)->map(fn (int $width, string $name): string => $asset->renditionPath($name).' '.$width.'w')->join(', '),
            'alt' => $asset->is_decorative ? '' : (string) $asset->alt_text,
            'credit' => $asset->credit_line,
            'dimensions' => $asset->dimensions(),
            'label' => $isStock ? WorkspaceCatalogue::STOCK_PHOTO_LABEL : null,
        ];
    }

    /**
     * The shown spaces of each given space type, in location order then position, ready for the menu: each link
     * carries its location, so the menu can group them.
     *
     * @param  list<int>  $spaceTypeIds
     * @return array<int, list<array{label: string, href: string, location: string, area: string, sample: bool}>>
     */
    public function menu(array $spaceTypeIds): array
    {
        if ($spaceTypeIds === [] || ! SpaceListing::isAvailable()) {
            return [];
        }

        /** @var Collection<int, SpaceListing> $listings */
        $listings = SpaceListing::query()->shown()
            ->whereIn('space_type_id', array_unique($spaceTypeIds))
            ->with(['property.city', 'property.microMarket'])
            ->get(['id', 'property_id', 'space_type_id', 'name', 'slug', 'is_sample', 'sort_order', 'status']);

        return $listings
            ->sortBy([fn (SpaceListing $a, SpaceListing $b): int => strnatcmp((string) $a->property?->code, (string) $b->property?->code), ['sort_order', 'asc'], fn (SpaceListing $a, SpaceListing $b): int => strnatcmp((string) $a->name, (string) $b->name)])
            ->groupBy('space_type_id')
            ->map(fn (Collection $group): array => $group->map(fn (SpaceListing $listing): array => [
                'label' => (string) $listing->name,
                'href' => '/spaces/'.$listing->slug,
                'location' => $listing->locationLabel()['name'],
                'area' => $listing->locationLabel()['area'],
                'sample' => (bool) $listing->is_sample,
            ])->values()->all())
            ->all();
    }
}
