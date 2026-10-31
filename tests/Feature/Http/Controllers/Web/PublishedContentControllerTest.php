<?php

use App\Enums\Content\RedirectMatch;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostTopic;
use App\Models\Redirect;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('lists published articles newest first and never drafts', function () {
    Post::factory()->published()->create(['title' => 'Older article', 'published_at' => now()->subWeek()]);
    Post::factory()->published()->create(['title' => 'Newer article', 'published_at' => now()]);
    Post::factory()->create(['title' => 'Draft article', 'slug' => 'draft-article']);

    get('/insights')
        ->assertOk()
        ->assertSeeInOrder(['Newer article', 'Older article'])
        ->assertDontSee('Draft article')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/insights">', false);
});

it('narrows the articles to those matching what the visitor typed, without indexing the narrowed list (OD-144)', function () {
    Post::factory()->published()->create(['title' => 'Choosing a meeting room', 'excerpt' => 'Plan ahead.']);
    Post::factory()->published()->create(['title' => 'Hybrid teams', 'excerpt' => 'When a meeting room helps.']);
    Post::factory()->published()->create(['title' => 'Desk etiquette', 'excerpt' => 'Quiet please.']);
    Post::factory()->create(['title' => 'Draft about meeting rooms', 'slug' => 'draft-meeting-rooms']);

    get('/insights?q=Meeting+Room')->assertOk()
        ->assertSee('2 articles for “Meeting Room”.', false)
        ->assertSee('Choosing a meeting room')->assertSee('Hybrid teams')
        ->assertDontSee('Desk etiquette')->assertDontSee('Draft about meeting rooms')
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
    get('/insights?q=%25%25')->assertOk()->assertSee('No article matches these words.');
    get('/insights?q=a')->assertSee('Desk etiquette')->assertDontSee('noindex', false);
    get('/insights?q[]=x')->assertOk()->assertSee('Desk etiquette');
});

it('shows a published article with its canonical address', function () {
    Post::factory()->published()->create(['title' => 'Choosing a workspace', 'slug' => 'choosing-a-workspace', 'excerpt' => 'What to look for.']);

    get('/insights/choosing-a-workspace')
        ->assertOk()
        ->assertSee('<h1 class="font-display text-display-xl font-semibold text-balance text-forest">Choosing a workspace</h1>', false)
        ->assertSee('What to look for.')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/insights/choosing-a-workspace">', false)
        ->assertDontSee('noindex');
});

