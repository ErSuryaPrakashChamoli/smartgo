<?php

namespace App\Http\Middleware;

use App\Models\MissingPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts website addresses that answer "not found" (FR-SEO-4's 404 monitor): GET and HEAD requests only,
 * the path without its query (a query can carry personal data), at most 255 characters. Not the back
 * office, Livewire or built assets; removed addresses (410) are intended and not counted. Nothing about
 * the visitor is kept. A failure here never changes the answer the visitor gets. Not counted on a
 * database without the table (the owner's server, OD-120).
 */
class RecordMissingPage
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 404 && $request->isMethodSafe() && ! $this->isExcluded($request)) {
            try {
                $this->record(mb_substr('/'.trim($request->path(), '/'), 0, 255));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }

    private function record(string $path): void
    {
        if (! MissingPage::isAvailable()) {
            return;
        }

        $now = now();

        if (! MissingPage::query()->where('path', $path)->exists() && MissingPage::query()->count() >= MissingPage::MAX_PATHS) {
            return;
        }

        MissingPage::query()->upsert(
            [['path' => $path, 'hits' => 1, 'first_seen_at' => $now, 'last_seen_at' => $now]],
            ['path'],
            ['hits' => DB::raw('hits + 1'), 'last_seen_at' => $now],
        );
    }

    private function isExcluded(Request $request): bool
    {
        $backoffice = trim((string) config('backoffice.path'), '/');

        return $request->is($backoffice, $backoffice.'/*', 'livewire*', 'build/*', 'up');
    }
}
