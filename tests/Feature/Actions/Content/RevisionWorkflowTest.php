<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\RejectRevision;
use App\Actions\Content\RestoreRevision;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnscheduleRevision;
use App\Actions\Content\UpdateDraft;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Events\Content\ContentPublished;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->page = Page::factory()->create();
    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * @return array{title: string, slug: string, template: string, blocks: list<array<string, mixed>>, seo: array{title: null, description: null, indexable: bool}}
 */
function pagePayload(string $title, string $slug = 'about'): array
{
    return ['title' => $title, 'slug' => $slug, 'template' => 'standard', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Coworking and managed offices in Noida.</p>']],
    ], 'seo' => ['title' => null, 'description' => null, 'indexable' => true]];
}

function submittedPageRevision(Page $page, User $author, string $title = 'About SmartGro', string $slug = 'about'): Revision
{
    $revision = app(CreateDraft::class)->handle($page, $author, pagePayload($title, $slug));

    return app(SubmitRevision::class)->handle($revision, $author);
}

function approvedPageRevision(Page $page, User $author, User $approver, string $title = 'About SmartGro', string $slug = 'about'): Revision
{
    return app(ApproveRevision::class)->handle(submittedPageRevision($page, $author, $title, $slug), $approver);
}

it('publishes a version written by one person, approved by a second and published by a third', function () {
    Event::fake([ContentPublished::class]);
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);

    app(PublishRevision::class)->handle($revision, $this->publisher);

    expect($revision->fresh())
        ->state->toBe(RevisionState::Published)
        ->published_by->toBe($this->publisher->id)
        ->and($this->page->fresh())
        ->title->toBe('About SmartGro')
        ->slug->toBe('about')
        ->status->toBe('published')
        ->published_revision_id->toBe($revision->id);
    Event::assertDispatched(ContentPublished::class, fn (ContentPublished $event): bool => $event->revision->is($revision));
});

it('lets the author publish a version someone else approved', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);

    app(PublishRevision::class)->handle($revision, $this->author);

    expect($this->page->fresh()->published_revision_id)->toBe($revision->id);
});

it('refuses approval by the author', function () {
    $revision = submittedPageRevision($this->page, $this->author);

    expect(fn () => app(ApproveRevision::class)->handle($revision, $this->author))
        ->toThrow(AuthorizationException::class, 'You worked on this version, so someone else must review it.')
        ->and($revision->fresh()->state)->toBe(RevisionState::Submitted);
});

it('refuses approval by anyone who changed the draft', function () {
    $revision = app(CreateDraft::class)->handle($this->page, $this->author, pagePayload('About SmartGro'));
    app(UpdateDraft::class)->handle($revision, $this->approver, pagePayload('About SmartGro Services'));
    app(SubmitRevision::class)->handle($revision, $this->author);

    expect(fn () => app(ApproveRevision::class)->handle($revision, $this->approver))
        ->toThrow(AuthorizationException::class, 'You worked on this version, so someone else must review it.');

    app(ApproveRevision::class)->handle($revision, $this->publisher);

    expect($revision->fresh()->reviewer_id)->toBe($this->publisher->id);
});

it('refuses publication by the approver', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($revision, $this->approver))
        ->toThrow(AuthorizationException::class, 'You approved this version, so someone else must publish it.')
        ->and($revision->fresh()->state)->toBe(RevisionState::Approved)
        ->and($this->page->fresh()->published_revision_id)->toBeNull();
});

it('refuses scheduling by the approver', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);

    expect(fn () => app(ScheduleRevision::class)->handle($revision, $this->approver, now()->addDay()))
        ->toThrow(AuthorizationException::class, 'You approved this version, so someone else must publish it.')
        ->and($revision->fresh()->scheduled_for)->toBeNull();
});

it('publishes only an approved version', function () {
    $revision = submittedPageRevision($this->page, $this->author);

    expect(fn () => app(PublishRevision::class)->handle($revision, $this->publisher))
        ->toThrow(AuthorizationException::class, 'Only an approved version can be published.')
        ->and($this->page->fresh()->published_revision_id)->toBeNull();
});

it('never publishes a rejected version', function () {
    $revision = app(RejectRevision::class)->handle(submittedPageRevision($this->page, $this->author), $this->approver, 'The opening hours are wrong.');

    expect($revision->fresh())
        ->state->toBe(RevisionState::Rejected)
        ->review_note->toBe('The opening hours are wrong.')
        ->and(fn () => app(PublishRevision::class)->handle($revision, $this->publisher))
        ->toThrow(AuthorizationException::class, 'Only an approved version can be published.');
});

it('requires a note to reject a version', function () {
    $revision = submittedPageRevision($this->page, $this->author);

    expect(fn () => app(RejectRevision::class)->handle($revision, $this->approver, ' '))
        ->toThrow(ValidationException::class, 'Say why the version is rejected.')
        ->and($revision->fresh()->state)->toBe(RevisionState::Submitted);
});