it('marks an article with the sample topic as sample content, without indexing, article data or a sitemap entry, and never indexes the sample topic (OD-143)', function () {
    $sample = PostTopic::factory()->create(['name' => 'Sample content', 'slug' => PostTopic::SAMPLE_SLUG]);
    Post::factory()->published()->create(['title' => 'Example guide', 'slug' => 'example-guide', 'excerpt' => 'Example text.', 'topic_ids' => [$sample->id], 'published_at' => now()]);
    Post::factory()->published()->create(['title' => 'Real guide', 'slug' => 'real-guide', 'excerpt' => 'Real text.', 'published_at' => now()->subDay()]);

    get('/insights/example-guide')->assertOk()
        ->assertSee('<strong class="font-semibold">Sample content.</strong>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertDontSee('"@type":"Article"', false);
    get('/insights/real-guide')->assertDontSee('Sample content.')->assertDontSee('noindex', false)->assertSee('"@type":"Article"', false);
    get('/insights')->assertSeeInOrder(['Example guide', 'Example text.', 'Sample content', 'Real guide']);
    get('/search?q=guide')->assertSeeInOrder(['Example guide', 'Sample content', 'Real guide']);
    get('/insights/topic/'.PostTopic::SAMPLE_SLUG)->assertOk()->assertSee('<meta name="robots" content="noindex, follow">', false);
    get('/sitemap.xml')->assertSee('/insights/real-guide')->assertDontSee('/insights/example-guide')->assertDontSee('/insights/topic/'.PostTopic::SAMPLE_SLUG);
});

it('answers a missing article with its redirect, or 404', function () {
    Post::factory()->create(['slug' => 'unpublished']);
    Redirect::factory()->create(['from_path' => '/insights/moved', 'to_path' => '/insights/new-home', 'status_code' => 301, 'match_type' => RedirectMatch::Exact]);

    get('/insights/unpublished')->assertNotFound();
    get('/insights/moved')->assertMovedPermanently()->assertRedirect('/insights/new-home');
});

it('lists a topic\'s published articles newest first, on a page that exists only while it has one', function () {
    $topic = PostTopic::factory()->create(['name' => 'Flexible work', 'slug' => 'flexible-work']);
    $other = PostTopic::factory()->create(['name' => 'Meeting rooms', 'slug' => 'meeting-rooms']);
    $draftOnly = PostTopic::factory()->create(['name' => 'Only in drafts', 'slug' => 'only-in-drafts']);
    Post::factory()->published()->create(['title' => 'Older on flexible work', 'topic_ids' => [$topic->id], 'published_at' => now()->subWeek()]);
    Post::factory()->published()->create(['title' => 'Newer on flexible work', 'topic_ids' => [$other->id, $topic->id], 'published_at' => now()]);
    Post::factory()->published()->create(['title' => 'About meeting rooms', 'topic_ids' => [$other->id]]);
    Post::factory()->create(['title' => 'Draft on flexible work', 'slug' => 'draft-on-flexible-work', 'topic_ids' => [$topic->id, $draftOnly->id]]);

    get('/insights/topic/flexible-work')
        ->assertOk()
        ->assertSee('<h1 class="font-display text-display-xl font-semibold text-balance text-forest">Flexible work</h1>', false)
        ->assertSeeInOrder(['Newer on flexible work', 'Older on flexible work'])
        ->assertDontSee('About meeting rooms')
        ->assertDontSee('Draft on flexible work')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/insights/topic/flexible-work">', false);

    get('/insights/topic/only-in-drafts')->assertNotFound();
    get('/insights/topic/no-such-topic')->assertNotFound();
});

it('links an article to its topics in the order chosen, and the insights page to topics on the website', function () {
    $first = PostTopic::factory()->create(['name' => 'Workspace guides', 'slug' => 'workspace-guides']);
    $second = PostTopic::factory()->create(['name' => 'Community & events', 'slug' => 'community-events']);
    $unused = PostTopic::factory()->create(['name' => 'Not used yet', 'slug' => 'not-used-yet']);
    Post::factory()->published()->create(['title' => 'Choosing a workspace', 'slug' => 'choosing-a-workspace', 'topic_ids' => [$second->id, $first->id]]);
    Post::factory()->create(['slug' => 'draft', 'topic_ids' => [$unused->id]]);

    get('/insights/choosing-a-workspace')
        ->assertOk()
        ->assertSeeInOrder(['href="/insights/topic/community-events"', 'Community &amp; events', 'href="/insights/topic/workspace-guides"'], false);

    get('/insights')
        ->assertOk()
        ->assertSee('<nav aria-label="Topics">', false)
        ->assertSee('href="/insights/topic/community-events"', false)
        ->assertSee('href="/insights/topic/workspace-guides"', false)
        ->assertDontSee('Not used yet');
});

it('shows no topic list while no published article has a topic', function () {
    PostTopic::factory()->create();
    Post::factory()->published()->create();

    get('/insights')->assertOk()->assertDontSee('aria-label="Topics"', false);
});

it('has no topic page at a malformed or empty address', function (string $path) {
    $topic = PostTopic::factory()->create(['slug' => 'flexible-work']);
    Post::factory()->published()->create(['topic_ids' => [$topic->id]]);

    get($path)->assertNotFound();
})->with([
    'no topic' => '/insights/topic',
    'trailing slash' => '/insights/topic/',
    'capitals' => '/insights/topic/Flexible-Work',
    'underscore' => '/insights/topic/flexible_work',
    'deeper path' => '/insights/topic/flexible-work/more',
    'markup' => '/insights/topic/%3Cscript%3E',
]);

it('answers a topic address that has no page with its redirect', function () {
    $topic = PostTopic::factory()->create(['slug' => 'flexible-work']);
    Post::factory()->published()->create(['topic_ids' => [$topic->id]]);
    Redirect::factory()->create(['from_path' => '/insights/topic/hybrid-work', 'to_path' => '/insights/topic/flexible-work', 'status_code' => 301, 'match_type' => RedirectMatch::Exact]);

    get('/insights/topic/hybrid-work')->assertMovedPermanently()->assertRedirect('/insights/topic/flexible-work');
});

it('pages a topic\'s articles twelve at a time, each page with its own canonical address', function () {
    $topic = PostTopic::factory()->create(['slug' => 'flexible-work']);
    Post::factory()->published()->count(12)->create(['topic_ids' => [$topic->id], 'published_at' => now()]);
    Post::factory()->published()->create(['title' => 'The oldest article', 'topic_ids' => [$topic->id], 'published_at' => now()->subYear()]);

    get('/insights/topic/flexible-work')->assertOk()->assertDontSee('The oldest article');
    get('/insights/topic/flexible-work?page=2')
        ->assertOk()
        ->assertSee('The oldest article')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/insights/topic/flexible-work?page=2">', false);
});

it('shows the insights pages with the same number of queries however many articles and topics there are', function () {
    $shared = PostTopic::factory()->create(['slug' => 'shared']);
    $publishArticles = function (int $count) use ($shared): void {
        PostTopic::factory()->count($count)->create()->each(fn (PostTopic $topic) => Post::factory()->published()->create(['topic_ids' => [$topic->id, $shared->id]]));
    };
    $queriesFor = function (string $path): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        get($path)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    $publishArticles(1);
    $queriesFor('/insights');
    $withFew = ['/insights' => $queriesFor('/insights'), '/insights/topic/shared' => $queriesFor('/insights/topic/shared')];
    $publishArticles(5);

    expect(['/insights' => $queriesFor('/insights'), '/insights/topic/shared' => $queriesFor('/insights/topic/shared')])->toBe($withFew);
});

it('shows customer stories only while consent is on file', function () {
    CaseStudy::factory()->published()->create(['title' => 'Rao Studio grows', 'slug' => 'rao-studio']);
    CaseStudy::factory()->published()->create(['title' => 'Consent withdrawn', 'slug' => 'withdrawn', 'consent_on_file' => false]);
    CaseStudy::factory()->create(['title' => 'Draft story', 'slug' => 'draft-story']);

    get('/customers')->assertOk()->assertSee('Rao Studio grows')->assertDontSee('Consent withdrawn')->assertDontSee('Draft story');
    get('/customers/rao-studio')->assertOk()->assertSee('Rao Studio grows');
    get('/customers/withdrawn')->assertNotFound();
    get('/customers/draft-story')->assertNotFound();
});

it('lists published FAQs in their set order, as escaped text', function () {
    Faq::factory()->published()->create(['question' => 'Second question?', 'sort_order' => 2]);
    Faq::factory()->published()->create(['question' => 'First question?', 'sort_order' => 1, 'answer' => "Yes.\n<script>alert(1)</script>"]);
    Faq::factory()->create(['question' => 'Draft question?']);

    get('/faqs')
        ->assertOk()
        ->assertSeeInOrder(['First question?', 'Second question?'])
        ->assertSee('Yes.<br />', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('Draft question?');
});

it('shows a testimonial section only while the testimonial is published with consent and a verified statement', function (array $state, bool $isShown) {
    $testimonial = Testimonial::factory()->published()->create(['quote' => 'Moving in took a day.', ...$state]);
    Page::factory()->published()->create(['slug' => 'about', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>About us.</p>']],
        ['type' => 'testimonial', 'data' => ['testimonial_id' => $testimonial->id]],
    ]]);

    $response = get('/about')->assertOk();

    $isShown ? $response->assertSee('Moving in took a day.') : $response->assertDontSee('Moving in took a day.');
})->with([
    'published with consent' => [[], true],
    'not published' => [['status' => 'draft'], false],
    'consent withdrawn' => [['consent_on_file' => false], false],
    'statement not verified' => [['statement_verified' => false], false],
]);

it('shows when a legal page was last updated', function () {
    Page::factory()->published()->create(['title' => 'Privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal', 'published_at' => '2026-10-01 06:00:00']);

    get('/legal/privacy')->assertOk()->assertSee('Last updated 1 October 2026');
});
