<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    config(['app.url' => 'https://www.smartgro.example']);

    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $this->page = Page::factory()->create();
});

/**
 * @param  array<string, mixed>  $seo
 * @param  list<array<string, mixed>>|null  $blocks
 */
function seoVersion(Page $page, User $author, string $slug, array $seo = [], ?array $blocks = null): Revision
{
    return app(CreateDraft::class)->handle($page, $author, [
        'title' => 'Managed offices', 'slug' => $slug, 'template' => 'standard',
        'blocks' => $blocks ?? [['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Managed offices in Noida', 'intro' => 'Private floors run by SmartGro, with reception and meeting rooms included in one monthly agreement for your team.', 'link' => null]]],
        'seo' => $seo,
    ]);
}

function publishSeoVersion(Revision $draft, User $author, User $approver, User $publisher): void
{
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);
}

it('publishes the title, description and canonical URL written for search engines', function () {
    publishSeoVersion(seoVersion($this->page, $this->author, 'managed-offices', ['title' => 'Managed offices in Noida | SmartGro', 'description' => 'Private, managed floors in Noida.', 'indexable' => true]), $this->author, $this->approver, $this->publisher);

    get('/managed-offices')
        ->assertOk()
        ->assertSee('<title>Managed offices in Noida | SmartGro</title>', false)
        ->assertSee('<meta name="description" content="Private, managed floors in Noida.">', false)
        ->assertSee('<link rel="canonical" href="https://www.smartgro.example/managed-offices">', false)
        ->assertDontSee('name="robots"', false);
});

it('falls back to the page title and the introduction, cut on a word', function () {
    publishSeoVersion(seoVersion($this->page, $this->author, 'managed-offices'), $this->author, $this->approver, $this->publisher);

    $html = get('/managed-offices')->assertSee('<title>Managed offices · SmartGro</title>', false)->getContent();

    preg_match('/<meta name="description" content="([^"]*)">/', $html, $match);

    expect($match[1])->toStartWith('Private floors run by SmartGro')
        ->and(mb_strlen(html_entity_decode($match[1])))->toBeLessThanOrEqual(160);
});

it('never makes an automatic description longer than 160 characters, ellipsis included', function (string $intro) {
    publishSeoVersion(seoVersion($this->page, $this->author, 'managed-offices', blocks: [
        ['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Managed offices', 'intro' => $intro, 'link' => null]],
    ]), $this->author, $this->approver, $this->publisher);

    preg_match('/<meta name="description" content="([^"]*)">/', get('/managed-offices')->getContent(), $match);
    $description = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);

    expect(mb_strlen($description))->toBeLessThanOrEqual(160)
        ->and($description)->toEndWith('…');
})->with([
    'one long word' => [str_repeat('a', 300)],
    'words ending at the limit' => [str_repeat('x ', 150)],
    'ordinary words' => [str_repeat('Private floors with reception ', 10)],
]);

it('asks search engines not to index a page when the editor says so', function () {
    publishSeoVersion(seoVersion($this->page, $this->author, 'managed-offices', ['indexable' => false]), $this->author, $this->approver, $this->publisher);

    get('/managed-offices')->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('moves the canonical URL with the page, while the old address redirects to it', function () {
    publishSeoVersion(seoVersion($this->page, $this->author, 'offices'), $this->author, $this->approver, $this->publisher);
    publishSeoVersion(seoVersion($this->page, $this->author, 'managed-offices'), $this->author, $this->approver, $this->publisher);

    get('/offices')->assertStatus(301)->assertRedirect('/managed-offices');
    get('/managed-offices')->assertSee('<link rel="canonical" href="https://www.smartgro.example/managed-offices">', false);
});

it('builds the canonical URL from the configured address, not the request\'s host', function () {
    publishSeoVersion(seoVersion($this->page, $this->author, 'managed-offices'), $this->author, $this->approver, $this->publisher);

    $this->withHeaders(['Host' => 'evil.example'])->get('/managed-offices')
        ->assertSee('<link rel="canonical" href="https://www.smartgro.example/managed-offices">', false)
        ->assertDontSee('evil.example');
});

it('keeps unpublished search-engine changes off the live page', function () {
    publishSeoVersion(seoVersion($this->page, $this->author, 'managed-offices', ['title' => 'Live title']), $this->author, $this->approver, $this->publisher);
    app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle(seoVersion($this->page, $this->author, 'managed-offices', ['title' => 'Approved, not published']), $this->author), $this->approver);

    get('/managed-offices')->assertSee('<title>Live title</title>', false)->assertDontSee('Approved, not published');
});

it('never lets a preview be indexed or declare a canonical URL', function () {
    $draft = seoVersion($this->page, $this->author, 'managed-offices', ['title' => 'Draft title', 'indexable' => true]);

    actingAs($this->author)->get(RevisionPreviewController::url($draft))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('rel="canonical"', false);
});

it('refuses markup in search-engine fields', function () {
    expect(fn () => seoVersion($this->page, $this->author, 'managed-offices', ['title' => '<script>alert(1)</script>']))
        ->toThrow(ValidationException::class, 'Use plain text: < and > are not allowed.');
});
