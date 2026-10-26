<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\Page;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\Concurrency\PublishingHarness;

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    PublishingHarness::reset();
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

afterEach(function () {
    PublishingHarness::reset();
});

function concurrentlyApproved(Page $page, User $author, User $approver, string $slug): Revision
{
    $draft = app(CreateDraft::class)->handle($page, $author, [
        'title' => 'Offices', 'slug' => $slug, 'template' => 'standard',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Managed offices.</p>']]],
    ]);

    return app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $author), $approver);
}

/**
 * @param  array<string, array{outcome: string, message: string|null}>  $results
 * @return array<string, int>
 */
function publishingTally(array $results): array
{
    return collect($results)->countBy('outcome')->sortKeys()->all();
}

it('publishes an approved version exactly once when people and the scheduler publish it at the same instant', function () {
    $page = Page::factory()->create();
    $revision = concurrentlyApproved($page, $this->author, $this->approver, 'offices');
    $revision->forceFill(['scheduled_for' => now()->subMinute(), 'scheduled_by' => $this->publisher->id])->save();
    $secondSuperAdmin = User::factory()->withRole(StaffRole::SuperAdmin)->create();

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $this->publisher->id],
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $this->author->id],
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $secondSuperAdmin->id],
        ['mode' => 'scheduled'],
    ]);

    expect(collect($results)->where('outcome', 'published')->count())->toBe(1)
        ->and(collect($results)->where('outcome', 'refused')->pluck('message')->unique()->all())->toBe(['Only an approved version can be published.'])
        ->and($revision->fresh()->state)->toBe(RevisionState::Published)
        ->and($page->fresh()->published_revision_id)->toBe($revision->id)
        ->and(Activity::query()->where('event', 'revision_published')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'revision_schedule_failed')->count())->toBe(0);
})->group('mysql', 'concurrency');

it('skips, without recording a failure, a scheduled version someone published a moment earlier', function () {
    $page = Page::factory()->create();
    $revision = concurrentlyApproved($page, $this->author, $this->approver, 'offices');
    $revision->forceFill(['scheduled_for' => now()->subMinute(), 'scheduled_by' => $this->publisher->id])->save();

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $revision->id, 'user_id' => $this->author->id],
        ['mode' => 'scheduled', 'start_delay_ms' => 50],
    ]);

    expect(publishingTally($results))->toBe(['published' => 1, 'skipped' => 1])
        ->and($results['worker-0']['outcome'])->toBe('published')
        ->and($revision->fresh()->published_by)->toBe($this->author->id)
        ->and(Activity::query()->where('event', 'revision_schedule_failed')->count())->toBe(0);
})->group('mysql', 'concurrency');

it('lets only one of two pages take the same address at the same instant', function () {
    $first = concurrentlyApproved(Page::factory()->create(), $this->author, $this->approver, 'offices');
    $second = concurrentlyApproved(Page::factory()->create(), $this->author, $this->approver, 'offices');

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $first->id, 'user_id' => $this->publisher->id],
        ['mode' => 'manual', 'revision_id' => $second->id, 'user_id' => $this->publisher->id],
    ]);

    expect(publishingTally($results))->toBe(['published' => 1, 'refused' => 1])
        ->and(collect($results)->firstWhere('outcome', 'refused')['message'])->toBe('Another page already uses this address.')
        ->and(Page::query()->where('slug', 'offices')->count())->toBe(1)
        ->and(Revision::query()->where('state', RevisionState::Published)->count())->toBe(1);
})->group('mysql', 'concurrency');

/**
 * A testimonial published through the workflow, with a second approved version waiting.
 *
 * @return array{0: Testimonial, 1: Revision}
 */
function liveTestimonialWithApprovedChange(User $author, User $approver, User $publisher): array
{
    $testimonial = Testimonial::factory()->create();
    $payload = ['quote' => 'Moving in took a day.', 'person_name' => 'Asha Rao', 'person_title' => null, 'company_name' => null,
        'consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14', 'statement_verified' => true];
    $first = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($testimonial, $author, $payload), $author), $approver);
    app(PublishRevision::class)->handle($first, $publisher);
    $second = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($testimonial->fresh(), $author, [...$payload, 'quote' => 'Changed.']), $author), $approver);

    return [$testimonial, $second];
}

it('never leaves a testimonial live when it is taken down while a new version is being published (OD-98)', function () {
    [$testimonial, $approvedChange] = liveTestimonialWithApprovedChange($this->author, $this->approver, $this->publisher);

    $results = PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $approvedChange->id, 'user_id' => $this->publisher->id],
        ['mode' => 'takedown', 'testimonial_id' => $testimonial->id, 'user_id' => $this->author->id],
    ]);

    expect($results['worker-1']['outcome'])->toBe('taken down')
        ->and($results['worker-0']['outcome'] === 'published' || $results['worker-0']['message'] === 'Only an approved version can be published.')->toBeTrue()
        ->and($testimonial->fresh()->status)->toBe('unpublished')
        ->and($testimonial->revisions()->where('state', RevisionState::Approved)->count())->toBe(0)
        ->and($testimonial->revisions()->where('state', RevisionState::Published)->count())->toBe(0)
        ->and(Activity::query()->where('event', 'content_unpublished')->count())->toBe(1);
})->group('mysql', 'concurrency');

