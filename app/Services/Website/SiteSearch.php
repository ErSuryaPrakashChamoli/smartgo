<?php

namespace App\Services\Website;

use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Property;
use App\Services\Content\PagePayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The website's search (OD-143): finds what the public website already shows, through the same scopes as
 * its pages, so nothing unpublished, unlisted, inactive or without consent is ever found. Locations
 * (listed properties), workspace types (active categories), articles, general questions, customer stories
 * with consent, and published pages; sample pages and articles are labelled as such. Plain substring
 * matching on a few fields: enough for SmartGro's amount of content, with no search service or package.
 */
class SiteSearch
{
    public const int MIN_LENGTH = 2;

    public const int MAX_LENGTH = 100;

    public const int PER_GROUP = 8;

    public function __construct(private WorkspaceCatalogue $catalogue) {}

    /**
     * Results grouped by kind, in a fixed order; empty groups are left out.
     *
     * @return list<array{label: string, results: list<array{title: string, href: string, summary: string|null, badge: string|null, sample: bool}>}>
     */
    public function search(string $query): array
    {
        $term = trim(preg_replace('/\s+/', ' ', $query) ?? '');

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $like = self::likePattern($term);

        return array_values(array_filter([
            ['label' => 'Locations', 'results' => $this->locations($like)],
            ['label' => 'Workspace types', 'results' => $this->workspaceTypes($like)],
            ['label' => 'Pages', 'results' => $this->pages($like)],
            ['label' => 'Articles', 'results' => $this->articles($like)],
            ['label' => 'Customer stories', 'results' => $this->customerStories($like)],
            ['label' => 'Questions', 'results' => $this->questions($like)],
        ], fn (array $group): bool => $group['results'] !== []));
    }

    /**
     * A case-insensitive "contains" pattern for LIKE, with the typed wildcards taken literally.
     */
    public static function likePattern(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(mb_substr($term, 0, self::MAX_LENGTH))).'%';
    }

    /**
     * @return list<array{title: string, href: string, summary: string|null, badge: string|null, sample: bool}>
     */
    private function locations(string $like): array
    {
        return Property::query()->publiclyListed()
            ->where(fn (Builder $query) => $query
                ->whereRaw('LOWER(properties.name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(properties.code) LIKE ?', [$like])
                ->orWhereHas('microMarket', fn (Builder $market) => $market->whereRaw('LOWER(name) LIKE ?', [$like]))
                ->orWhereHas('city', fn (Builder $city) => $city->whereRaw('LOWER(name) LIKE ?', [$like])))
            ->with(['city', 'microMarket', 'primaryBuilding', 'profile'])
            ->orderBy('code')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Property $property): array => ['title' => 'SmartGro '.$property->name, 'href' => (string) $property->path(), 'summary' => $property->addressLine(), 'badge' => $property->openingLabel(), 'sample' => false])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, href: string, summary: string|null, badge: string|null, sample: bool}>
     */
    private function workspaceTypes(string $like): array
    {
        return Product::query()->shown()
            ->whereRaw('LOWER(name) LIKE ?', [$like])
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Product $product): array => [
                'title' => $product->name,
                'href' => $this->catalogue->categoryWithPage($product->slug) !== null ? '/workspaces/'.$product->slug : '/workspaces',
                'summary' => $this->catalogue->categoryCopy($product)['summary'],
                'badge' => null,
                'sample' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, href: string, summary: string|null, badge: string|null, sample: bool}>
     */
    private function pages(string $like): array
    {
        return Page::query()->published()
            ->where(fn (Builder $query) => $query->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(CAST(blocks AS CHAR)) LIKE ?', [$like]))
            ->orderBy('title')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Page $page): array => [
                'title' => (string) $page->title,
                'href' => (string) $page->path(),
                'summary' => $this->firstText($page->blocks),
                'badge' => null,
                'sample' => $page->template === PagePayload::SAMPLE_TEMPLATE,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, href: string, summary: string|null, badge: string|null, sample: bool}>
     */
    private function articles(string $like): array
    {
        return Post::query()->published()
            ->where(fn (Builder $query) => $query->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(excerpt) LIKE ?', [$like]))
            ->latest('published_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Post $post): array => ['title' => (string) $post->title, 'href' => (string) $post->path(), 'summary' => $post->excerpt, 'badge' => null, 'sample' => $post->isSample()])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, href: string, summary: string|null, badge: string|null, sample: bool}>
     */
    private function customerStories(string $like): array
    {
        return CaseStudy::query()->published()->where('consent_on_file', true)
            ->where(fn (Builder $query) => $query->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(summary) LIKE ?', [$like]))
            ->latest('published_at')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (CaseStudy $story): array => ['title' => (string) $story->title, 'href' => (string) $story->path(), 'summary' => $story->summary, 'badge' => null, 'sample' => false])
            ->values()
            ->all();
    }

    /**
     * @return list<array{title: string, href: string, summary: string|null, badge: string|null, sample: bool}>
     */
    private function questions(string $like): array
    {
        return Faq::query()->published()->general()
            ->where(fn (Builder $query) => $query->whereRaw('LOWER(question) LIKE ?', [$like])->orWhereRaw('LOWER(answer) LIKE ?', [$like]))
            ->orderBy('sort_order')
            ->limit(self::PER_GROUP)
            ->get()
            ->map(fn (Faq $faq): array => ['title' => (string) $faq->question, 'href' => '/faqs', 'summary' => Str::limit(strip_tags((string) $faq->answer), 160), 'badge' => null, 'sample' => false])
            ->values()
            ->all();
    }

    /**
     * The first piece of text in a page's sections, as a one-line summary.
     */
    private function firstText(mixed $blocks): ?string
    {
        foreach (is_array($blocks) ? $blocks : [] as $block) {
            foreach (['intro', 'text', 'body'] as $field) {
                $text = trim(strip_tags((string) ($block['data'][$field] ?? '')));

                if ($text !== '') {
                    return Str::limit(html_entity_decode($text, ENT_QUOTES | ENT_HTML5), 160);
                }
            }
        }

        return null;
    }
}
