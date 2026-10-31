<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Post;
use App\Models\PostTopic;
use App\Services\Content\BlockLibrary;
use App\Services\Content\LiveRedirects;
use App\Services\Content\SiteNavigation;
use App\Services\Website\SiteSearch;
use App\Services\Website\StructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Insights, customer stories and FAQs (FR-CMS-7, INFORMATION_ARCHITECTURE §2). Only published items are
 * shown; a missing item falls back to a live redirect, so changed addresses keep working (BR-CMS-6). A
 * list page past the last one does not exist, and a list with nothing in it is not indexed (FR-SEO-3).
 */
class PublishedContentController extends Controller
{
    /**
     * Articles per page of a list.
     */
    public const int PER_PAGE = 12;

    public function __construct(private SiteNavigation $siteNavigation, private BlockLibrary $blockLibrary) {}

    /**
     * Published articles, newest first, optionally narrowed to those whose title or summary contains what
     * the visitor typed (`?q=`, 2 to 100 characters, wildcards taken literally). A narrowed list is never
     * indexed and keeps its words across pages (OD-144).
     */
    public function insights(Request $request): View
    {
        $query = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, SiteSearch::MAX_LENGTH) : '';
        $isFiltered = mb_strlen($query) >= SiteSearch::MIN_LENGTH;
        $like = SiteSearch::likePattern($query);
        $posts = Post::query()->published()
            ->when($isFiltered, fn ($posts) => $posts->where(fn ($match) => $match->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(excerpt) LIKE ?', [$like])))
            ->latest('published_at')
            ->paginate(self::PER_PAGE)
            ->appends($isFiltered ? ['q' => $query] : []);

        abort_if($posts->currentPage() > max(1, $posts->lastPage()), 404);

        return view('pages.insights.index', [
            'posts' => $posts,
            'query' => $query,
            'isFiltered' => $isFiltered,
            'topics' => $this->topicsOnWebsite(),
            'canonical' => $this->canonical('/insights'.($posts->currentPage() > 1 ? '?page='.$posts->currentPage() : '')),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    /**
     * A topic's published articles, newest first. A topic without one has no page.
     */
    public function insightTopic(string $slug, LiveRedirects $liveRedirects): View|RedirectResponse
    {
        $topic = PostTopic::isAvailable() ? PostTopic::query()->where('slug', $slug)->first() : null;
        $posts = $topic?->publishedPosts()->latest('published_at')->paginate(self::PER_PAGE);

        if ($topic === null || $posts->total() === 0) {
            return $liveRedirects->respond('/insights/topic/'.$slug);
        }

        abort_if($posts->currentPage() > $posts->lastPage(), 404);

        return view('pages.insights.topic', [
            'topic' => $topic,
            'posts' => $posts,
            'canonical' => $this->canonical($topic->path().($posts->currentPage() > 1 ? '?page='.$posts->currentPage() : '')),
            'navigation' => $this->siteNavigation->present(),
        ]);
    }

    public function insight(string $slug, LiveRedirects $liveRedirects, StructuredData $structuredData): View|RedirectResponse
    {
        $post = Post::query()->published()->where('slug', $slug)->first();

        if ($post === null) {
            return $liveRedirects->respond('/insights/'.$slug);
        }

        // As the page shows it: headline, summary and publication date; written and published by SmartGro
        // (no staff member is named on the page, so none is named here). Sample articles get none (OD-143).
        $base = rtrim((string) config('app.url'), '/');
        $isSample = $post->isSample();
        if (! $isSample) {
            $structuredData->add('article', array_filter([
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $post->excerpt,
                'datePublished' => $post->published_at?->copy()->utc()->toAtomString(),
                'url' => $this->canonical((string) $post->path()),
                'mainEntityOfPage' => $this->canonical((string) $post->path()),
                'author' => ['@id' => $base.'/#organization'],
                'publisher' => ['@id' => $base.'/#organization'],
                'articleSection' => $post->topics()->pluck('name')->all() ?: null,
            ]));
        }

        return view('pages.insights.show', [
            ...$this->article($post->title, $post->excerpt, $post->blocks ?? []),
            'topics' => $post->topics()->map(fn (PostTopic $topic): array => ['name' => $topic->name, 'path' => $topic->path()])->all(),
            'date' => $post->published_at,
            'canonical' => $this->canonical((string) $post->path()),
            'navigation' => $this->siteNavigation->present(),
            'preview' => null,
            'isSample' => $isSample,
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
            'faqs' => Faq::query()->published()->general()->orderBy('sort_order')->orderBy('id')->get(),
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

    /**
     * Topics with at least one published article, by name; none on a database not migrated for topics.
     *
     * @return Collection<int, PostTopic>
     */
    private function topicsOnWebsite(): Collection
    {
        if (! PostTopic::isAvailable()) {
            return new Collection;
        }

        $ids = Post::query()->published()->pluck('topic_ids')->flatten()->unique()->filter()->values()->all();

        return PostTopic::query()->whereKey($ids)->orderBy('name')->get();
    }

    private function canonical(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }
}
