<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Offering;
use App\Models\Product;
use App\Services\Website\EnquiryForm;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The home page's workspace finder, a plain form that needs no JavaScript: it takes the chosen workspace
 * type and location and sends the visitor to the most specific published page for them. Nothing is
 * searched for availability or price, because there is no verified inventory yet (OD-113 d): the finder
 * only leads to what SmartGro has published, or to the enquiry form with the choice filled in. A type with
 * neither leads to its own card on the workspaces page, the destination the Spaces menu uses (OD-174), never
 * back to the home page (OD-175). Unknown, inactive or unlisted choices are ignored, and every destination
 * is an address on this website.
 */
class WorkspaceFinderController extends Controller
{
    public function __invoke(Request $request, WorkspaceCatalogue $catalogue, EnquiryForm $enquiryForm): RedirectResponse
    {
        $slug = $request->query('workspace');
        $workspace = is_string($slug) && $slug !== '' ? Product::query()->shown()->where('slug', $slug)->first() : null;
        $location = $request->query('location');
        $properties = $catalogue->listedProperties();
        $property = is_string($location) && ctype_digit($location) ? $properties->firstWhere('id', (int) $location) : null;

        if ($workspace === null) {
            return redirect($property?->path() ?? match (true) {
                Product::query()->shown()->exists() => '/workspaces',
                $properties->isNotEmpty() => '/locations',
                default => '/#contact',
            });
        }

        $offering = $property?->publishedOfferings->first(fn (Offering $offering): bool => (int) $offering->product_id === (int) $workspace->getKey());
        $enquiry = $enquiryForm->isOpen()
            ? route('enquiries.create', array_filter(['workspace' => $workspace->slug, 'property' => $property?->getKey()]), false)
            : null;

        return redirect($offering?->path()
            ?? ($catalogue->categoryWithPage($workspace->slug) !== null ? '/workspaces/'.$workspace->slug : null)
            ?? $enquiry
            ?? $property?->path()
            ?? '/workspaces#'.$workspace->slug);
    }
}
