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
use App\Models\Post;
use App\Models\Product;
use App\Models\Property;
use App\Models\Revision;
use App\Models\SiteSetting;
use App\Models\TourAvailabilityRule;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

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

it('links a workspace type to its page, or to the enquiry form while it has none and the form is open, never an inactive type, and opens a long group as a wide panel (OD-143, OD-145)', function () {
    seed(ReferenceDataSeeder::class);
    $products = Product::query()->orderBy('sort_order')->get();
    publishShellMenu('main', [['label' => 'Spaces', 'type' => 'group', 'children' => [
        ['label' => 'All workspace types', 'type' => 'section', 'section' => 'workspace_types'],
        ...$products->map(fn (Product $product): array => ['label' => $product->name, 'type' => 'category', 'product_id' => $product->id])->all(),
    ]]]);
    $mainMenu = fn (): string => str(shellHtml('/welcome'))->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString();

    expect($mainMenu())->not->toContain('/workspaces/');

    $this->privacy->forceDelete();
    $a70 = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    offerForTest($a70, 'meeting_room');
    offerForTest($a70, 'virtual_office', published: false);

    expect($mainMenu())->toContain('href="/workspaces/meeting-rooms"')->not->toContain('href="/workspaces/virtual-office"')
        ->not->toContain('workspace=virtual-office')->not->toContain('w-[min(36rem');

    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);

    expect($mainMenu())->toContain('href="/enquire?workspace=virtual-office"')->toContain('href="/workspaces/meeting-rooms"')
        ->and(fn () => Product::query()->where('key', 'virtual_office')->update(['is_active' => false]))->not->toThrow(Throwable::class)
        ->and($mainMenu())->not->toContain('virtual-office');
    Product::query()->where('key', 'virtual_office')->update(['is_active' => true]);

    $products->reject(fn (Product $product): bool => $product->key === 'meeting_room')->each(fn (Product $product) => offerForTest($a70, $product->key));

    expect($mainMenu())->toContain('href="/workspaces/virtual-office"')->toContain('w-[min(36rem,calc(100vw-2.5rem))]');
});

it('publishes a drop-down with sub-groups, shows each sub-group with its links on wide screens and phones, and hides an empty one (OD-145)', function () {
    $draftOnly = Page::factory()->create(['title' => 'Coming soon', 'slug' => 'coming-soon']);
    $items = [['label' => 'Spaces', 'type' => 'group', 'children' => [
        shellPageLink('About us', $this->about),
        ['label' => 'Coworking', 'type' => 'group', 'children' => [shellPageLink('Welcome desk', $this->home), shellPageLink('About desk', $this->about)]],
        ['label' => 'Unpublished', 'type' => 'group', 'children' => [shellPageLink('Soon', $draftOnly)]],
    ]]];
    publishShellMenu('main', $items);

    $html = shellHtml('/welcome');
    $wide = str($html)->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString();
    $phone = str($html)->after('id="mobile-menu"')->before('</nav>')->toString();

    expect($wide)->toContain('data-flyout')
        ->toMatch('#<button type="button" data-flyout-tab aria-controls="nav-group-0-1"[^>]*>.*Coworking.*</button>#s')
        ->toMatch('#<div id="nav-group-0-1"[^>]*data-flyout-pane> <ul aria-label="Coworking"[^>]*>.*href="/welcome"[^>]*>.*Welcome desk.*href="/about"[^>]*>.*About desk#s')
        ->toMatch('#href="/about"[^>]*>About us</a>#')->not->toContain('Unpublished')->not->toContain('Soon')
        ->and($phone)->toMatch('#<summary[^>]*>.*Coworking.*</summary>\s*<ul aria-label="Coworking"[^>]*>.*Welcome desk.*About desk#s')->not->toContain('Unpublished')
        ->and(NavigationMenu::query()->where('handle', 'main')->sole()->toRevisionPayload()['items'][0]['children'][1])->toMatchArray(['label' => 'Coworking', 'type' => 'group'])
        ->and(NavigationMenu::query()->where('handle', 'main')->sole()->toRevisionPayload()['items'][0]['children'][1]['children'])->toHaveCount(2);
});

