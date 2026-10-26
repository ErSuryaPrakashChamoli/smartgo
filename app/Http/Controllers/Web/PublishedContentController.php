<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Post;
use App\Services\Content\BlockLibrary;
use App\Services\Content\LiveRedirects;
use App\Services\Content\SiteNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Insights, customer stories and FAQs (FR-CMS-7, INFORMATION_ARCHITECTURE §2). Only published items are
 * shown; a missing item falls back to a live redirect, so changed addresses keep working (BR-CMS-6).
 */
class PublishedContentController extends Controller
{
    public function __construct(private SiteNavigation $siteNavigation, private BlockLibrary $blockLibrary) {}

    public function insights(Request $request): View
    {
        $posts = Post::query()->published()->latest('published_at')->paginate(12);

        return view('pages.insights.index', [
            'posts' => $posts,
            'canonical' => $this->canonical('/insights'.($posts->currentPage() > 1 ? '?page='.$posts->currentPage() : '')),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    public function insight(string $slug, LiveRedirects $liveRedirects): View|RedirectResponse
    {
        $post = Post::query()->published()->where('slug', $slug)->first();

        if ($post === null) {
            return $liveRedirects->respond('/insights/'.$slug);
        }

        return view('pages.insights.show', [
            ...$this->article($post->title, $post->excerpt, $post->blocks ?? []),
            'date' => $post->published_at,
            'canonical' => $this->canonical((string) $post->path()),
            'navigation' => $this->siteNavigation->present(),
            'preview' => null,
        ]);
    }

    public function customers(): View
    {
        return view('pages.customers.index', [
            'caseStudies' => CaseStudy::query()->published()->where('consent_on_file', true)->latest('published_at')->get(),
            'canonical' => $this->canonical('/customers'),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    public function customer(string $slug, LiveRedirects $liveRedirects): View|RedirectResponse
    {
        $caseStudy = CaseStudy::query()->published()->where('consent_on_file', true)->where('slug', $slug)->first();

        if ($caseStudy === null) {
            return $liveRedirects->respond('/customers/'.$slug);
        }

        return view('pages.customers.show', [
            ...$this->article($caseStudy->title, $caseStudy->summary, $caseStudy->blocks ?? []),
            'company' => $caseStudy->company_name,
            'canonical' => $this->canonical((string) $caseStudy->path()),
            'navigation' => $this->siteNavigation->present(),
            'preview' => null,
        ]);
    }

    public function faqs(): View
    {
        return view('pages.faqs', [
            'faqs' => Faq::query()->published()->orderBy('sort_order')->orderBy('id')->get(),
            'canonical' => $this->canonical('/faqs'),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return array{title: string, lead: string|null, blocks: list<array<string, mixed>>}
     */
    private function article(?string $title, ?string $lead, array $blocks): array
    {
        return ['title' => (string) $title, 'lead' => $lead, 'blocks' => $this->blockLibrary->present($blocks)];
    }

    private function canonical(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
