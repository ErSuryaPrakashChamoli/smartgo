<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Pages\SearchMetadataReport;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Post;
use App\Models\Property;
use App\Models\User;
use App\Services\Website\SearchMetadata;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    $this->seed(ReferenceDataSeeder::class);
    config(['app.url' => 'https://www.smartgro.example']);
    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $property = publishPropertyProfileForTest(Property::query()->where('code', 'A-70')->sole());
    offerForTest($property, 'private_office');
});

/**
 * A page published through the workflow with the search title and description given.
 *
 * @param  array<string, mixed>  $seo
 */
function publishSearchPageForTest(User $author, User $approver, User $publisher, string $slug, array $seo): Page
{
    $page = Page::factory()->create();
    $draft = app(CreateDraft::class)->handle($page, $author, [
        'title' => 'Page '.$slug, 'slug' => $slug, 'template' => 'standard',
        'blocks' => [['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Heading of '.$slug, 'intro' => 'An introduction to '.$slug.' that is long enough to describe the page to visitors.', 'link' => null]]],
        'seo' => $seo,
    ]);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);

    return $page->fresh();
}

it('reports, for every page search engines may index, exactly the title and description that page writes', function () {
    publishSearchPageForTest($this->author, $this->approver, $this->publisher, 'about', ['title' => 'About SmartGro | Coworking in Noida', 'description' => 'Who runs SmartGro.']);
    publishSearchPageForTest($this->author, $this->approver, $this->publisher, 'contact', []);
    Post::factory()->published()->create(['slug' => 'choosing', 'title' => 'Choosing a workspace', 'excerpt' => 'What to look for.']);
    CaseStudy::factory()->published()->create(['slug' => 'a-story']);

    $rows = app(SearchMetadata::class)->rows();

    expect($rows)->toHaveCount(16);

    foreach ($rows as $row) {
        $html = get((string) str($row['url'])->after('https://www.smartgro.example')->whenEmpty(fn () => '/'))->assertOk()->getContent();
        preg_match('#<title>(.*?)</title>#s', $html, $title);
        preg_match('#<meta name="description" content="([^"]*)">#', $html, $description);

        expect(html_entity_decode($title[1], ENT_QUOTES | ENT_HTML5))->toBe($row['title'], $row['url'])
            ->and(isset($description[1]) ? html_entity_decode($description[1], ENT_QUOTES | ENT_HTML5) : null)->toBe(blank($row['description']) ? null : $row['description'], $row['url']);
    }
});

it('flags titles and descriptions two pages share and pages without a description, but expects further pages of a list to repeat their list', function () {
    $first = publishSearchPageForTest($this->author, $this->approver, $this->publisher, 'offices-noida', ['title' => 'Offices in Noida', 'description' => 'Offices in Noida from SmartGro.']);
    publishSearchPageForTest($this->author, $this->approver, $this->publisher, 'noida-offices', ['title' => 'offices in  noida', 'description' => 'Offices in Noida from SmartGro.']);
    Post::factory()->published()->create(['slug' => 'no-summary', 'title' => 'No summary', 'excerpt' => null]);
    Post::factory()->published()->count(12)->sequence(fn ($sequence) => ['slug' => 'article-'.$sequence->index, 'title' => 'Article '.$sequence->index, 'excerpt' => 'Summary '.$sequence->index])->create();

    $rows = app(SearchMetadata::class)->rows()->keyBy('key');

    expect($rows['/offices-noida']['findings'])->toBe([SearchMetadata::DUPLICATE_TITLE, SearchMetadata::DUPLICATE_DESCRIPTION])
        ->and($rows['/offices-noida']['edit_url'])->toEndWith('/website/pages/'.$first->id.'/edit')
        ->and($rows['/noida-offices']['findings'])->toBe([SearchMetadata::DUPLICATE_TITLE, SearchMetadata::DUPLICATE_DESCRIPTION])
        ->and($rows['/insights/no-summary']['findings'])->toBe([SearchMetadata::MISSING_DESCRIPTION])
        ->and($rows['/insights']['findings'])->toBe([SearchMetadata::EXPECTED_REPEAT])
        ->and($rows['/insights?page=2']['findings'])->toBe([SearchMetadata::EXPECTED_REPEAT])
        ->and($rows['/locations/noida/a-70']['findings'])->toBe([])
        ->and($rows['/locations/noida/a-70']['edit_url'])->not->toBeNull()
        ->and($rows['/workspaces']['edit_url'])->toBeNull();
});

it('shows the report to staff who see website content, filtered to what needs looking at, and changes nothing', function () {
    publishSearchPageForTest($this->author, $this->approver, $this->publisher, 'offices-noida', ['title' => 'Offices in Noida', 'description' => 'Same.']);
    publishSearchPageForTest($this->author, $this->approver, $this->publisher, 'noida-offices', ['title' => 'Offices in Noida', 'description' => 'Same.']);
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete)\b/i', $query->sql) === 1 && ! str_contains($query->sql, '`sessions`') && ! str_contains($query->sql, '`cache')) {
            $writes[] = $query->sql;
        }
    });
    actingAs($this->author);

    Livewire::test(SearchMetadataReport::class)
        ->assertSee(['Offices in Noida', 'Title used by another page', 'Workspaces · SmartGro'])
        ->filterTable('finding', 'any')
        ->assertCanSeeTableRecords(['/offices-noida', '/noida-offices'])
        ->assertCanNotSeeTableRecords(['/workspaces', '/locations/noida/a-70']);

    expect($writes)->toBe([]);

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create());
    get(SearchMetadataReport::getUrl())->assertForbidden();
});
