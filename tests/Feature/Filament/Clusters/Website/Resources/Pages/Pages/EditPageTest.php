<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Filament\Clusters\Website\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Filament\Forms\Components\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create(['name' => 'Asha Author']);
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create(['name' => 'Ravi Reviewer']);
    $this->publisher = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create(['name' => 'Priya Publisher']);
    $this->page = Page::factory()->create();
    $this->draft = app(CreateDraft::class)->handle($this->page, $this->author, editorPayload('About SmartGro'));
});

/**
 * @return array<string, mixed>
 */
function editorPayload(string $title, string $slug = 'about'): array
{
    return ['title' => $title, 'slug' => $slug, 'template' => 'standard', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Managed offices in Noida.</p>']],
    ]];
}

function pageEditor(Page $page): Testable
{
    return Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
}

it('saves changes to the draft and never to the live page', function () {
    actingAs($this->author);
    $undoBuilderFake = Builder::fake();

    pageEditor($this->page)
        ->fillForm(['title' => 'About us', 'slug' => 'about', 'blocks' => [['type' => 'text', 'data' => ['heading' => 'Who we are', 'body' => '<p>New text.</p>']]]])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Draft saved');

    $undoBuilderFake();

    expect($this->draft->fresh()->payload)
        ->toMatchArray(['title' => 'About us'])
        ->and($this->draft->fresh()->payload['blocks'][0]['data'])->toEqual(['heading' => 'Who we are', 'body' => '<p>New text.</p>'])
        ->and($this->page->fresh())->title->toBeNull()->status->toBe('draft');
});

it('saves the search-engine fields with the draft', function () {
    actingAs($this->author);
    $undoBuilderFake = Builder::fake();

    pageEditor($this->page)
        ->assertSchemaStateSet(['seo.indexable' => true])
        ->fillForm(['seo' => ['title' => 'About SmartGro | Coworking in Noida', 'description' => 'Who we are.', 'indexable' => false]])
        ->call('save')
        ->assertHasNoFormErrors();

    $undoBuilderFake();

    expect($this->draft->fresh()->payload['seo'])->toEqual(['title' => 'About SmartGro | Coworking in Noida', 'description' => 'Who we are.', 'indexable' => false, 'image_asset_id' => null])
        ->and($this->page->fresh()->seo)->toBeNull();
});

it('shows a server-side error on the exact field, with the form\'s own item keys', function () {
    $unsafe = [...editorPayload('About SmartGro'), 'blocks' => [['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Go', 'text' => null, 'primary_link' => ['label' => 'Click', 'type' => 'url', 'page_id' => null, 'url' => 'javascript:alert(1)'], 'secondary_link' => null]]]];
    $this->draft->forceFill(['payload' => $unsafe])->save();
    actingAs($this->author);

    $editor = pageEditor($this->page);
    $itemKey = array_key_first($editor->get('data.blocks'));

    expect($itemKey)->not->toBe(0);

    $editor->call('save')->assertHasFormErrors(["blocks.{$itemKey}.data.primary_link.url"]);

    expect($this->draft->fresh()->payload['blocks'][0]['data']['primary_link']['url'])->toBe('javascript:alert(1)');
});

it('counts a second person who saves the draft as an author, so they cannot approve it', function () {
    actingAs($this->approver);
    $undoBuilderFake = Builder::fake();
    pageEditor($this->page)->fillForm(['title' => 'About SmartGro Services'])->call('save')->assertHasNoFormErrors();
    $undoBuilderFake();
    app(SubmitRevision::class)->handle($this->draft, $this->author);

    pageEditor($this->page)
        ->assertActionVisible('approve')
        ->assertActionDisabled('approve');

    expect($this->draft->fresh()->editor_ids)->toBe([$this->approver->id]);
});

it('freezes a submitted version, even against a direct save request', function () {
    actingAs($this->author);

    pageEditor($this->page)->callAction('submit')->assertNotified('Submitted for approval');

    expect($this->draft->fresh()->state)->toBe(RevisionState::Submitted);

    pageEditor($this->page)
        ->assertFormFieldDisabled('title')
        ->assertDontSee('Save draft')
        ->set('data.title', 'Changed after submission')
        ->call('save')
        ->assertNotified('Not allowed');

    expect($this->draft->fresh()->payload['title'])->toBe('About SmartGro');
});

