<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Enums\Access\StaffRole;
use App\Models\CaseStudy;
use App\Models\City;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\Property;
use App\Models\Revision;
use App\Models\TourAvailabilityRule;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    config(['app.url' => 'https://www.smartgro.example']);
    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * The addresses in the sitemap, as paths.
 *
 * @return list<string>
 */
function sitemapPathsForTest(): array
{
    $xml = get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    $document = new DOMDocument;
    $document->loadXML($xml);

    return collect(iterator_to_array($document->getElementsByTagNameNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'loc')))
        ->map(fn (DOMElement $loc): string => (string) str($loc->textContent)->after('https://www.smartgro.example')->whenEmpty(fn () => '/'))
        ->all();
}

/**
 * A published standard page at that address, through the content workflow; indexable unless told otherwise.
 */
function publishPageForTest(Page $page, User $author, User $approver, User $publisher, string $slug, bool $indexable = true): Page
{
    $draft = app(CreateDraft::class)->handle($page, $author, [
        'title' => 'Test page', 'slug' => $slug, 'template' => 'standard',
        'blocks' => [['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Test page heading', 'intro' => 'An introduction long enough for the page to describe itself to search engines and visitors alike.', 'link' => null]]],
        'seo' => ['indexable' => $indexable],
    ]);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);

    return $page->fresh();
}

it('lists every published, indexable page once, at the canonical address that page itself gives, and nothing else', function () {
    $property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    offerForTest($property, 'private_office');
    $post = Post::factory()->published()->create(['slug' => 'choosing-a-workspace']);
    $story = CaseStudy::factory()->published()->create(['slug' => 'a-team-story']);
    Faq::factory()->published()->create();
    publishPageForTest(Page::factory()->create(), $this->author, $this->approver, $this->publisher, 'about');

    $paths = sitemapPathsForTest();

    expect($paths)->toEqualCanonicalizing([
        '/', '/workspaces', '/workspaces/private-cabins', '/workspaces/private-cabins/noida',
        '/locations', '/locations/noida', '/locations/noida/area/sector-2', '/locations/noida/a-70', '/locations/noida/a-70/private-cabins',
        '/insights', '/insights/choosing-a-workspace', '/customers', '/customers/a-team-story', '/faqs', '/enquire', '/about',
    ])->and($paths)->toHaveCount(count(array_unique($paths)));

    foreach ($paths as $path) {
        $page = get($path)->assertOk();
        $html = $page->getContent();

        expect($html)->toContain('<link rel="canonical" href="https://www.smartgro.example'.($path === '/' ? '/' : $path).'">')
            ->and($html)->not->toContain('name="robots" content="noindex');
    }

    expect($post->path())->toBe('/insights/choosing-a-workspace')->and($story->path())->toBe('/customers/a-team-story');
});

it('leaves out drafts, unpublished and hidden items, pages marked not to be indexed, comparisons, coming-soon cities and private pages', function () {
    publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    City::query()->where('slug', 'gurugram')->update(['is_publicly_visible' => true]);
    Post::factory()->create(['slug' => 'draft-article']);
    CaseStudy::factory()->published()->create(['slug' => 'story-without-consent', 'consent_on_file' => false]);
    Faq::factory()->create();
    publishPageForTest(Page::factory()->create(), $this->author, $this->approver, $this->publisher, 'internal-notice', indexable: false);
    Page::factory()->create(['slug' => 'never-published']);

    $paths = sitemapPathsForTest();

    expect($paths)->not->toContain('/locations/noida/a-71', '/locations/gurugram', '/locations/compare', '/insights/draft-article', '/customers/story-without-consent',
        '/customers', '/faqs', '/insights', '/internal-notice', '/never-published', '/book-a-tour', '/enquire/received', '/design');

    get('/locations/gurugram')->assertOk()->assertSee('name="robots" content="noindex', false);
    get('/locations/compare')->assertOk()->assertSee('name="robots" content="noindex', false);
    get('/internal-notice')->assertOk()->assertSee('name="robots" content="noindex, follow"', false);
    get('/locations/noida/a-71')->assertNotFound();
    get('/insights/draft-article')->assertNotFound();
    get('/never-published')->assertNotFound();
});

