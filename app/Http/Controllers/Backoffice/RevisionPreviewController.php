<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Revision;
use App\Services\Content\PageRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * Shows a version of a page exactly as it would be published (BR-CMS-4, FR-CMS-3). The link is signed
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

    public function __invoke(Request $request, Revision $revision, PageRenderer $pageRenderer): Response
    {
        Gate::authorize('view', $revision);

        abort_unless($revision->revisionable instanceof Page, 404);

        $expiresAt = Carbon::createFromTimestamp((int) $request->query('expires'))->timezone(config('backoffice.timezone'));

        return response()
            ->view('pages.content.show', [
                ...$pageRenderer->present($revision->payload),
                'preview' => [
                    'number' => $revision->number,
                    'state' => $revision->state->label(),
                    'expires_at' => $expiresAt->format('H:i T'),
                ],
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