it('lets someone other than the author approve, and shows the author why they cannot', function () {
    app(SubmitRevision::class)->handle($this->draft, $this->author);

    actingAs($this->author);
    pageEditor($this->page)->assertActionDisabled('approve')->assertActionDisabled('reject');

    actingAs($this->approver);
    pageEditor($this->page)->callAction('approve')->assertNotified('Version approved')->assertDispatched('page-workflow-changed');

    expect($this->draft->fresh())->state->toBe(RevisionState::Approved)->reviewer_id->toBe($this->approver->id);
});

it('stops the approver publishing or scheduling, and lets the author publish', function () {
    app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($this->draft, $this->author), $this->approver);

    actingAs($this->approver);
    pageEditor($this->page)->assertActionDisabled('publish')->assertActionDisabled('schedule');

    actingAs($this->author);
    pageEditor($this->page)->callAction('publish')->assertNotified('Page published');

    expect($this->page->fresh())->status->toBe('published')->title->toBe('About SmartGro');
    get('/about')->assertOk();
});

it('requires a note to reject, shows it to the authors and lets them revise the rejected version', function () {
    app(SubmitRevision::class)->handle($this->draft, $this->author);

    actingAs($this->approver);
    pageEditor($this->page)
        ->callAction('reject', ['review_note' => ''])
        ->assertHasActionErrors(['review_note' => 'required']);

    expect($this->draft->fresh()->state)->toBe(RevisionState::Submitted);

    pageEditor($this->page)
        ->callAction('reject', ['review_note' => 'Add the opening hours.'])
        ->assertNotified('Version rejected');

    actingAs($this->author);
    pageEditor($this->page)
        ->assertSee('Add the opening hours.')
        ->callAction('startDraft')
        ->assertNotified('Draft started');

    expect($this->page->openRevision())
        ->number->toBe(2)
        ->state->toBe(RevisionState::Draft)
        ->payload->toEqual($this->draft->fresh()->payload);
});

it('schedules in India Standard Time and can cancel the schedule', function () {
    $this->travelTo(Carbon::parse('2026-10-26 06:00:00', 'UTC'));
    app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($this->draft, $this->author), $this->approver);

    actingAs($this->publisher);
    pageEditor($this->page)
        ->callAction('schedule', ['publish_at' => '2026-10-27 10:00:00'])
        ->assertNotified('Publication scheduled')
        ->assertSee('Scheduled for 27 Oct 2026, 10:00 IST by Priya Publisher');

    expect($this->draft->fresh())
        ->scheduled_for->toEqual(Carbon::parse('2026-10-27 04:30:00', 'UTC'))
        ->scheduled_by->toBe($this->publisher->id)
        ->state->toBe(RevisionState::Approved)
        ->and($this->page->fresh()->status)->toBe('draft');

    pageEditor($this->page)->callAction('unschedule')->assertNotified('Schedule cancelled');

    expect($this->draft->fresh()->scheduled_for)->toBeNull();
});

it('shows a failed scheduled publication with its reason', function () {
    app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($this->draft, $this->author), $this->approver);
    app(ScheduleRevision::class)->handle($this->draft, $this->publisher, now()->addHour());
    $this->publisher->update(['is_active' => false]);
    $this->travel(2)->hours();
    $this->artisan('content:publish-scheduled')->assertSuccessful();

    actingAs($this->author);
    pageEditor($this->page)
        ->assertSee('Scheduled publication failed')
        ->assertSee('This account is deactivated.')
        ->assertActionEnabled('publish');
});

it('refuses staff who cannot view content', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create())
        ->get(PageResource::getUrl('edit', ['record' => $this->page]))
        ->assertForbidden();
});

it('applies a revoked permission on the next request of an open editor', function () {
    actingAs($this->author);
    $editor = pageEditor($this->page);

    Role::findByName(StaffRole::MarketingManager->value)->revokePermissionTo(Capability::ViewContent);

    $editor->call('save')->assertForbidden();
});
