<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Content\SiteNavigation;
use App\Services\Website\SiteSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The website's search page (OD-143): a plain form that needs no JavaScript, and results from what the
 * public website already shows (SiteSearch). Result pages are never indexed.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, SiteSearch $siteSearch, SiteNavigation $siteNavigation): View
    {
        $query = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, SiteSearch::MAX_LENGTH) : '';

        return view('pages.search', [
            'query' => $query,
            'groups' => $siteSearch->search($query),
            'tooShort' => $query !== '' && mb_strlen($query) < SiteSearch::MIN_LENGTH,
            'navigation' => $siteNavigation->present(),
        ]);
    }
}
