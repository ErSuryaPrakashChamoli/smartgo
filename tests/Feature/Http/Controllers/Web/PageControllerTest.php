<?php

use App\Enums\Content\RedirectMatch;
use App\Enums\Content\RevisionState;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

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

it('shows cards with live links only, and links to parts of the website only while they have something to show (OD-143)', function () {
    $live = Page::factory()->published()->create(['title' => 'Startups', 'slug' => 'solutions/startups']);
    $draft = Page::factory()->create(['title' => 'Draft solution']);
    $link = fn (string $label, string $type, ?int $pageId = null, ?string $section = null): array => ['label' => $label, 'type' => $type, 'page_id' => $pageId, 'section' => $section, 'url' => null];
    Page::factory()->published()->create(['slug' => 'solutions', 'blocks' => [['type' => 'cards', 'data' => ['heading' => 'Who we work with', 'intro' => 'Pick yours.', 'items' => [
        ['title' => 'Startups', 'text' => 'Room to grow.', 'link' => $link('Read about startups', 'page', $live->id)],
        ['title' => 'Enterprises', 'text' => 'Whole floors.', 'link' => $link('Read about enterprises', 'page', $draft->id)],
        ['title' => 'Ask us', 'text' => null, 'link' => $link('Send an enquiry', 'section', section: 'enquire')],
    ]]]]]);

    get('/solutions')->assertOk()
        ->assertSeeInOrder(['Who we work with', 'Startups', 'Read about startups', 'Enterprises', 'Ask us'])
        ->assertSee('href="/solutions/startups"', false)
        ->assertDontSee('Read about enterprises')
        ->assertDontSee('href="/enquire"', false);

    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);

    get('/solutions')->assertSee('href="/enquire"', false)->assertSee('Send an enquiry');
});

it('links to a workspace type\'s page only while the type has one on the website (OD-150)', function () {
    seed(ReferenceDataSeeder::class);
    $meetingRooms = Product::query()->where('key', 'meeting_room')->sole();
    Page::factory()->published()->create(['slug' => 'business-solutions/meeting-rooms', 'blocks' => [['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Meeting Rooms', 'intro' => null,
        'link' => ['label' => 'See locations with meeting rooms', 'type' => 'category', 'page_id' => null, 'section' => null, 'product_id' => $meetingRooms->id, 'url' => null]]]]]);

    get('/business-solutions/meeting-rooms')->assertOk()->assertDontSee('See locations with meeting rooms');

    offerForTest(publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole()), 'meeting_room');

    get('/business-solutions/meeting-rooms')->assertSee('href="/workspaces/meeting-rooms"', false)->assertSee('See locations with meeting rooms');

    $meetingRooms->update(['is_active' => false]);

    get('/business-solutions/meeting-rooms')->assertOk()->assertDontSee('See locations with meeting rooms');
});

it('marks a page with the sample layout as sample content, never indexes it and leaves it out of the sitemap (OD-143)', function () {
    Page::factory()->published()->create(['title' => 'Community', 'slug' => 'community', 'template' => 'sample', 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Example text.</p>']]]]);
    Page::factory()->published()->create(['title' => 'Real page', 'slug' => 'real-page']);

    get('/community')->assertOk()
        ->assertSee('<strong class="font-semibold">Sample content.</strong>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
    get('/real-page')->assertDontSee('Sample content.')->assertDontSee('noindex', false);
    get('/sitemap.xml')->assertSee('/real-page')->assertDontSee('/community');
});
