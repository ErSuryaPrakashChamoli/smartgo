<?php

namespace App\Services\Website;

use App\Http\Controllers\Web\PublishedContentController;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Offering;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\Property;
use App\Models\SpaceListing;
use App\Services\Content\PagePayload;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use XMLWriter;

/**
 * The XML sitemap (FR-SEO-3, sitemaps.org protocol 0.9): every page the website shows and lets search
 * engines index, at its canonical address, and nothing else. It is built from the same published records
 * and queries as the pages themselves (WorkspaceCatalogue, the published scopes), on every request, so a
 * publication or a takedown shows at once. Not listed: drafts and previews, unpublished or hidden items,
 * redirects, comparisons, "coming soon" cities, signed visitor pages, confirmation pages, design previews,
 * lists of articles, customer stories or questions with nothing in them, and the tour page while tours
 * are not offered (those carry "noindex"). No priority or change frequency is given (no
 * such facts exist); the last change is given only where the item's publication time is recorded.
 */
class Sitemap
{
    /**
     * The protocol's limit for one file; beyond it an index of several files is needed.
     */
    public const int MAX_URLS = 50000;

    public function __construct(private WorkspaceCatalogue $catalogue, private TourForm $tourForm) {}

    /**
     * Canonical addresses, without duplicates, home first, each with what it shows: its kind, the record
     * behind it (if any) and, for a list's further page, the list and the page number. The search
     * metadata report (SearchMetadata) reads the same entries.
     *
     * @return Collection<int, array{loc: string, path: string, lastmod: CarbonInterface|null, type: string, subject: mixed, context: array<string, mixed>}>
     */
    public function entries(): Collection
    {
        $entries = collect();
        $add = function (string $path, ?CarbonInterface $lastModified = null, string $type = 'page', mixed $subject = null, array $context = []) use ($entries): void {
            $entries->push(['loc' => rtrim((string) config('app.url'), '/').$path, 'path' => $path, 'lastmod' => $lastModified, 'type' => $type, 'subject' => $subject, 'context' => $context]);
        };

        $add('/', null, 'home');
        $properties = $this->catalogue->listedProperties();
        $this->workspaces($add, $properties);
        $this->locations($add, $properties);

        // Each shown space's page (OD-177); example spaces carry "noindex", so they are left out.
        if (SpaceListing::isAvailable()) {
            SpaceListing::query()->shown()->where('is_sample', false)->with(['property', 'spaceType'])->orderBy('slug')->get()
                ->each(fn (SpaceListing $listing) => $add((string) $listing->path(), $listing->published_at, 'space', $listing));
        }

        $this->insights($add);

        $caseStudies = CaseStudy::query()->published()->where('consent_on_file', true)->latest('published_at')->get(['id', 'slug', 'status', 'published_at', 'title', 'summary']);

        if ($caseStudies->isNotEmpty()) {
            $add('/customers', null, 'customers');
            $caseStudies->each(fn (CaseStudy $caseStudy) => $add((string) $caseStudy->path(), $caseStudy->published_at, 'case_study', $caseStudy));
        }

        if (Faq::query()->published()->general()->exists()) {
            $add('/faqs', null, 'faqs');
        }

        $add('/enquire', null, 'enquire');

        if ($this->tourForm->properties()->isNotEmpty()) {
            $add('/book-a-tour', null, 'book_a_tour');
        }

        Page::query()->published()->with('seo')->orderBy('slug')->get()
            ->filter(fn (Page $page): bool => ($page->seo?->isIndexable() ?? true) && $page->template !== PagePayload::SAMPLE_TEMPLATE)
            ->each(fn (Page $page) => $add((string) $page->path(), $page->published_at, 'page', $page));

        return $entries->unique('loc')->values();
    }

