<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Website\Sitemap;
use Illuminate\Http\Response;

/**
 * What search engines read before the pages (FR-SEO-3): the crawler rules and the XML sitemap. In
 * production every page may be crawled and the sitemap is named; anywhere else nothing may be crawled,
 * and every response also carries "noindex" (SetSecurityHeaders; DEPLOYMENT_RUNBOOK: staging is never
 * indexed). The rules never name the back office or any private address: those pages say "noindex"
 * themselves, so their paths are not advertised (D-30).
 */
class SearchEngineController extends Controller
{
    public function robots(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow:', '', 'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Sitemap $sitemap): Response
    {
        return response($sitemap->xml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