it('takes a testimonial down once when two people do it at the same instant (OD-98)', function () {
    [$testimonial] = liveTestimonialWithApprovedChange($this->author, $this->approver, $this->publisher);

    $results = PublishingHarness::runWorkers([
        ['mode' => 'takedown', 'testimonial_id' => $testimonial->id, 'user_id' => $this->author->id],
        ['mode' => 'takedown', 'testimonial_id' => $testimonial->id, 'user_id' => $this->publisher->id],
    ]);

    expect(publishingTally($results))->toBe(['refused' => 1, 'taken down' => 1])
        ->and(collect($results)->firstWhere('outcome', 'refused')['message'])->toBe('Only live content can be taken down.')
        ->and(Activity::query()->where('event', 'content_unpublished')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'revision_returned')->count())->toBe(1);
})->group('mysql', 'concurrency');

/**
 * A live testimonial with a second version submitted for approval.
 *
 * @return array{0: Testimonial, 1: Revision}
 */
function liveTestimonialWithSubmittedChange(User $author, User $approver, User $publisher): array
{
    [$testimonial, $approved] = liveTestimonialWithApprovedChange($author, $approver, $publisher);
    app(PublishRevision::class)->handle($approved, $publisher);
    $payload = [...$approved->payload, 'quote' => 'Submitted change.'];
    $submitted = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($testimonial->fresh(), $author, $payload), $author);

    return [$testimonial, $submitted];
}

it('leaves nothing approvable or publishable when consent is withdrawn while a version is approved (OD-101)', function () {
    [$testimonial, $submitted] = liveTestimonialWithSubmittedChange($this->author, $this->approver, $this->publisher);

    $results = PublishingHarness::runWorkers([
        ['mode' => 'approve', 'revision_id' => $submitted->id, 'user_id' => $this->approver->id],
        ['mode' => 'takedown', 'testimonial_id' => $testimonial->id, 'user_id' => $this->publisher->id, 'consent_withdrawn' => true],
    ]);

    expect($results['worker-1']['outcome'])->toBe('taken down')
        ->and($results['worker-0']['outcome'] === 'approved' || $results['worker-0']['message'] === 'Only a submitted version can be approved or rejected.')->toBeTrue()
        ->and($submitted->fresh()->state)->toBe(RevisionState::Rejected)
        ->and($testimonial->revisions()->whereIn('state', [RevisionState::Submitted, RevisionState::Approved])->count())->toBe(0)
        ->and($testimonial->fresh())->status->toBe('unpublished')->consent_withdrawn_at->not->toBeNull();
})->group('mysql', 'concurrency');

it('records the withdrawal and keeps the item off the website when it races a publication (OD-101)', function () {
    [$testimonial, $approvedChange] = liveTestimonialWithApprovedChange($this->author, $this->approver, $this->publisher);

    PublishingHarness::runWorkers([
        ['mode' => 'manual', 'revision_id' => $approvedChange->id, 'user_id' => $this->publisher->id],
        ['mode' => 'takedown', 'testimonial_id' => $testimonial->id, 'user_id' => $this->author->id, 'consent_withdrawn' => true],
    ]);

    expect($testimonial->fresh())->status->toBe('unpublished')->consent_withdrawn_at->not->toBeNull()
        ->and($testimonial->revisions()->whereIn('state', [RevisionState::Approved, RevisionState::Published])->count())->toBe(0);
})->group('mysql', 'concurrency');

it('never lets a version restored during a withdrawal keep the withdrawn consent (OD-101)', function () {
    [$testimonial, $approvedChange] = liveTestimonialWithApprovedChange($this->author, $this->approver, $this->publisher);
    app(PublishRevision::class)->handle($approvedChange, $this->publisher);
    $earlier = $testimonial->revisions()->where('state', RevisionState::Superseded)->sole();

    $results = PublishingHarness::runWorkers([
        ['mode' => 'restore', 'revision_id' => $earlier->id, 'user_id' => $this->author->id],
        ['mode' => 'takedown', 'testimonial_id' => $testimonial->id, 'user_id' => $this->publisher->id, 'consent_withdrawn' => true],
    ]);
    $open = $testimonial->revisions()->whereIn('state', RevisionState::open())->get();

    expect($results['worker-1']['outcome'])->toBe('taken down')
        ->and($results['worker-0']['outcome'])->toBe('restored')
        ->and($open->filter(fn (Revision $revision): bool => (bool) ($revision->payload['consent_on_file'] ?? false))->count())->toBe(0)
        ->and($earlier->fresh()->payload['consent_on_file'])->toBeTrue()
        ->and($testimonial->fresh()->consent_withdrawn_at)->not->toBeNull();

    foreach ($open as $draft) {
        expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class);
    }
})->group('mysql', 'concurrency');

it('never lets a draft with the withdrawn consent be submitted while consent is withdrawn (OD-101)', function () {
    [$testimonial, $approvedChange] = liveTestimonialWithApprovedChange($this->author, $this->approver, $this->publisher);
    app(PublishRevision::class)->handle($approvedChange, $this->publisher);
    $draft = app(CreateDraft::class)->handle($testimonial->fresh(), $this->author);

    $results = PublishingHarness::runWorkers([
        ['mode' => 'submit', 'revision_id' => $draft->id, 'user_id' => $this->author->id],
        ['mode' => 'takedown', 'testimonial_id' => $testimonial->id, 'user_id' => $this->publisher->id, 'consent_withdrawn' => true],
    ]);

    expect($results['worker-1']['outcome'])->toBe('taken down')
        ->and($draft->fresh()->state)->toBe(RevisionState::Rejected)
        ->and($testimonial->revisions()->whereIn('state', RevisionState::open())->count())->toBe(0)
        ->and($testimonial->fresh()->consent_withdrawn_at)->not->toBeNull();
})->group('mysql', 'concurrency');
