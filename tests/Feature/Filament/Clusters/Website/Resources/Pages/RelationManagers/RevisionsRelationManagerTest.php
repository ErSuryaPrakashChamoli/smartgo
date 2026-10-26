<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\Pages\Pages\EditPage;
use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create(['name' => 'Asha Author']);
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create(['name' => 'Ravi Reviewer']);
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create(['name' => 'Priya Publisher']);
    $this->page = Page::factory()->create();
    $this->first = publishedHistoryVersion($this->page, 'First title', $this->author, $this->approver, $this->publisher);
    $this->second = publishedHistoryVersion($this->page, 'Second title', $this->author, $this->publisher, $this->approver);
});

function publishedHistoryVersion(Page $page, string $title, User $author, User $approver, User $publisher): Revision
{
    $draft = app(CreateDraft::class)->handle($page, $author, [
        'title' => $title, 'slug' => 'about', 'template' => 'standard',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Managed offices.</p>']]],
    ]);

    return app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver), $publisher);
}

function versionHistory(Page $page): Testable
{
    return Livewire::test(RevisionsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditPage::class]);
}

it('lists every version with who wrote, approved and published it', function () {
    actingAs($this->author);

    versionHistory($this->page)
        ->assertCanSeeTableRecords([$this->first, $this->second])
        ->assertSee(['Previously published', 'Published', 'Asha Author', 'Ravi Reviewer', 'Priya Publisher']);
});

it('restores an earlier version as a new draft and leaves the history and live page unchanged', function () {
    actingAs($this->publisher);

    versionHistory($this->page)
        ->callAction(TestAction::make('restore')->table($this->first))
        ->assertNotified('Version 1 restored as a new draft')
        ->assertDispatched('revisions-changed');

    expect($this->page->openRevision())
        ->number->toBe(3)
        ->state->toBe(RevisionState::Draft)
        ->payload->toEqual($this->first->fresh()->payload)
        ->and($this->first->fresh()->state)->toBe(RevisionState::Superseded)
        ->and($this->page->fresh()->title)->toBe('Second title');
});

it('does not let the approver of a version restore it', function () {
    actingAs($this->approver);

    versionHistory($this->page)->assertActionDisabled(TestAction::make('restore')->table($this->first));

    actingAs($this->author);

    versionHistory($this->page)->assertActionEnabled(TestAction::make('restore')->table($this->first));
});

it('shows the audit trail of a version', function () {
    actingAs($this->author);

    versionHistory($this->page)
        ->mountAction(TestAction::make('activity')->table($this->first))
        ->assertMountedActionModalSee(['Draft started', 'Content submitted for approval', 'Content approved', 'Content published', 'Ravi Reviewer']);
});