it('follows publication at once: a moved page is listed only at its new address, and content taken down leaves the sitemap', function () {
    $property = Property::query()->where('code', 'A-70')->sole();
    offerForTest($property, 'private_office');
    $publish = fn ($content, array $payload) => app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(
        app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($content, $this->author, $payload), $this->author), $this->approver), $this->publisher);
    $publish($property->profile, ['headline' => 'Workspaces at SmartGro A-70', 'summary' => 'A summary.']);
    $story = CaseStudy::factory()->create();
    $publish($story, ['title' => 'A team story', 'slug' => 'a-team-story', 'company_name' => 'A team', 'summary' => 'A growing team.', 'consent_on_file' => true, 'consent_reference' => 'Signed form',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Story.</p>']]]]);
    $page = publishPageForTest(Page::factory()->create(), $this->author, $this->approver, $this->publisher, 'old-address');
    publishPageForTest($page, $this->author, $this->approver, $this->publisher, 'new-address');

    expect(sitemapPathsForTest())->toContain('/new-address', '/customers/a-team-story', '/locations/noida/a-70', '/locations/noida/a-70/private-cabins')->not->toContain('/old-address');
    get('/old-address')->assertRedirect('/new-address');

    app(UnpublishContent::class)->handle($story->fresh(), $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    app(UnpublishContent::class)->handle($property->profile->fresh(), $this->publisher, 'Location closed for refurbishment', consentWithdrawn: false);

    expect(sitemapPathsForTest())->not->toContain('/customers/a-team-story', '/customers', '/locations/noida/a-70', '/locations/noida/a-70/private-cabins', '/locations/noida', '/workspaces/private-cabins');
    get('/customers/a-team-story')->assertNotFound();
    get('/locations/noida/a-70')->assertNotFound();
});

it('lists the further pages of a long list at their canonical addresses, and a page past the last one does not exist', function () {
    Post::factory()->published()->count(13)->sequence(fn ($sequence) => ['slug' => 'article-'.$sequence->index, 'published_at' => now()->subMinutes($sequence->index)])->create();

    expect(sitemapPathsForTest())->toContain('/insights', '/insights?page=2')->not->toContain('/insights?page=3');

    get('/insights?page=2')->assertOk()->assertSee('<link rel="canonical" href="https://www.smartgro.example/insights?page=2">', false);
    get('/insights?page=3')->assertNotFound();
});

it('leaves out list and topic pages that show only sample articles, page by page (OD-152)', function () {
    $sample = PostTopic::factory()->create(['slug' => PostTopic::SAMPLE_SLUG]);
    $topic = PostTopic::factory()->create(['slug' => 'flexible-work']);
    Post::factory()->published()->count(12)->sequence(fn ($sequence) => ['slug' => 'sample-'.$sequence->index, 'published_at' => now()->subMinutes($sequence->index), 'topic_ids' => [$sample->id, $topic->id]])->create();

    expect(sitemapPathsForTest())->not->toContain('/insights')->not->toContain('/insights/topic/flexible-work')->not->toContain('/insights/sample-0');

    Post::factory()->published()->create(['slug' => 'real-article', 'published_at' => now()->subDay(), 'topic_ids' => [$topic->id]]);

    expect(sitemapPathsForTest())->toContain('/insights?page=2', '/insights/topic/flexible-work?page=2', '/insights/real-article')
        ->not->toContain('/insights')->not->toContain('/insights/topic/flexible-work')->not->toContain('/insights/sample-0');
    get('/insights?page=2')->assertOk()->assertSee('href="/insights/real-article"', false);
});

it('marks lists with nothing in them "noindex", and lists only the main sections when nothing is published', function () {
    expect(sitemapPathsForTest())->toBe(['/', '/workspaces', '/locations', '/enquire']);

    foreach (['/insights', '/customers', '/faqs', '/book-a-tour'] as $hub) {
        get($hub)->assertOk()->assertSee('name="robots" content="noindex', false)->assertDontSee('rel="canonical"', false);
    }
});

it('lists the tour page only while visitors can request a tour', function () {
    $property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    TourAvailabilityRule::factory()->for($property)->window(2, '10:00', '11:00', 30, 1)->create();

    expect(sitemapPathsForTest())->not->toContain('/book-a-tour');

    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal', 'published_revision_id' => Revision::factory()->create()->id]);

    expect(sitemapPathsForTest())->toContain('/book-a-tour');
    get('/book-a-tour')->assertOk()->assertDontSee('name="robots" content="noindex', false)->assertSee('<link rel="canonical" href="https://www.smartgro.example/book-a-tour">', false);
});

it('is a valid sitemaps.org document whose addresses are absolute, unique and escaped', function () {
    Post::factory()->published()->create(['slug' => 'choosing-a-workspace']);
    $document = new DOMDocument;
    $document->loadXML(get('/sitemap.xml')->getContent());
    libxml_use_internal_errors(true);

    expect($document->schemaValidate(base_path('tests/Support/Seo/sitemap-0.9.xsd')))->toBeTrue(collect(libxml_get_errors())->map(fn ($error) => trim($error->message))->join('; '))
        ->and($document->getElementsByTagName('lastmod')->item(0)?->textContent)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/')
        ->and($document->getElementsByTagName('priority')->length + $document->getElementsByTagName('changefreq')->length)->toBe(0);

    libxml_clear_errors();
});

it('lets nothing be crawled or indexed outside production, and never names the back office', function () {
    get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSeeText("User-agent: *\nDisallow: /\n", false)->assertDontSee('Sitemap:', false);
    get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    get('/sitemap.xml')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('lets every public page be crawled in production, names the sitemap, and keeps the back office out of search engines without naming it', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $robots = get('/robots.txt')->assertOk()->getContent();

    expect($robots)->toBe("User-agent: *\nDisallow:\n\nSitemap: https://www.smartgro.example/sitemap.xml\n")
        ->and($robots)->not->toContain('/'.config('backoffice.path'));
    get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    get('/sitemap.xml')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    get('/'.config('backoffice.path').'/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
