<?php

use App\Enums\Content\RedirectMatch;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('shows a published article with its canonical address', function () {
    Post::factory()->published()->create(['title' => 'Choosing a workspace', 'slug' => 'choosing-a-workspace', 'excerpt' => 'What to look for.']);

    get('/insights/choosing-a-workspace')
        ->assertOk()
        ->assertSee('<h1 class="font-display text-display-xl font-semibold text-balance text-forest">Choosing a workspace</h1>', false)
        ->assertSee('What to look for.')
        ->assertSee('<link rel="canonical" href="'.rtrim(config('app.url'), '/').'/insights/choosing-a-workspace">', false)
        ->assertDontSee('noindex');
});

it('answers a missing article with its redirect, or 404', function () {
    Post::factory()->create(['slug' => 'unpublished']);
    Redirect::factory()->create(['from_path' => '/insights/moved', 'to_path' => '/insights/new-home', 'status_code' => 301, 'match_type' => RedirectMatch::Exact]);

    get('/insights/unpublished')->assertNotFound();
    get('/insights/moved')->assertMovedPermanently()->assertRedirect('/insights/new-home');
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
