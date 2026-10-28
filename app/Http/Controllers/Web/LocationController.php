<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\CustomerSegment;
use App\Models\MicroMarket;
use App\Models\Offering;
use App\Models\Property;
use App\Services\Content\SiteNavigation;
use App\Services\Website\EnquiryForm;
use App\Services\Website\LocationPages;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Locations (INFORMATION_ARCHITECTURE §2, OD-17): the cities SmartGro shows, each city's properties on
 * the website by sector, a page per sector with a property on the website, a page per property and per
 * published workspace offering at it. Old city names redirect (OD-16); a visible city without a
 * property on the website shows a "Coming soon" page (OD-22); hidden cities, sectors without a property
 * on the website, properties not on the website and unpublished offerings do not exist publicly (404).
 * A city page can be narrowed to one workspace type, and two or three properties can be compared side
 * by side (FR-DISC-2, FR-DISC-4).
 */
class LocationController extends Controller
{
    /**
     * Properties a comparison shows at most (FR-DISC-4).
     */
    public const int MAX_COMPARED = 3;

    public function __construct(private WorkspaceCatalogue $catalogue, private SiteNavigation $siteNavigation, private EnquiryForm $enquiryForm, private LocationPages $pages) {}

    public function index(): View
    {
        $properties = $this->catalogue->listedProperties();

        return view('pages.locations.index', [
            'cities' => $this->catalogue->publicCities(),
            'propertiesByCity' => $properties->groupBy('city_id'),
            'canCompare' => $properties->count() > 1,
            'navigation' => $this->siteNavigation->present(),
            'canonical' => $this->canonical('/locations'),
        ]);
    }

