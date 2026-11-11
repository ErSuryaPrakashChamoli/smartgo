<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SpaceListing;
use App\Services\Content\SiteNavigation;
use App\Services\Website\SpaceListingPages;
use Illuminate\Contracts\View\View;

/**
 * A listed space's own page, /spaces/{slug} (OD-177): only while it is published at a location that may be listed;
 * otherwise 404. Example spaces say so and are not indexed.
 */
class SpaceListingController extends Controller
{
    public function __invoke(string $slug, SpaceListingPages $pages, SiteNavigation $siteNavigation): View
    {
        $listing = SpaceListing::isAvailable() ? SpaceListing::query()->shown()->where('slug', $slug)->first() : null;
        abort_if($listing === null, 404);

        return view('pages.spaces.show', [
            'space' => $pages->present($listing),
            'preview' => null,
            'navigation' => $siteNavigation->present(),
            'canonical' => rtrim((string) config('app.url'), '/').'/spaces/'.$listing->slug,
        ]);
    }
}
