<?php

use App\Enums\Content\RedirectMatch;
use App\Enums\Content\RevisionState;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('renders a published page with every kind of block', function () {
    $contact = Page::factory()->published()->create(['slug' => 'contact']);
    $unpublished = Page::factory()->create(['title' => 'Not live yet', 'slug' => 'not-live-yet']);
    $page = Page::factory()->published()->create(['title' => 'Workspaces', 'slug' => 'workspaces-in-noida', 'blocks' => [
        ['type' => 'hero', 'data' => ['eyebrow' => 'Noida', 'heading' => 'Workspaces that grow with you', 'intro' => 'Desks and cabins.', 'link' => ['label' => 'Talk to us', 'type' => 'page', 'page_id' => $contact->id, 'url' => null]]],
        ['type' => 'text', 'data' => ['heading' => 'About the space', 'body' => '<p>Quiet <strong>floors</strong>.</p>']],
        ['type' => 'features', 'data' => ['heading' => 'Why us', 'intro' => null, 'items' => [['title' => 'Daylight', 'text' => 'Large windows.'], ['title' => 'Transit', 'text' => 'Near the metro.']]]],
        ['type' => 'faq', 'data' => ['heading' => null, 'items' => [['question' => 'Can I visit?', 'answer' => "Yes.\nBook a tour."]]]],
        ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Visit us', 'text' => 'Any weekday.', 'primary_link' => ['label' => 'Partner site', 'type' => 'url', 'page_id' => null, 'url' => 'https://example.com/visit'], 'secondary_link' => ['label' => 'Hidden', 'type' => 'page', 'page_id' => $unpublished->id, 'url' => null]]],
    ]]);

    get('/'.$page->slug)
        ->assertOk()
        ->assertSeeInOrder(['Workspaces that grow with you', 'About the space', 'Why us', 'Frequently asked questions', 'Visit us'])
        ->assertSee('<h1', false)
        ->assertSee('href="/contact"', false)
        ->assertSee('href="https://example.com/visit"', false)
        ->assertSee('<p>Quiet <strong>floors</strong>.</p>', false)
        ->assertSee('Yes.<br />', false)
        ->assertDontSee('Hidden')
        ->assertDontSee('/not-live-yet')
        ->assertDontSee('noindex');
});

it('uses the page title as the main heading when there is no introduction', function () {
    $page = Page::factory()->published()->create(['title' => 'Privacy notice', 'slug' => 'legal/privacy']);

    get('/legal/privacy')->assertOk()->assertSee('<h1 class="max-w-4xl font-display text-display-xl font-semibold text-balance text-forest">Privacy notice</h1>', false);
});

it('shows only the live version, never a version in progress', function () {
    $page = Page::factory()->published()->create(['title' => 'Live title', 'slug' => 'about']);
    $page->revisions()->create(['number' => 2, 'payload' => [...$page->toRevisionPayload(), 'title' => 'Unapproved title'], 'state' => RevisionState::Approved, 'author_id' => User::factory()->create()->id, 'editor_ids' => []]);

    get('/about')->assertOk()->assertSee('Live title')->assertDontSee('Unapproved title');
});

it('returns 404 for pages that were never published', function () {
    Page::factory()->create(['slug' => 'secret', 'title' => 'Secret']);

    get('/secret')->assertNotFound();
});

it('never outputs script stored in a live page, however it got there', function () {
    $page = Page::factory()->published()->create(['slug' => 'injected', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => '<script>alert("h")</script>', 'body' => '<p>Hi</p><script>alert(1)</script><img src=x onerror="alert(2)"><a href="javascript:alert(3)">x</a>']],
        ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Go', 'text' => null, 'primary_link' => ['label' => 'Click', 'type' => 'url', 'page_id' => null, 'url' => 'javascript:alert(4)'], 'secondary_link' => null]],
        ['type' => 'html', 'data' => ['code' => '<script>alert(5)</script>']],
    ]]);

    get('/'.$page->slug)
        ->assertOk()
        ->assertDontSee('<script>alert', false)
        ->assertDontSee('onerror', false)
        ->assertDontSee('javascript:', false)
        ->assertSee('&lt;script&gt;alert(&quot;h&quot;)&lt;/script&gt;', false);
});

it('redirects a live redirect permanently and counts the hit', function () {
    Page::factory()->published()->create(['slug' => 'about']);
    $redirect = Redirect::factory()->create(['from_path' => '/about-us', 'to_path' => '/about']);

    get('/about-us')->assertStatus(301)->assertRedirect('/about');

    expect($redirect->fresh()->hits)->toBe(1)
        ->and($redirect->fresh()->last_hit_at)->not->toBeNull();
});

it('answers 410 for a prefix rule', function () {
    Redirect::factory()->create(['from_path' => '/pin/', 'match_type' => RedirectMatch::Prefix, 'to_path' => null, 'status_code' => 410]);

    get('/pin/some-spam-page')->assertStatus(410);
});

it('never follows a stored redirect to another website or to itself', function (string $target) {
    Redirect::factory()->create(['from_path' => '/old', 'to_path' => $target]);

    get('/old')->assertNotFound();
})->with(['https://evil.example', '//evil.example', '/\\evil.example', 'evil.example', '/old']);

it('serves a live page in preference to a redirect from the same address', function () {
    Page::factory()->published()->create(['slug' => 'offices', 'title' => 'Offices']);
    Redirect::factory()->create(['from_path' => '/offices', 'to_path' => '/elsewhere']);

    get('/offices')->assertOk()->assertSee('Offices');
});

it('leaves existing routes and unknown paths alone', function () {
    Redirect::factory()->create(['from_path' => '/design', 'to_path' => '/elsewhere']);

    get('/')->assertOk();
    get('/design')->assertOk();
    get('/admin/login')->assertOk();
    get('/no-such-page')->assertNotFound();
});