it('refuses changes to a version once it is submitted', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);

    expect(fn () => app(UpdateDraft::class)->handle($revision, $this->author, pagePayload('Changed after approval')))
        ->toThrow(AuthorizationException::class, 'Only a draft can be changed. Start a new draft to change this version.')
        ->and($revision->fresh()->payload['title'])->toBe('About SmartGro');
});

it('keeps one version in progress at a time', function () {
    app(CreateDraft::class)->handle($this->page, $this->author, pagePayload('About SmartGro'));

    expect(fn () => app(CreateDraft::class)->handle($this->page, $this->approver, pagePayload('Another draft')))
        ->toThrow(ValidationException::class, 'This content already has a version in progress.')
        ->and($this->page->revisions()->count())->toBe(1);
});

it('starts a new draft from the live content once the previous version is rejected', function () {
    app(PublishRevision::class)->handle(approvedPageRevision($this->page, $this->author, $this->approver), $this->publisher);
    app(RejectRevision::class)->handle(submittedPageRevision($this->page, $this->author, 'Rejected title'), $this->approver, 'Not this one.');

    $draft = app(CreateDraft::class)->handle($this->page->fresh(), $this->approver);

    expect($draft)
        ->number->toBe(3)
        ->payload->toEqual(pagePayload('About SmartGro'));
});

it('applies the same rules to the Super Admin', function () {
    $otherSuperAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();
    $revision = submittedPageRevision($this->page, $this->publisher);

    expect(fn () => app(ApproveRevision::class)->handle($revision, $this->publisher))
        ->toThrow(AuthorizationException::class, 'You worked on this version, so someone else must review it.');

    app(ApproveRevision::class)->handle($revision, $otherSuperAdmin);

    expect(fn () => app(PublishRevision::class)->handle($revision, $otherSuperAdmin))
        ->toThrow(AuthorizationException::class, 'You approved this version, so someone else must publish it.');
});

it('refuses staff roles without content permissions', function (StaffRole $role) {
    $user = User::factory()->withRole($role)->create();
    $submitted = submittedPageRevision($this->page, $this->author);
    $approved = approvedPageRevision(Page::factory()->create(), $this->author, $this->approver, slug: 'contact');

    expect(fn () => app(CreateDraft::class)->handle(Page::factory()->create(), $user))
        ->toThrow(AuthorizationException::class, 'You cannot edit this content.')
        ->and(fn () => app(ApproveRevision::class)->handle($submitted, $user))
        ->toThrow(AuthorizationException::class, 'You cannot approve this content.')
        ->and(fn () => app(PublishRevision::class)->handle($approved, $user))
        ->toThrow(AuthorizationException::class, 'You cannot publish this content.');
})->with([StaffRole::OperationsManager, StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::Finance]);

it('refuses a deactivated account', function () {
    $revision = submittedPageRevision($this->page, $this->author);
    $this->approver->update(['is_active' => false]);

    expect(fn () => app(ApproveRevision::class)->handle($revision, $this->approver))
        ->toThrow(AuthorizationException::class, 'This account is deactivated.');
});

it('refuses to publish a version whose address another page took after it was approved', function () {
    $first = approvedPageRevision(Page::factory()->create(), $this->author, $this->approver, 'Contact', 'contact');
    $revision = approvedPageRevision($this->page, $this->author, $this->approver, 'Contact us', 'contact');
    app(PublishRevision::class)->handle($first, $this->publisher);

    expect(fn () => app(PublishRevision::class)->handle($revision, $this->publisher))
        ->toThrow(ValidationException::class, 'Another page already uses this address.')
        ->and($revision->fresh())
        ->state->toBe(RevisionState::Approved)
        ->published_by->toBeNull()
        ->and($this->page->fresh()->published_revision_id)->toBeNull();
});

it('restores an earlier version as a new draft that goes live only after approval and publication', function () {
    $first = app(PublishRevision::class)->handle(approvedPageRevision($this->page, $this->author, $this->approver, 'First title'), $this->publisher);
    $second = app(PublishRevision::class)->handle(approvedPageRevision($this->page, $this->author, $this->approver, 'Second title'), $this->publisher);
    $firstPayload = $first->fresh()->payload;

    $restored = app(RestoreRevision::class)->handle($first->fresh(), $this->publisher);

    expect($restored)
        ->number->toBe(3)
        ->state->toBe(RevisionState::Draft)
        ->payload->toEqual($firstPayload)
        ->author_id->toBe($this->publisher->id)
        ->and($first->fresh())
        ->state->toBe(RevisionState::Superseded)
        ->payload->toEqual($firstPayload)
        ->and($second->fresh()->state)->toBe(RevisionState::Published)
        ->and($this->page->fresh()->title)->toBe('Second title');

    app(SubmitRevision::class)->handle($restored, $this->publisher);

    expect(fn () => app(ApproveRevision::class)->handle($restored, $this->author))
        ->toThrow(AuthorizationException::class, 'You worked on this version, so someone else must review it.');

    app(ApproveRevision::class)->handle($restored, $this->approver);
    app(PublishRevision::class)->handle($restored, $this->author);

    expect($this->page->fresh())
        ->title->toBe('First title')
        ->published_revision_id->toBe($restored->id);
});

