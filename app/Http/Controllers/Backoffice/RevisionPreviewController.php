<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Services\Content\BlockLibrary;
use App\Services\Content\PageRenderer;
use App\Services\Content\SiteNavigation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * Shows a version of a page, or of a menu in the real header and footer, exactly as it would be
 * published (BR-CMS-4, FR-CMS-3). The link is signed
 * and expires, and the route sits inside the back office, so it also needs a signed-in staff member
 * with two-step login, back-office access and permission to view the content. The response is never
 * indexed, cached or sent as a referrer, and nothing is written.
 */
class RevisionPreviewController extends Controller
{
    public const string ROUTE = 'filament.admin.content.revisions.preview';

    public const int LIFETIME_MINUTES = 30;

    public static function url(Revision $revision): string
    {
        return URL::temporarySignedRoute(self::ROUTE, now()->addMinutes(self::LIFETIME_MINUTES), ['revision' => $revision]);
    }

    public function __invoke(Request $request, Revision $revision, PageRenderer $pageRenderer, SiteNavigation $siteNavigation, BlockLibrary $blockLibrary): Response
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