    /**
     * The sitemap document.
     */
    public function xml(): string
    {
        $entries = $this->entries()->take(self::MAX_URLS);
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($entries as $entry) {
            $writer->startElement('url');
            $writer->writeElement('loc', $entry['loc']);

            if ($entry['lastmod'] !== null) {
                $writer->writeElement('lastmod', $entry['lastmod']->copy()->utc()->toAtomString());
            }

            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    /**
     * The workspace hub (always: a main section of the website), each category with a page, and each
     * category in each city where it is offered.
     *
     * @param  callable(string, CarbonInterface|null=, string=, mixed=, array<string, mixed>=): void  $add
     * @param  Collection<int, Property>  $properties  the listed properties (WorkspaceCatalogue::listedProperties)
     */
    private function workspaces(callable $add, Collection $properties): void
    {
        $categories = collect($this->catalogue->categories($properties))->whereNotNull('path');

        $add('/workspaces', null, 'workspaces');
        $offerings = $this->catalogue->publishedOfferings()->filter(fn (Offering $offering): bool => $categories->contains('slug', $offering->product->slug));
        $categories->each(fn (array $category) => $add($category['path'], null, 'category', $offerings->first(fn (Offering $offering): bool => $offering->product->slug === $category['slug'])?->product,
            ['offerings' => $offerings->filter(fn (Offering $offering): bool => $offering->product->slug === $category['slug'])->count()]));

        $offerings->groupBy(fn (Offering $offering): string => $offering->product->slug.'/'.$offering->property->city->slug)
            ->each(fn (Collection $group, string $key) => $add('/workspaces/'.$key, null, 'category_city', $group->first()->product, ['city' => $group->first()->property->city, 'offerings' => $group->count()]));
    }

    /**
     * The locations hub (always: a main section of the website), each city with a property on the
     * website, each sector with one, each property and each published offering at it.
     *
     * @param  callable(string, CarbonInterface|null=, string=, mixed=, array<string, mixed>=): void  $add
     * @param  Collection<int, Property>  $properties  the listed properties (WorkspaceCatalogue::listedProperties)
     */
    private function locations(callable $add, Collection $properties): void
    {
        $cities = $this->catalogue->publicCities();
        $add('/locations', null, 'locations');
        $cities->filter(fn ($city): bool => $city->open_properties_count > 0)->each(fn ($city) => $add('/locations/'.$city->slug, null, 'city', $city));
        $properties->pluck('microMarket')->filter()->unique('id')->each(fn ($sector) => $add($sector->path(), null, 'sector', $sector));
        $properties->each(function (Property $property) use ($add): void {
            $add((string) $property->path(), null, 'property', $property);
            $property->publishedOfferings->each(fn (Offering $offering) => $add((string) $offering->path(), null, 'offering', $offering->setRelation('property', $property)));
        });
    }

    /**
     * Published articles, their list and the topics with one, including every further page of a list;
     * sample articles, the sample topic and list pages showing only sample articles are left out (OD-143,
     * OD-152).
     *
     * @param  callable(string, CarbonInterface|null=, string=, mixed=, array<string, mixed>=): void  $add
     */
    private function insights(callable $add): void
    {
        $posts = Post::query()->published()->latest('published_at')->get(['id', 'slug', 'status', 'published_at', ...(PostTopic::isAvailable() ? ['topic_ids'] : []), 'title', 'excerpt']);

        $this->listPages($add, '/insights', $posts, 'insights');

        if (PostTopic::isAvailable()) {
            PostTopic::query()->whereKey($posts->pluck('topic_ids')->flatten()->filter()->unique()->all())->where('slug', '!=', PostTopic::SAMPLE_SLUG)->orderBy('slug')->get()
                ->each(fn (PostTopic $topic) => $this->listPages($add, $topic->path(), $posts->filter(fn (Post $post): bool => in_array($topic->getKey(), array_map('intval', $post->topic_ids ?? []), true)), 'topic', $topic));
        }

        $posts->reject(fn (Post $post): bool => $post->isSample())->each(fn (Post $post) => $add((string) $post->path(), $post->published_at, 'post', $post));
    }

    /**
     * Each page of a list, at its canonical address, that shows at least one article other than a sample:
     * the list's articles are split into pages as the list itself splits them (newest first).
     *
     * @param  callable(string, CarbonInterface|null=, string=, mixed=, array<string, mixed>=): void  $add
     * @param  Collection<int, Post>  $posts
     */
    private function listPages(callable $add, string $path, Collection $posts, string $type, mixed $subject = null): void
    {
        $posts->values()->chunk(PublishedContentController::PER_PAGE)->each(function (Collection $onPage, int $index) use ($add, $path, $type, $subject): void {
            if ($onPage->contains(fn (Post $post): bool => ! $post->isSample())) {
                $add($index === 0 ? $path : $path.'?page='.($index + 1), null, $type, $subject, ['list' => $path, 'page' => $index + 1]);
            }
        });
    }
}
