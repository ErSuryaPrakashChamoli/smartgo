<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\Faq;
use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\PropertyProfile;
use App\Models\Revision;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SampleContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed(ReferenceDataSeeder::class);
    app(SyncAccessControl::class)->handle();
    // As on the owner's server (OD-152): the seeder must allow sample content for its own run only.
    config(['backoffice.sample_content' => false]);
});

/**
 * Publish a menu or page version by hand, as two other staff.
 *
 * @param  array<string, mixed>  $payload
 */
function publishAsOtherStaff(Page|NavigationMenu $content, array $payload, User $author): void
{
    $approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $draft = app(CreateDraft::class)->handle($content, $author, $payload);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $author);
}

it('publishes the sample pages, articles, FAQs and menus through the workflow as the dummy staff, and drafts each profile', function () {
    seed(SampleContentSeeder::class);

    $author = User::query()->where('email', 'marketing@dummy.test')->sole();
    $approver = User::query()->where('email', 'marketing2@dummy.test')->sole();
    $published = Revision::query()->where('state', RevisionState::Published)->get();
    expect(Page::query()->where('template', 'sample')->where('status', 'published')->count())->toBe(29)
        ->and(Post::query()->where('status', 'published')->count())->toBe(10)
        ->and(Faq::query()->where('status', 'published')->orderBy('sort_order')->pluck('question')->all())
        ->toBe(['How do I visit a location?', 'Are prices shown on the website?', 'Which kinds of workspace can I ask about?', 'Does an enquiry reserve a workspace?', 'Where are SmartGro\'s locations?'])
        ->and(NavigationMenu::query()->whereNotNull('published_revision_id')->orderBy('handle')->pluck('handle')->all())->toBe(['footer', 'main'])
        ->and(NavigationMenu::query()->where('handle', 'main')->sole()->items()->whereNull('parent_id')->orderBy('sort_order')->pluck('label')->all())->toBe(['Spaces', 'Locations', 'Solutions', 'Community', 'About', 'Resources']);

    // OD-168: every main item is a drop-down; each link has a description and, outside Spaces' workspace types and the locations, an icon.
    $main = collect(NavigationMenu::query()->where('handle', 'main')->sole()->toRevisionPayload()['items'])->keyBy('label');
    $links = fn (string $label): array => array_column($main[$label]['children'], 'label');
    expect($main->every(fn (array $item): bool => $item['type'] === 'group' && $item['children'] !== []))->toBeTrue()
        ->and($links('Spaces'))->toBe(['All workspace types', ...Product::query()->shown()->orderBy('sort_order')->pluck('name')->all()])
        ->and($links('Locations'))->toBe(['All locations', 'SmartGro A-23', 'SmartGro A-70', 'SmartGro A-71', 'SmartGro H-105'])
        ->and($main['Locations']['children'][2])->toMatchArray(['type' => 'property', 'description' => 'Sector 2, Noida'])
        ->and($links('Community'))->toBe(['Community at SmartGro', 'Founders\' breakfast', 'Workspace open house', 'Partner with SmartGro'])
        ->and($links('About'))->toBe(['About SmartGro', 'Careers', 'Contact', 'Book a tour', 'Accessibility'])
        ->and($links('Resources'))->toBe(['Insights', 'FAQs', 'Customer stories', 'Search'])
        ->and(collect(['Spaces', 'Locations', 'Community', 'About', 'Resources'])->flatMap(fn (string $label): array => $main[$label]['children'])->every(fn (array $link): bool => filled($link['description'])))->toBeTrue()
        ->and(collect(['Community', 'About', 'Resources'])->flatMap(fn (string $label): array => $main[$label]['children'])->every(fn (array $link): bool => filled($link['icon'])))->toBeTrue();
    expect($published)->toHaveCount(29 + 10 + 5 + 2)
        ->and($published->every(fn (Revision $revision): bool => $revision->author_id === $author->id && $revision->reviewer_id === $approver->id && $revision->published_by === $author->id))->toBeTrue();
    expect(PropertyProfile::query()->whereNotNull('published_revision_id')->exists())->toBeFalse()
        ->and(Revision::query()->where('revisionable_type', PropertyProfile::class)->where('state', RevisionState::Draft)->where('author_id', $author->id)->count())->toBe(4);
    expect(config('backoffice.sample_content'))->toBeFalse();
});

it('adds no version and no account when run again', function () {
    seed(SampleContentSeeder::class);
    $revisions = Revision::query()->count();
    $passwords = User::query()->where('email', 'like', '%@dummy.test')->pluck('password', 'email');

    seed(SampleContentSeeder::class);

    expect(Revision::query()->count())->toBe($revisions)
        ->and(User::query()->where('email', 'like', '%@dummy.test')->pluck('password', 'email'))->toEqual($passwords);
});

it('leaves alone what an active person published and replaces what only a deactivated account published', function () {
    $person = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $helper = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $about = Page::query()->forceCreate(['status' => 'draft']);
    publishAsOtherStaff($about, ['title' => 'About us', 'slug' => 'about', 'template' => 'standard', 'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Our own words.</p>']]]], $person);
    publishAsOtherStaff(NavigationMenu::query()->where('handle', 'main')->sole(), ['items' => [['label' => 'Contact', 'type' => 'section', 'section' => 'contact']]], $helper);
    $helper->forceFill(['is_active' => false])->save();

    $summary = app(SampleContentSeeder::class)->seedWith(
        User::factory()->withRole(StaffRole::MarketingManager)->create(),
        User::factory()->withRole(StaffRole::MarketingManager)->create(),
        User::factory()->withRole(StaffRole::MarketingManager)->create(),
    );

    expect($summary['left_alone'])->toBe(['Page about'])
        ->and($about->fresh()->title)->toBe('About us')
        ->and(NavigationMenu::query()->where('handle', 'main')->sole()->items()->whereNull('parent_id')->orderBy('sort_order')->pluck('label')->all())->toBe(['Spaces', 'Locations', 'Solutions', 'Community', 'About', 'Resources']);
});

it('refuses to write sample content in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    $staff = User::factory()->withRole(StaffRole::MarketingManager)->count(2)->create();

    expect(fn () => app(SampleContentSeeder::class)->seedWith($staff[0], $staff[1], $staff[0]))->toThrow(RuntimeException::class, 'local databases only');

    expect(Revision::query()->exists())->toBeFalse()
        ->and(config('backoffice.sample_content'))->toBeFalse();
});
