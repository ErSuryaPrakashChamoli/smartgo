<?php

namespace App\Http\Controllers\Web;

use App\Actions\Crm\SubmitEnquiry;
use App\Http\Controllers\Controller;
use App\Models\CustomerSegment;
use App\Models\Product;
use App\Services\Content\SiteNavigation;
use App\Services\Website\EnquiryForm;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The enquiry form (FR-BK-1, the approved enquiry journey): opened from a property, offering or
 * category page with that context, or on its own. Sending it stores an enquiry and nothing else: no
 * booking, reservation, price or payment. The confirmation page shows only the reference, from the
 * session, never the visitor's details.
 */
class EnquiryController extends Controller
{
    public function __construct(private EnquiryForm $form, private WorkspaceCatalogue $catalogue, private SiteNavigation $siteNavigation) {}

    public function create(Request $request): View
    {
        $navigation = $this->siteNavigation->present();
        $properties = $this->catalogue->listedProperties();
        $categories = Product::query()->shown()->get();
        $property = $properties->firstWhere('id', (int) $request->query('property'));
        $workspace = $categories->firstWhere('slug', (string) $request->query('workspace'));
        $city = $this->catalogue->comingSoonCities()->firstWhere('slug', (string) $request->query('city'));

        return view('pages.enquiries.create', [
            'isOpen' => $this->form->isOpen(),
            'notice' => $this->form->privacyNotice(),
            'started' => $this->form->startedToken(),
            'properties' => $properties,
            'categories' => $categories,
            'segments' => CustomerSegment::query()->offered()->get(),
            'teamSizes' => EnquiryForm::TEAM_SIZES,
            'selected' => ['property' => $property?->getKey(), 'workspace' => $workspace?->slug, 'city' => $city?->slug],
            'cityName' => $city?->name,
            'from' => $this->sameSitePath((string) $request->headers->get('referer')),
            'utm' => collect(['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'])
                ->mapWithKeys(fn (string $key): array => [$key => is_string($request->query($key)) ? mb_substr($request->query($key), 0, 120) : null])
                ->filter()->all(),
            'contact' => $navigation['contact'],
            'navigation' => $navigation,
            'canonical' => rtrim((string) config('app.url'), '/').'/enquire',
        ]);
    }

    public function store(Request $request, SubmitEnquiry $submitEnquiry): RedirectResponse
    {
        $lead = $submitEnquiry->handle($request->except(['_token']), $request->ip());

        return redirect()->route('enquiries.received')->with(['enquiry_received' => true, 'enquiry_reference' => $lead?->reference]);
    }

    public function received(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('enquiry_received', false)) {
            return redirect()->route('enquiries.create');
        }

        return view('pages.enquiries.received', [
            'reference' => $request->session()->get('enquiry_reference'),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    /**
     * The path of the page the visitor came from on this site, for the enquiry's attribution.
     */
    private function sameSitePath(string $referer): ?string
    {
        $host = parse_url($referer, PHP_URL_HOST);
        $path = parse_url($referer, PHP_URL_PATH);

        return is_string($host) && $host === parse_url((string) config('app.url'), PHP_URL_HOST) && is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            ? mb_substr($path, 0, 255)
            : null;
    }
}
