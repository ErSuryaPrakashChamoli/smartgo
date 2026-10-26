<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Redirect;
use App\Services\Content\PageRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Answers any public path no other route claims: a published page first, then a live redirect,
 * otherwise 404. Registered as the fallback route, so existing routes are never affected, and only
 * published pages and live (approved) redirects are ever used.
 */
class PageController extends Controller
{
    public function __invoke(Request $request, PageRenderer $pageRenderer): Response|RedirectResponse
    {
        $path = '/'.trim($request->path(), '/');

        $page = Page::query()->published()->where('slug', ltrim($path, '/'))->first();

        if ($page !== null) {
            return response()->view('pages.content.show', [...$pageRenderer->present($page->toRevisionPayload()), 'preview' => null]);
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
