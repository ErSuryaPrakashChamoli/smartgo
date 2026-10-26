<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->staff = [
        User::factory()->withRole(StaffRole::MarketingManager)->create(),
        User::factory()->withRole(StaffRole::MarketingManager)->create(),
        User::factory()->withRole(StaffRole::SuperAdmin)->create(),
    ];
    $this->home = Page::factory()->published()->create(['title' => 'Welcome', 'slug' => 'welcome']);
    $this->about = Page::factory()->published()->create(['title' => 'About SmartGro', 'slug' => 'about']);
    $this->careers = Page::factory()->published()->create(['title' => 'Careers', 'slug' => 'careers']);
    $this->privacy = Page::factory()->published()->create(['title' => 'Privacy', 'slug' => 'legal/privacy']);
});

/**
 * Publishes a menu version through the workflow: author, approver and publisher are different people.
 *
 * @param  list<array<string, mixed>>  $items
 */
function publishShellMenu(string $handle, array $items): void
{
    [$author, $approver, $publisher] = test()->staff;
    $draft = app(CreateDraft::class)->handle(NavigationMenu::query()->where('handle', $handle)->sole(), $author, ['items' => $items]);

    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);
}

/**
 * The page's HTML with runs of whitespace collapsed, so assertions check structure, not formatting.
 */
function shellHtml(string $path): string
{
    return (string) preg_replace('/\s+/', ' ', (string) get($path)->assertOk()->getContent());
}

/**
 * @return array{label: string, type: string, page_id: int, url: null}
 */
function shellPageLink(string $label, Page $page): array
{
    return ['label' => $label, 'type' => 'page', 'page_id' => $page->id, 'url' => null];
}

it('shows the published menus in the header, the phone menu, the action bar and the footer, in order', function () {
    publishShellMenu('main', [
        shellPageLink('About', $this->about),
        ['label' => 'Company', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => [
            shellPageLink('Careers', $this->careers),
            ['label' => 'Partner site', 'type' => 'url', 'page_id' => null, 'url' => 'https://partner.example/smartgro'],
        ]],
    ]);
    publishShellMenu('actions', [shellPageLink('Contact us', $this->about), shellPageLink('Visit us', $this->careers)]);
    publishShellMenu('footer', [['label' => 'Explore', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => [shellPageLink('About us', $this->about)]]]);
    publishShellMenu('legal', [shellPageLink('Privacy', $this->privacy)]);

    $html = shellHtml('/welcome');
    $desktop = str($html)->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString();
    $phone = str($html)->after('id="mobile-menu"')->before('</nav>')->toString();

    expect($desktop)
        ->toMatch('#href="/about"[^>]*>About</a>.*popovertarget="nav-group-1"[^>]*> Company .*href="/careers"[^>]*>Careers</a>.*href="https://partner.example/smartgro" rel="noopener"#')
        ->and($phone)->toMatch('#href="/about"[^>]*>About</a>.*<summary[^>]*> Company .*href="/careers"#')
        ->and($phone)->toMatch('#Visit us.*Contact us#')
        ->and($html)->toMatch('#<div class="hidden items-center gap-2 lg:flex">.*Contact us.*Visit us#')
        ->and($html)->toContain('<nav aria-label="Explore" class="flex flex-col gap-3">')
        ->and($html)->toMatch('#<nav aria-label="Legal">.*href="/legal/privacy"[^>]*>Privacy</a>#')
        ->and($html)->toContain('class="min-h-dvh pb-24 lg:pb-0"');
});

it('marks the link to the current page', function () {
    publishShellMenu('main', [shellPageLink('About', $this->about)]);

    $html = shellHtml('/about');

    expect(str($html)->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString())->toMatch('#href="/about" aria-current="page"#')
        ->and(str($html)->after('id="mobile-menu"')->before('</nav>')->toString())->toMatch('#href="/about" aria-current="page"#')
        ->and(shellHtml('/welcome'))->not->toContain('aria-current="page"');
});

it('hides links to unpublished pages and follows a page to its new address', function () {
    $draftOnly = Page::factory()->create(['title' => 'Coming soon', 'slug' => 'coming-soon']);
    publishShellMenu('main', [shellPageLink('Soon', $draftOnly), shellPageLink('About', $this->about)]);
    $this->about->forceFill(['slug' => 'about-us'])->save();

    get('/welcome')
        ->assertDontSee('Soon')
        ->assertDontSee('/coming-soon')
        ->assertSee('href="/about-us"', false)
        ->assertDontSee('href="/about"', false);
});

it('never outputs an unsafe address stored in a live menu, however it got there', function () {
    publishShellMenu('legal', [shellPageLink('Privacy', $this->privacy)]);
    NavigationItem::query()->update(['link_type' => 'external', 'linkable_type' => null, 'linkable_id' => null, 'url' => 'javascript:alert(1)']);

    expect(shellHtml('/welcome'))->not->toContain('javascript:')->not->toContain('aria-label="Legal"');
});

it('shows no menus, buttons or placeholder links until menus are published', function () {
    get('/welcome')
        ->assertOk()
        ->assertDontSee('href="#"', false)
        ->assertDontSee('popovertarget="mobile-menu"', false)
        ->assertDontSee('aria-label="Main"', false)
        ->assertDontSee('aria-label="Legal"', false)
        ->assertDontSee('Book a tour')
        ->assertDontSee('pb-24', false)
        ->assertSee('Coworking and managed workspaces in Noida, Delhi NCR.');
});

it('never shows a menu version that is not published yet', function () {
    publishShellMenu('main', [shellPageLink('Live label', $this->about)]);
    [$author, $approver] = $this->staff;
    $draft = app(CreateDraft::class)->handle(NavigationMenu::query()->where('handle', 'main')->sole(), $author, ['items' => [shellPageLink('Approved but unpublished', $this->about)]]);
    app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver);

    get('/welcome')->assertSee('Live label')->assertDontSee('Approved but unpublished');
});

it('keeps the Phase 2 design previews on their sample menus', function () {
    publishShellMenu('main', [shellPageLink('Live label', $this->about)]);

    get('/design/home')->assertOk()->assertSee('Workspaces')->assertDontSee('Live label');
});
