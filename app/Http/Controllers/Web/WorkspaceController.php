<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Offering;
use App\Models\Product;
use App\Services\Content\SiteNavigation;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Workspace categories (INFORMATION_ARCHITECTURE §2, §3.3): the hub, a page per category and per
 * category and city. A category or category-and-city page exists only while at least one published
 * offering does (BR-PRODCFG-4); otherwise it answers 404 and is not linked.
 */
class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceCatalogue $catalogue, private SiteNavigation $siteNavigation) {}

    public function index(): View
    {
        $properties = $this->catalogue->listedProperties();

        return view('pages.workspaces.index', [
            'categories' => $this->catalogue->categories($properties),
            'navigation' => $this->siteNavigation->present(),
            'canonical' => $this->canonical('/workspaces'),
        ]);
    }

    public function category(string $workspace): View
    {
        $product = $this->catalogue->categoryWithPage($workspace);

        abort_if($product === null, 404);

        return $this->page($product, $this->catalogue->publishedOfferings(product: $product), null);
    }

    public function categoryInCity(string $workspace, string $city): View
    {
        $product = $this->catalogue->categoryWithPage($workspace);
        $cityRecord = City::query()->where('slug', $city)->first();
        $offerings = $product === null || $cityRecord === null ? collect() : $this->catalogue->publishedOfferings(product: $product, city: $cityRecord);

        abort_if($offerings->isEmpty(), 404);

        return $this->page($product, $offerings, $cityRecord);
    }

    /**
     * @param  Collection<int, Offering>  $offerings
     */
    private function page(Product $product, Collection $offerings, ?City $city): View
    {
        $cities = $this->catalogue->publishedOfferings(product: $product)
            ->map(fn (Offering $offering): City => $offering->property->city)
            ->unique('id')->sortBy('name')->values();

        return view('pages.workspaces.category', [
            'product' => $product,
            'copy' => $this->catalogue->categoryCopy($product),
            'city' => $city,
            'cities' => $cities,
            'offerings' => $offerings->map(fn (Offering $offering): array => [
                'offering' => $offering,
                'profile' => $this->catalogue->offeringProfile($offering->profile->toRevisionPayload(), $offering),
            ])->all(),
            'others' => collect($this->catalogue->categories($this->catalogue->listedProperties()))->where('path', '!==', null)->reject(fn (array $category): bool => $category['key'] === $product->key)->values()->all(),
            'enquiryHref' => route('enquiries.create', ['workspace' => $product->slug]),
            'navigation' => $this->siteNavigation->present(),
            'canonical' => $this->canonical('/workspaces/'.$product->slug.($city === null ? '' : '/'.$city->slug)),
        ]);
    }

    private function canonical(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
