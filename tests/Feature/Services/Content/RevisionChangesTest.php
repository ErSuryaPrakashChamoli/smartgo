<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use App\Services\Content\RevisionChanges;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();
    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
 * @return array<string, mixed>
 */
function changesPagePayload(string $title, array $blocks, ?string $description = null): array
{
    return ['title' => $title, 'slug' => 'about', 'template' => 'standard', 'blocks' => $blocks, 'seo' => ['title' => null, 'description' => $description, 'indexable' => true, 'image_asset_id' => null]];
}

function changesTextSection(?string $heading, string $body): array
{
    return ['type' => 'text', 'data' => ['heading' => $heading, 'body' => $body]];
}

it('lists each changed field with its live and new value as plain text, and sections added, replaced or removed', function () {
    $live = changesPagePayload('About SmartGro', [
        changesTextSection('Who we are', '<p>Managed offices in <strong>Noida</strong>.</p>'),
        changesTextSection('Our teams', '<p>Teams.</p>'),
        changesTextSection('Old section', '<p>Gone soon.</p>'),
    ]);
    $draft = changesPagePayload('About us', [
        changesTextSection('Who we are', '<p>Managed offices in <strong>Noida and Delhi</strong>.</p>'),
        ['type' => 'locations', 'data' => ['heading' => null, 'intro' => null, 'city_id' => null]],
    ], 'Flexible workspaces in Delhi NCR.');

    expect(app(RevisionChanges::class)->between($live, $draft))->toBe([
        ['field' => 'Title', 'before' => 'About SmartGro', 'after' => 'About us'],
        ['field' => 'Description in search results', 'before' => '—', 'after' => 'Flexible workspaces in Delhi NCR.'],
        ['field' => 'Section 1 (Text section): body', 'before' => 'Managed offices in Noida.', 'after' => 'Managed offices in Noida and Delhi.'],
        ['field' => 'Section 2', 'before' => 'Text section', 'after' => 'Location list'],
        ['field' => 'Section 3', 'before' => 'Text section', 'after' => 'Removed'],
    ])->and(app(RevisionChanges::class)->between($live, $live))->toBe([]);
});

it('names nested items by their number', function () {
    $faq = fn (string $answer): array => ['title' => 'FAQs', 'blocks' => [['type' => 'faq', 'data' => ['heading' => null, 'items' => [['question' => 'Parking?', 'answer' => 'Yes.'], ['question' => 'Wi-Fi?', 'answer' => $answer]]]]]];

    expect(app(RevisionChanges::class)->between($faq('Yes.'), $faq('Yes, 1 Gbps.')))->toBe([
        ['field' => 'Section 1 (FAQs): item 2 answer', 'before' => 'Yes.', 'after' => 'Yes, 1 Gbps.'],
    ]);
});

it('shows the approver what a submitted version changes compared with the live page', function () {
    $page = Page::factory()->create();
    $first = app(CreateDraft::class)->handle($page, $this->author, changesPagePayload('About SmartGro', [changesTextSection('Who we are', '<p>Managed offices.</p>')]));
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($first, $this->author), $this->approver), $this->publisher);
    $second = app(CreateDraft::class)->handle($page->fresh(), $this->author, changesPagePayload('About us', [changesTextSection('Who we are', '<p>Managed offices and hot desks.</p>')]));
    app(SubmitRevision::class)->handle($second, $this->author);
    actingAs($this->approver);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertActionVisible(TestAction::make('changes'))
        ->mountAction(TestAction::make('changes'))
        ->assertMountedActionModalSee(['What version 2 changes', 'Compared with version 1, the one on the website now.', 'Title', 'About SmartGro', 'About us', 'Section 1 (Text section): body', 'Managed offices.', 'Managed offices and hot desks.']);

    expect(Revision::query()->count())->toBe(2)->and($page->fresh()->title)->toBe('About SmartGro');
});

it('offers no comparison for something never published, and says so when nothing differs', function () {
    $page = Page::factory()->create();
    $first = app(CreateDraft::class)->handle($page, $this->author, changesPagePayload('About SmartGro', [changesTextSection(null, '<p>Managed offices.</p>')]));
    actingAs($this->author);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->assertActionHidden(TestAction::make('changes'));

    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($first, $this->author), $this->approver), $this->publisher);
    app(CreateDraft::class)->handle($page->fresh(), $this->author);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->mountAction(TestAction::make('changes'))
        ->assertMountedActionModalSee('No differences from the live version.');
});