it('shows each drop-down category\'s and link\'s description and chosen icon, read as descriptions, and notes a list of sample pages (OD-149)', function () {
    $sample = Page::factory()->published()->create(['title' => 'Desks', 'slug' => 'workspace-solutions/desks', 'template' => 'sample']);
    $iconPath = fn (string $name): string => (string) preg_replace('/\s+/', ' ', str(svg('heroicon-o-'.$name)->contents())->match('/ d="([^"]+)"/')->toString());
    publishShellMenu('main', [['label' => 'Solutions', 'type' => 'group', 'children' => [
        ['label' => 'Workspaces', 'type' => 'group', 'description' => 'Desks and offices.', 'icon' => 'building-office', 'children' => [
            [...shellPageLink('Dedicated Desks', $sample), 'description' => 'A desk of your own.', 'icon' => 'computer-desktop'],
        ]],
        ['label' => 'Business Solutions', 'type' => 'group', 'children' => [shellPageLink('Careers', $this->careers)]],
    ]]]);

    $html = shellHtml('/welcome');
    $wide = str($html)->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString();
    $phone = str($html)->after('id="mobile-menu"')->before('</nav>')->toString();

    expect($wide)->toMatch('#data-flyout-tab aria-controls="nav-group-0-0" aria-describedby="nav-group-0-0-about"[^>]*>.*Workspaces <span id="nav-group-0-0-about" aria-hidden="true"[^>]*>Desks and offices.</span>#s')
        ->toMatch('#<a href="/workspace-solutions/desks"[^>]*aria-describedby="nav-link-0-0-0"[^>]*>.*Dedicated Desks.*<span id="nav-link-0-0-0" aria-hidden="true"[^>]*>A desk of your own.</span>#s')
        ->toContain($iconPath('computer-desktop'))->toContain($iconPath('building-office'))
        ->and(str($wide)->after('id="nav-group-0-0"')->before('id="nav-group-0-1"')->toString())->toContain('Sample pages: example text, not yet confirmed by SmartGro.')
        ->and(str($wide)->after('id="nav-group-0-1"')->toString())->toContain('href="/careers"')->not->toContain('Sample pages')->not->toContain('aria-describedby')
        ->and($phone)->toContain('Desks and offices.')->toContain('A desk of your own.')->toContain('Sample pages: example text');

    $sample->forceFill(['status' => 'draft'])->save();

    expect(shellHtml('/welcome'))->not->toContain('Dedicated Desks')->not->toContain('Desks and offices.')->not->toContain('Sample pages');
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

it('shows the owner\'s header in order, with Book a Tour as the main button only while a tour can be requested (OD-140)', function () {
    $solutions = Page::factory()->published()->create(['title' => 'Solutions', 'slug' => 'solutions']);
    $community = Page::factory()->published()->create(['title' => 'Community', 'slug' => 'community']);
    Product::factory()->create(['is_active' => true]);
    $listed = Property::factory()->listed()->create();
    Post::factory()->published()->create();
    publishShellMenu('main', [
        ['label' => 'Spaces', 'type' => 'section', 'section' => 'workspace_types'],
        ['label' => 'Locations', 'type' => 'section', 'section' => 'locations'],
        shellPageLink('Solutions', $solutions),
        shellPageLink('Community', $community),
        shellPageLink('About', $this->about),
        ['label' => 'Resources', 'type' => 'section', 'section' => 'insights'],
    ]);
    publishShellMenu('actions', [
        ['label' => 'Enquire', 'type' => 'section', 'section' => 'enquire'],
        ['label' => 'Book a Tour', 'type' => 'section', 'section' => 'book_a_tour'],
    ]);
    $order = '#href="/workspaces"[^>]*>Spaces</a>.*href="/locations"[^>]*>Locations</a>.*href="/solutions"[^>]*>Solutions</a>.*href="/community"[^>]*>Community</a>.*href="/about"[^>]*>About</a>.*href="/insights"[^>]*>Resources</a>#';

    $html = shellHtml('/welcome');
    expect(str($html)->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString())->toMatch($order)
        ->and(str($html)->after('id="mobile-menu"')->before('</nav>')->toString())->toMatch($order)
        ->and($html)->toMatch('#<div class="hidden items-center gap-2 lg:flex"> <div class=""> <a href="/enquire"[^>]*> Enquire </a> </div> </div>#')
        ->and($html)->not->toContain('Book a Tour');

    TourAvailabilityRule::factory()->for($listed)->create();

    $html = shellHtml('/welcome');
    expect($html)->toMatch('#<div class="hidden items-center gap-2 lg:flex"> <div class="hidden xl:block"> <a href="/enquire"[^>]*> Enquire </a> </div> <div class=""> <a href="/book-a-tour"[^>]*class="[^"]*bg-leaf-700[^"]*"> Book a Tour </a>#')
        ->and(str($html)->after('id="mobile-menu"')->toString())->toMatch('#href="/book-a-tour"[^>]*> Book a Tour </a>.*href="/enquire"[^>]*> Enquire </a>#')
        ->and(str($html)->afterLast('fixed inset-x-0 bottom-0')->toString())->toMatch('#href="/book-a-tour"[^>]*> Book a Tour </a>#');

    $solutions->forceFill(['status' => 'draft'])->save();
    $this->privacy->forceFill(['status' => 'draft'])->save();

    $html = shellHtml('/welcome');
    expect(str($html)->after('<nav aria-label="Main" class="hidden lg:block">')->before('</nav>')->toString())->not->toContain('Solutions')->toContain('Community')
        ->and($html)->not->toContain('Book a Tour')->not->toContain('href="/enquire"');
});