it('refuses a restore by the approver of the version being restored', function () {
    $first = app(PublishRevision::class)->handle(approvedPageRevision($this->page, $this->author, $this->approver, 'First title'), $this->publisher);
    app(PublishRevision::class)->handle(approvedPageRevision($this->page, $this->author, $this->publisher, 'Second title'), $this->approver);

    expect(fn () => app(RestoreRevision::class)->handle($first->fresh(), $this->approver))
        ->toThrow(AuthorizationException::class, 'You approved this version, so someone else must restore it.')
        ->and($this->page->revisions()->count())->toBe(2)
        ->and($this->page->fresh()->title)->toBe('Second title');
});

it('records the author, approver and publisher of each step in the audit log', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);
    app(PublishRevision::class)->handle($revision, $this->publisher);

    $entries = Activity::query()->inLog('content')->whereMorphedTo('subject', $revision)->oldest('id')->get();

    expect($entries->pluck('event')->all())->toBe(['revision_created', 'revision_submitted', 'revision_approved', 'revision_published'])
        ->and($entries->pluck('causer_id')->all())->toBe([$this->author->id, $this->author->id, $this->approver->id, $this->publisher->id])
        ->and($entries->last()->properties->only(['revision', 'author_id', 'reviewer_id', 'published_by'])->all())->toBe([
            'revision' => 1,
            'author_id' => $this->author->id,
            'reviewer_id' => $this->approver->id,
            'published_by' => $this->publisher->id,
        ]);
});

it('cancels a schedule and leaves the version approved and unpublished', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);
    app(ScheduleRevision::class)->handle($revision, $this->publisher, now()->addDay());

    expect(fn () => app(UnscheduleRevision::class)->handle($revision, User::factory()->withRole(StaffRole::Finance)->create()))
        ->toThrow(AuthorizationException::class, 'You cannot publish this content.');

    app(UnscheduleRevision::class)->handle($revision, $this->author);

    expect($revision->fresh())
        ->state->toBe(RevisionState::Approved)
        ->scheduled_for->toBeNull()
        ->scheduled_by->toBeNull()
        ->and($this->page->fresh()->published_revision_id)->toBeNull();
});

it('keeps content, passwords, tokens and preview signatures out of the audit log', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);
    RevisionPreviewController::url($revision);
    app(ScheduleRevision::class)->handle($revision, $this->publisher, now()->addDay());
    app(UnscheduleRevision::class)->handle($revision, $this->publisher);
    app(PublishRevision::class)->handle($revision, $this->publisher);
    app(PublishRevision::class)->handle(approvedPageRevision($this->page, $this->author, $this->approver, 'Second'), $this->publisher);
    app(RestoreRevision::class)->handle($revision->fresh(), $this->author);
    $first = $revision->fresh();

    $logged = Activity::query()->inLog('content')->get()->map(fn (Activity $entry): string => json_encode($entry->properties))->join("\n");

    expect(Activity::query()->inLog('content')->count())->toBeGreaterThan(8)
        ->and($logged)
        ->not->toContain('password')
        ->not->toContain('token')
        ->not->toContain('signature')
        ->not->toContain('payload')
        ->not->toContain('Coworking and managed offices in Noida.')
        ->and($first->state)->toBe(RevisionState::Superseded);
});

it('restores only a previously published version', function () {
    $live = app(PublishRevision::class)->handle(approvedPageRevision($this->page, $this->author, $this->approver), $this->publisher);
    $draft = app(CreateDraft::class)->handle($this->page, $this->author, pagePayload('Draft'));

    expect(fn () => app(RestoreRevision::class)->handle($live, $this->publisher))
        ->toThrow(AuthorizationException::class, 'Only a previously published version can be restored.')
        ->and(fn () => app(RestoreRevision::class)->handle($draft, $this->publisher))
        ->toThrow(AuthorizationException::class, 'Only a previously published version can be restored.')
        ->and($this->page->revisions()->count())->toBe(2);
});

it('refuses to submit an incomplete draft', function () {
    $draft = app(CreateDraft::class)->handle($this->page, $this->author, ['title' => null, 'slug' => null, 'blocks' => []]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))
        ->toThrow(ValidationException::class)
        ->and($draft->fresh()->state)->toBe(RevisionState::Draft);
});

it('checks an approved version\'s content again when publishing it', function () {
    $revision = approvedPageRevision($this->page, $this->author, $this->approver);
    $revision->forceFill(['payload' => [...pagePayload('About SmartGro'), 'blocks' => [
        ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Go', 'text' => null, 'primary_link' => ['label' => 'Click', 'type' => 'url', 'page_id' => null, 'url' => 'javascript:alert(1)'], 'secondary_link' => null]],
    ]]])->save();

    expect(fn () => app(PublishRevision::class)->handle($revision, $this->publisher))
        ->toThrow(ValidationException::class)
        ->and($revision->fresh()->state)->toBe(RevisionState::Approved)
        ->and($this->page->fresh()->published_revision_id)->toBeNull();
});
