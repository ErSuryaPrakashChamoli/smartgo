<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UpdateDraft;
use App\Enums\Access\StaffRole;
use App\Enums\Properties\PropertyStatus;
use App\Models\NavigationItem;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Validation\ValidationException;

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
        ->and($html)->toContain('class="min-h-dvh pb-24 lg:pb-0"')
        ->and($html)->toContain('<html lang="en-IN" class="scroll-pt-24 scroll-pb-24 lg:scroll-pb-0">');
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

it('shows links to parts of the website and to locations only while they have something to show', function () {
    $property = Property::factory()->create(['code' => 'A-70', 'slug' => 'a-70']);
    publishShellMenu('main', [
        ['label' => 'Locations', 'type' => 'section', 'section' => 'locations'],
        ['label' => 'FAQs', 'type' => 'section', 'section' => 'faqs'],
        ['label' => 'Visit A-70', 'type' => 'property', 'property_id' => $property->id],
        ['label' => 'Contact', 'type' => 'section', 'section' => 'contact'],
    ]);
    $mainMenu = fn (): string => str(shellHtml('/welcome'))->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString();

    expect($mainMenu())->toContain('href="/#contact"')
        ->not->toContain('Locations')
        ->not->toContain('FAQs')
        ->not->toContain('Visit A-70');

    $property->forceFill(['operational_status' => PropertyStatus::Open])->save();

    expect($mainMenu())->not->toContain('Visit A-70');

    publishPropertyProfileForTest($property);

    expect($mainMenu())->toMatch('#href="/locations"[^>]*>Locations</a>.*href="/locations/'.$property->city->slug.'/a-70"[^>]*>Visit A-70</a>.*href="/\#contact"#')
        ->not->toContain('FAQs');
});

it('shows a legal link in the footer only once its legal page has been reviewed, approved and published', function () {
    [$author, $approver, $publisher] = $this->staff;
    $terms = Page::factory()->create();
    publishShellMenu('legal', [shellPageLink('Terms of use', $terms)]);
    $text = [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Reviewed wording.</p>']]];
    $draft = app(CreateDraft::class)->handle($terms, $author, ['title' => 'Terms of use', 'slug' => 'legal/terms', 'template' => 'legal', 'legal_review_reference' => null, 'blocks' => $text]);

    expect(shellHtml('/welcome'))->not->toContain('aria-label="Legal"')->not->toContain('Terms of use')
        ->and(fn () => app(SubmitRevision::class)->handle($draft, $author))->toThrow(ValidationException::class, 'Legal pages need the reference of their review by counsel');

    $reviewed = app(UpdateDraft::class)->handle($draft, $author, [...$draft->payload, 'legal_review_reference' => 'COUNSEL-2026-01']);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($reviewed, $author), $approver), $publisher);

    expect(shellHtml('/welcome'))->toMatch('#<nav aria-label="Legal">.*href="/legal/terms"[^>]*>Terms of use</a>#');
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
        ->assertSee('<html lang="en-IN" class="scroll-pt-24">', false)
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

it('adds call and WhatsApp links for the published numbers to the phone action bar, beside the main button only (FR-DISC-6)', function () {
    SiteSetting::query()->where('handle', 'contact')->sole()->forceFill([
        'values' => ['phone' => '+91 120 400 0000', 'whatsapp' => '+911204000000', 'email' => 'hello@example.com'],
        'published_revision_id' => Revision::factory()->create()->id,
    ])->save();

    $html = shellHtml('/welcome');
    expect($html)->toContain('class="min-h-dvh pb-24 lg:pb-0"')
        ->and(str($html)->afterLast('fixed inset-x-0 bottom-0')->toString())->toMatch('#href="tel:\+911204000000".*Call </a>.*href="https://wa.me/911204000000" rel="noopener".*WhatsApp </a>#')
        ->and(str($html)->afterLast('fixed inset-x-0 bottom-0')->toString())->not->toContain('mailto:');

    publishShellMenu('actions', [shellPageLink('Contact us', $this->about), shellPageLink('Visit us', $this->careers)]);

    $bar = str(shellHtml('/welcome'))->afterLast('fixed inset-x-0 bottom-0')->toString();
    expect($bar)->toMatch('#WhatsApp </a>.*href="/careers"[^>]*> Visit us#')
        ->and($bar)->not->toContain('Contact us');
});

it('never asks Vite for the 3D viewer on a public page, so no 3D bytes load (Phase 7 AC 3, BR-3D-6)', function () {
    $vite = new class extends Vite
    {
        /** @var list<string> */
        public array $requested = [];

        public function __invoke($entrypoints, $buildDirectory = null)
        {
            $this->requested = [...$this->requested, ...(array) $entrypoints];

            return parent::__invoke($entrypoints, $buildDirectory);
        }
    };
    app()->instance(Vite::class, $vite);

    foreach (['/', '/about', '/locations', '/workspaces'] as $path) {
        get($path)->assertOk();
    }
    get('/no-such-page')->assertNotFound();

    expect($vite->requested)->toContain('resources/js/app.js')
        ->and(array_unique($vite->requested))->not->toContain('resources/js/twin-viewer.js');
});