    /**
     * Two or three properties on the website side by side, from their published records only, at an
     * address that can be shared (FR-DISC-4). Properties in the address that are no longer on the
     * website are left out and the visitor is told. Never indexed: the choice of properties is the
     * visitor's.
     */
    public function compare(Request $request): View
    {
        $properties = $this->catalogue->listedProperties();
        $requested = collect(is_array($request->query('locations')) ? $request->query('locations') : [])
            ->filter(fn (mixed $key): bool => is_string($key))
            ->unique()
            ->values();
        $byKey = $properties->keyBy(fn (Property $property): string => $this->compareKey($property));
        $chosen = $requested->map(fn (string $key): ?Property => $byKey->get($key))->filter()->values();
        $tooMany = $chosen->count() > self::MAX_COMPARED;

        return view('pages.locations.compare', [
            'compared' => $chosen->count() > 1 && ! $tooMany ? $this->comparison($chosen) : null,
            'chosenKeys' => $chosen->map(fn (Property $property): string => $this->compareKey($property))->all(),
            'missing' => $requested->count() - $chosen->count(),
            'tooMany' => $tooMany,
            'tooFew' => $requested->isNotEmpty() && $chosen->count() < 2,
            'choices' => $properties->map(fn (Property $property): array => ['key' => $this->compareKey($property), 'property' => $property])->all(),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    public function city(Request $request, string $city): View|RedirectResponse
    {
        $record = $this->findPublicCity($city);

        if ($record instanceof RedirectResponse) {
            return $record;
        }

        $properties = $this->catalogue->listedProperties($record);
        $navigation = $this->siteNavigation->present();

        if ($properties->isEmpty()) {
            return view('pages.locations.coming-soon', [
                'city' => $record,
                'notice' => $this->enquiryForm->privacyNotice(),
                'started' => $this->enquiryForm->startedToken(),
                'segments' => CustomerSegment::query()->offered()->get(),
                'teamSizes' => EnquiryForm::TEAM_SIZES,
                'contact' => $navigation['contact'],
                'navigation' => $navigation,
            ]);
        }

        $workspaceTypes = $properties->flatMap(fn (Property $property): Collection => $property->publishedOfferings->pluck('product'))
            ->unique('id')
            ->sortBy('sort_order')
            ->values();
        $workspace = $workspaceTypes->firstWhere('slug', $request->query('workspace'));

        return view('pages.locations.city', [
            'city' => $record,
            'sectors' => $this->sectors($workspace === null ? $properties : $properties->filter(fn (Property $property): bool => $property->publishedOfferings->contains('product_id', $workspace->getKey()))->values()),
            'workspaceTypes' => $workspaceTypes,
            'workspace' => $workspace,
            'canCompare' => $properties->count() > 1,
            'navigation' => $navigation,
            'canonical' => $this->canonical('/locations/'.$record->slug),
        ]);
    }

    public function area(string $city, string $area): View|RedirectResponse
    {
        $cityRecord = $this->findPublicCity($city, 'area/'.$area);

        if ($cityRecord instanceof RedirectResponse) {
            return $cityRecord;
        }

        $sectors = $this->sectors($this->catalogue->listedProperties($cityRecord));
        $sector = $sectors->first(fn (array $group): bool => $group['sector']?->slug === $area);

        abort_if($sector === null, 404);

        $navigation = $this->siteNavigation->present();

        return view('pages.locations.area', [
            'city' => $cityRecord,
            'sector' => $sector['sector'],
            'properties' => $sector['properties'],
            'otherSectors' => $sectors->pluck('sector')->filter(fn (?MicroMarket $other): bool => $other !== null && ! $other->is($sector['sector']))->values(),
            'contact' => $navigation['contact'],
            'navigation' => $navigation,
            'canonical' => $this->canonical($sector['sector']->path()),
        ]);
    }

    public function property(string $city, string $property): View|RedirectResponse
    {
        $cityRecord = $this->findPublicCity($city, $property);

        if ($cityRecord instanceof RedirectResponse) {
            return $cityRecord;
        }

        $properties = $this->catalogue->listedProperties($cityRecord);
        $record = $properties->firstWhere('slug', $property);

        abort_if($record === null, 404);

        return $this->pages->property($record, $this->catalogue->publishedProfile($record), null, $properties->reject(fn (Property $other): bool => $other->is($record))->values());
    }

    public function offering(string $city, string $property, string $workspace): View|RedirectResponse
    {
        $cityRecord = $this->findPublicCity($city, $property.'/'.$workspace);

        if ($cityRecord instanceof RedirectResponse) {
            return $cityRecord;
        }

        $record = $this->catalogue->listedProperties($cityRecord)->firstWhere('slug', $property);
        $offering = $record?->publishedOfferings->first(fn (Offering $offering): bool => $offering->product->slug === $workspace);

        abort_if($offering === null, 404);

        return $this->pages->offering($offering, $this->catalogue->offeringProfile($offering->profile->toRevisionPayload(), $offering), null);
    }

    /**
     * How a property is named in a comparison's address, e.g. noida/a-70.
     */
    private function compareKey(Property $property): string
    {
        return $property->city->slug.'/'.$property->slug;
    }

    /**
     * The compared properties with what the comparison shows about each, from published records.
     *
     * @param  Collection<int, Property>  $properties
     * @return list<array{key: string, property: Property, profile: array<string, mixed>|null, workspaceTypes: list<string>, openingHours: list<array{day: string, hours: string}>, enquiryHref: string, removeHref: string|null}>
     */
    private function comparison(Collection $properties): array
    {
        $keys = $properties->map(fn (Property $property): string => $this->compareKey($property));

        return $properties->map(fn (Property $property): array => [
            'key' => $this->compareKey($property),
            'property' => $property,
            'profile' => $this->catalogue->publishedProfile($property),
            'workspaceTypes' => $property->publishedOfferings->sortBy(fn (Offering $offering): int => $offering->product->sort_order)->map(fn (Offering $offering): string => $offering->product->name)->values()->all(),
            'openingHours' => $property->openingHours(),
            'enquiryHref' => route('enquiries.create', ['property' => $property->getKey()]),
            'removeHref' => $properties->count() > 2 ? route('locations.compare', ['locations' => $keys->reject(fn (string $key): bool => $key === $this->compareKey($property))->values()->all()]) : null,
        ])->all();
    }

    /**
     * The city if it is shown publicly; a permanent redirect for an old name; otherwise 404.
     */
    private function findPublicCity(string $slug, ?string $rest = null): City|RedirectResponse
    {
        $city = City::query()->where('slug', $slug)->first();

        if ($city === null) {
            $renamed = City::query()->whereJsonContains('aliases', $slug)->first();
            abort_if($renamed === null, 404);

            return redirect()->to('/locations/'.$renamed->slug.($rest === null ? '' : '/'.$rest), 301);
        }

        abort_unless($this->catalogue->publicCities()->contains('id', $city->id), 404);

        return $city;
    }

    /**
     * Listed properties grouped by sector, sectors by name and properties without one last.
     *
     * @param  Collection<int, Property>  $properties
     * @return Collection<int, array{sector: MicroMarket|null, properties: Collection<int, Property>}>
     */
    private function sectors(Collection $properties): Collection
    {
        return $properties
            ->groupBy(fn (Property $property): int => (int) $property->micro_market_id)
            ->map(fn (Collection $group): array => ['sector' => $group->first()->microMarket, 'properties' => $group->values()])
            ->sortBy(fn (array $group): string => $group['sector'] === null ? "\u{10FFFF}" : $group['sector']->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function canonical(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
