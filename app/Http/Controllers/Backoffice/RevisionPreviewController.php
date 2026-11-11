<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\HomeBanner;
use App\Models\HomeHighlight;
use App\Models\NavigationMenu;
use App\Models\OfferingProfile;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\PropertyProfile;
use App\Models\RedirectMap;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\SpaceListing;
use App\Models\Testimonial;
use App\Services\Content\BlockLibrary;
use App\Services\Content\PageRenderer;
use App\Services\Content\SiteNavigation;
use App\Services\Website\HomeShowcase;
use App\Services\Website\LocationPages;
use App\Services\Website\SpaceListingPages;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * Shows a version of a page, of a property or workspace offering profile on its own page, of a home banner
 * or highlight in the home page's carousel or strip (OD-169), or of a menu in the real header and footer, exactly as it would be published (BR-CMS-4, FR-CMS-3). The link is signed
 * and expires, and the route sits inside the back office, so it also needs a signed-in staff member
 * with two-step login, back-office access and permission to view the content. The response is never
 * indexed, cached or sent as a referrer, and nothing is written.
 */
class RevisionPreviewController extends Controller
{
    public const string ROUTE = 'filament.admin.content.revisions.preview';

    public const int LIFETIME_MINUTES = 30;

    /**
     * Whether the version can be shown as a website page. Settings and the redirect list are reviewed
     * in their editor instead.
     */
    public static function isAvailableFor(Revision $revision): bool
    {
        return ! in_array($revision->revisionable_type, [(new SiteSetting)->getMorphClass(), (new RedirectMap)->getMorphClass()], true);
    }

    public static function url(Revision $revision): string
    {
        return URL::temporarySignedRoute(self::ROUTE, now()->addMinutes(self::LIFETIME_MINUTES), ['revision' => $revision]);
    }

    public function __invoke(Request $request, Revision $revision, PageRenderer $pageRenderer, SiteNavigation $siteNavigation, BlockLibrary $blockLibrary, WorkspaceCatalogue $catalogue, LocationPages $locationPages, HomeShowcase $showcase): Response
    {
        Gate::authorize('view', $revision);

        $content = $revision->revisionable;
        $preview = [
            'number' => $revision->number,
            'state' => $revision->state->label(),
            'expires_at' => Carbon::createFromTimestamp((int) $request->query('expires'))->timezone(config('backoffice.timezone'))->format('H:i T'),
        ];

        $view = match (true) {
            $content instanceof Page => view('pages.content.show', [
                ...$pageRenderer->present($revision->payload),
                'preview' => $preview,
                'robots' => null,
                'canonical' => null,
                'navigation' => $siteNavigation->present(),
            ]),
            $content instanceof Post => view('pages.insights.show', [
                'title' => (string) ($revision->payload['title'] ?? ''),
                'lead' => $revision->payload['excerpt'] ?? null,
                'blocks' => $blockLibrary->present($revision->payload['blocks'] ?? []),
                'topics' => PostTopic::inOrder($revision->payload['topic_ids'] ?? [])->map(fn (PostTopic $topic): array => ['name' => $topic->name, 'path' => null])->all(),
                'date' => null, 'preview' => $preview, 'navigation' => $siteNavigation->present(),
            ]),
            $content instanceof CaseStudy => view('pages.customers.show', [
                'title' => (string) ($revision->payload['title'] ?? ''),
                'lead' => $revision->payload['summary'] ?? null,
                'blocks' => $blockLibrary->present($revision->payload['blocks'] ?? []),
                'company' => $revision->payload['company_name'] ?? null, 'preview' => $preview, 'navigation' => $siteNavigation->present(),
            ]),
            $content instanceof Faq, $content instanceof Testimonial => view('pages.content.item-preview', [
                'heading' => $content instanceof Faq ? 'FAQ' : 'Testimonial',
                'faq' => $content instanceof Faq ? $revision->payload : null,
                'testimonial' => $content instanceof Testimonial ? [
                    'quote' => (string) ($revision->payload['quote'] ?? ''),
                    'person' => (string) ($revision->payload['person_name'] ?? ''),
                    'role' => collect([$revision->payload['person_title'] ?? null, $revision->payload['company_name'] ?? null])->filter()->join(', '),
                ] : null,
                'preview' => $preview, 'navigation' => $siteNavigation->present(),
            ]),
            $content instanceof HomeBanner => view('pages.content.item-preview', [
                'heading' => 'Home banner',
                'faq' => null, 'testimonial' => null,
                'banner' => ['slide' => $showcase->slide($revision->payload)],
                'preview' => $preview, 'navigation' => $siteNavigation->present(),
            ]),
            $content instanceof HomeHighlight => view('pages.content.item-preview', [
                'heading' => 'Highlight',
                'faq' => null, 'testimonial' => null,
                'highlight' => [
                    'icon' => array_key_exists((string) ($revision->payload['icon'] ?? ''), HomeHighlight::ICONS) ? (string) $revision->payload['icon'] : HomeHighlight::DEFAULT_ICON,
                    'text' => (string) ($revision->payload['text'] ?? ''),
                    'sample' => (bool) ($revision->payload['is_sample'] ?? false),
                ],
                'preview' => $preview, 'navigation' => $siteNavigation->present(),
            ]),
            $content instanceof SpaceListing => view('pages.spaces.show', [
                'space' => app(SpaceListingPages::class)->present($content->newInstance()->forceFill([
                    'id' => $content->getKey(),
                    ...array_intersect_key($revision->payload, array_flip(['property_id', 'space_type_id', 'space_id', 'name', 'slug', 'summary', 'details', 'seats', 'features', 'media_asset_id', 'is_sample'])),
                ])),
                'preview' => $preview, 'canonical' => null, 'navigation' => $siteNavigation->present(),
            ]),
            $content instanceof PropertyProfile => $locationPages->property(
                $content->property->load(['city', 'microMarket', 'primaryBuilding', 'publishedOfferings.product', 'publishedOfferings.profile']),
                $catalogue->profile($revision->payload, $content->property),
                $preview,
            ),
            $content instanceof OfferingProfile => $locationPages->offering(
                $content->offering->load(['product', 'property.city', 'property.microMarket', 'property.primaryBuilding']),
                $catalogue->offeringProfile($revision->payload, $content->offering),
                $preview,
            ),
            $content instanceof NavigationMenu => view('pages.content.navigation-preview', [
                'menuName' => $content->name,
                'preview' => $preview,
                'navigation' => $siteNavigation->present([$content->handle => $revision->payload]),
            ]),
            default => abort(404),
        };

        return response($view)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
