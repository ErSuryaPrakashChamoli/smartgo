<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Content\SiteNavigation;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Contracts\View\View;

/**
 * The public home page: workspace categories, open properties and published contact details, all
 * from records. Nothing is shown about prices or availability until that data exists.
 */
class HomeController extends Controller
{
    public function __invoke(WorkspaceCatalogue $catalogue, SiteNavigation $siteNavigation): View
    {
        $properties = $catalogue->listedProperties();
        $categories = $catalogue->categories($properties);
        $navigation = $siteNavigation->present();

        return view('pages.home', [
            'properties' => $properties,
            'propertiesBySector' => $properties->groupBy(fn ($property): string => trim(($property->microMarket?->name ?? '').', '.$property->city->name, ', ')),
            'cityNames' => $properties->pluck('city.name')->unique()->values()->all(),
            'categories' => $categories,
            'groups' => $catalogue->groups($categories),
            'contact' => $navigation['contact'],
            'navigation' => $navigation,
            'canonical' => rtrim((string) config('app.url'), '/').'/',
        ]);
    }
}
