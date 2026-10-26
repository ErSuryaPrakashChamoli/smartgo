<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\SeoMetadata;
use App\Services\Content\PageRenderer;
use App\Services\Content\SiteNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Answers any public path no other route claims: a published page first, then a live redirect,
 * otherwise 404. Registered as the fallback route, so existing routes are never affected, and only
 * published pages, menus and redirects are ever used. The canonical URL is built from APP_URL and the
 * page's current address, never from the request's host header.
 */
class PageController extends Controller
{
    public function __invoke(Request $request, PageRenderer $pageRenderer, SiteNavigation $siteNavigation): Response|RedirectResponse
    {
        $path = '/'.trim($request->path(), '/');

        $page = Page::query()->published()->where('slug', ltrim($path, '/'))->first();

        if ($page !== null) {
            $content = $pageRenderer->present($page->toRevisionPayload());

            return response()->view('pages.content.show', [
                ...$content,
                'preview' => null,
                'robots' => $content['indexable'] ? null : SeoMetadata::NOINDEX,
                'canonical' => rtrim((string) config('app.url'), '/').$page->path(),
                'shareImage' => $content['shareImagePath'] === null ? null : rtrim((string) config('app.url'), '/').$content['shareImagePath'],
                'navigation' => $siteNavigation->present(),
            ]);
        }

        $redirect = Redirect::findFor($path);

        if ($redirect === null || ! $redirect->isServable()) {
            abort(404);
        }

        $redirect->recordHit();

        abort_if($redirect->status_code === 410, 410);

        return redirect()->to((string) $redirect->to_path, $redirect->status_code);
    }
}
