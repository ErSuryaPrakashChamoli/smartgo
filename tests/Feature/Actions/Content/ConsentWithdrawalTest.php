<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\PublishScheduledRevisions;
use App\Actions\Content\RejectRevision;
use App\Actions\Content\RestoreRevision;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Actions\Content\UpdateDraft;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * A complete payload with consent on file for a testimonial or a customer story.
 *
 * @param  class-string<Model>  $type
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function consentPayload(string $type, array $overrides = []): array
{
    $payload = $type === Testimonial::class
        ? ['quote' => 'Moving in took a day.', 'person_name' => 'Asha Rao', 'person_title' => null, 'company_name' => null, 'statement_verified' => true]
        : ['title' => 'Rao Studio grows', 'slug' => 'rao-studio', 'company_name' => 'Rao Studio', 'summary' => 'A growing team.',
            'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Story.</p>']]]];

    return [...$payload, 'consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14', ...$overrides];
}

/**
 * @param  array<string, mixed>  $payload
 */
function submittedVersion(Model $content, array $payload, User $author): Revision
{
    return app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($content->fresh(), $author, $payload), $author);
}

/**
 * Publishes the payload through the full workflow and returns the published version.
 *
 * @param  array<string, mixed>  $payload
 */
function publishVersion(Model $content, array $payload, User $author, User $approver, User $publisher): Revision
{
    return app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(submittedVersion($content, $payload, $author), $approver), $publisher);
}

/**
 * @param  class-string<Model>  $type
 */
function liveItem(string $type, User $author, User $approver, User $publisher): Model
{
    $content = $type::factory()->create();
    publishVersion($content, consentPayload($type), $author, $approver, $publisher);

    return $content->fresh();
}

/**
 * Starts a draft after the withdrawal and records new consent in it, as an author does in the editor.
 *
 * @param  array<string, mixed>  $consent
 */
function draftWithNewConsent(Model $content, User $author, array $consent): Revision
{
    $draft = app(CreateDraft::class)->handle($content->fresh(), $author);

    return app(UpdateDraft::class)->handle($draft, $author, [...$draft->payload, ...$consent]);
}

dataset('consent types', [
    'testimonial' => [Testimonial::class],
    'customer story' => [CaseStudy::class],
]);

it('records withdrawn consent on the item when it is taken down, keeping the consent evidence', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);

    $this->freezeTime(function () use ($item): void {
        app(UnpublishContent::class)->handle($item, $this->approver, 'Consent withdrawn by email on 26 October 2026', consentWithdrawn: true);

        expect($item->fresh())
            ->consent_withdrawn_at->toDateTimeString()->toBe(now()->toDateTimeString())
            ->consent_withdrawn_by->toBe($this->approver->id)
            ->consent_on_file->toBeTrue()
            ->consent_reference->toBe('Signed form 2026-14')
            ->and(Activity::query()->where('event', 'consent_withdrawn')->sole())
            ->causer_id->toBe($this->approver->id)
            ->properties->toArray()->toMatchArray(['reason' => 'Consent withdrawn by email on 26 October 2026', 'withdrawn_consent_reference' => 'Signed form 2026-14']);
    });
})->with('consent types');

it('keeps the consent record when the item is taken down for another reason', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);

    app(UnpublishContent::class)->handle($item, $this->publisher, 'Typo in the name', consentWithdrawn: false);

    $draft = app(CreateDraft::class)->handle($item->fresh(), $this->author);

    expect($item->fresh()->consent_withdrawn_at)->toBeNull()
        ->and(Activity::query()->where('event', 'consent_withdrawn')->exists())->toBeFalse()
        ->and($draft->payload)->toMatchArray(['consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14'])
        ->and(app(SubmitRevision::class)->handle($draft, $this->author)->state)->toBe(RevisionState::Submitted);
})->with('consent types');

it('never lets a new draft inherit consent once it is withdrawn, whatever it copies', function (string $type, string $source) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    $first = $item->publishedRevision;
    publishVersion($item, consentPayload($type, ['consent_reference' => 'Signed form 2026-20']), $this->author, $this->approver, $this->publisher);
    $returned = app(ApproveRevision::class)->handle(submittedVersion($item, consentPayload($type, ['consent_reference' => 'Signed form 2026-21']), $this->author), $this->approver);
    app(UnpublishContent::class)->handle($item->fresh(), $this->publisher, 'Consent withdrawn', consentWithdrawn: true);

    $draft = match ($source) {
        'the live content' => app(CreateDraft::class)->handle($item->fresh(), $this->author),
        'the returned version' => app(CreateDraft::class)->handle($item->fresh(), $this->author, basedOn: $returned->fresh()),
        'an earlier published version' => app(RestoreRevision::class)->handle($first->fresh(), $this->author),
    };

    expect($draft->payload)->toMatchArray(['consent_on_file' => false, 'consent_reference' => null, 'consent_reconfirmed' => false])
        ->and($item->fresh()->consent_withdrawn_at)->not->toBeNull()
        ->and($first->fresh()->payload)->toMatchArray(['consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14'])
        ->and(Activity::query()->where('event', 'revision_created')->latest('id')->first()->properties['consent_cleared'])->toBeTrue();
})->with('consent types')->with(['the live content', 'the returned version', 'an earlier published version']);

it('refuses to submit a version without fresh consent evidence and confirmation', function (string $type, array $consent, string $field) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    publishVersion($item, consentPayload($type, ['consent_reference' => 'Signed form 2026-20']), $this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($item->fresh(), $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    $draft = draftWithNewConsent($item, $this->author, $consent);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field))
        ->and($draft->fresh()->state)->toBe(RevisionState::Draft);
})->with('consent types')->with([
    'consent not ticked' => [['consent_on_file' => false, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true], 'consent_on_file'],
    'no new reference' => [['consent_on_file' => true, 'consent_reference' => null, 'consent_reconfirmed' => true], 'consent_reference'],
    'the withdrawn reference' => [['consent_on_file' => true, 'consent_reference' => 'Signed form 2026-20', 'consent_reconfirmed' => true], 'consent_reference'],
    'an older reference' => [['consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14', 'consent_reconfirmed' => true], 'consent_reference'],
    'an old reference in other letters and spaces' => [['consent_on_file' => true, 'consent_reference' => '  SIGNED FORM 2026-20 ', 'consent_reconfirmed' => true], 'consent_reference'],
    'no confirmation' => [['consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => false], 'consent_reconfirmed'],
]);

it('accepts new consent evidence with confirmation, and keeps the withdrawal until publication', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    $draft = draftWithNewConsent($item, $this->author, ['consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true]);

    $approved = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->author), $this->approver);

    expect($approved->state)->toBe(RevisionState::Approved)
        ->and($item->fresh()->consent_withdrawn_at)->not->toBeNull();
})->with('consent types');

it('re-establishes consent only when a version with new consent is published through the workflow', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    $approved = app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle(
        draftWithNewConsent($item, $this->author, ['consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true]),
        $this->author,
    ), $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($approved, $this->approver))->toThrow(AuthorizationException::class);

    app(PublishRevision::class)->handle($approved, $this->author);

    expect($item->fresh())
        ->status->toBe('published')
        ->consent_withdrawn_at->toBeNull()
        ->consent_withdrawn_by->toBeNull()
        ->consent_reference->toBe('Email 2 Nov 2026')
        ->and(Activity::query()->where('event', 'consent_reestablished')->sole())
        ->causer_id->toBe($this->author->id)
        ->properties->toArray()->toMatchArray(['consent_reference' => 'Email 2 Nov 2026', 'reviewer_id' => $this->approver->id, 'withdrawn_by' => $this->publisher->id]);
})->with('consent types');

it('checks consent again at publication, even for a version approved before the withdrawal was recorded', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    $approved = app(ApproveRevision::class)->handle(submittedVersion($item, consentPayload($type), $this->author), $this->approver);
    $item->forceFill(['consent_withdrawn_at' => now(), 'consent_withdrawn_by' => $this->publisher->id])->save();

    expect(fn () => app(PublishRevision::class)->handle($approved, $this->author))->toThrow(ValidationException::class)
        ->and($item->fresh()->publishedRevision->is($approved))->toBeFalse()
        ->and($item->fresh()->consent_withdrawn_at)->not->toBeNull();
})->with('consent types');

it('sends submitted and approved versions back so that nobody can approve or publish them', function (string $type, string $pending) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    $waiting = submittedVersion($item, consentPayload($type), $this->author);

    if ($pending === 'approved') {
        $waiting = app(ApproveRevision::class)->handle($waiting, $this->approver);
    }

    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);

    expect($waiting->fresh()->state)->toBe(RevisionState::Rejected)
        ->and(fn () => app(ApproveRevision::class)->handle($waiting->fresh(), $this->approver))->toThrow(AuthorizationException::class, 'Only a submitted version can be approved or rejected.')
        ->and(fn () => app(PublishRevision::class)->handle($waiting->fresh(), $this->author))->toThrow(AuthorizationException::class, 'Only an approved version can be published.')
        ->and(Activity::query()->where('event', 'revision_returned')->sole()->properties['previous_state'])->toBe($pending);
})->with('consent types')->with(['submitted', 'approved']);

it('cancels scheduled publication, so the scheduler publishes nothing afterwards', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    $scheduled = app(ApproveRevision::class)->handle(submittedVersion($item, consentPayload($type), $this->author), $this->approver);
    app(ScheduleRevision::class)->handle($scheduled, $this->publisher, now()->addHour());

    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    $this->travel(2)->hours();

    expect($scheduled->fresh()->scheduled_for)->toBeNull()
        ->and(app(PublishScheduledRevisions::class)->handle())->toBe(0)
        ->and($item->fresh()->status)->toBe('unpublished');
})->with('consent types');

it('records nothing for people who may not take content down or whose account is deactivated', function (string $type, string $who) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);

    if ($who === 'deactivated') {
        $this->publisher->forceFill(['is_active' => false])->save();
    }

    $user = $who === 'finance' ? User::factory()->withRole(StaffRole::Finance)->create() : $this->publisher->fresh();

    expect(fn () => app(UnpublishContent::class)->handle($item, $user, 'Consent withdrawn', consentWithdrawn: true))->toThrow(AuthorizationException::class)
        ->and($item->fresh())->status->toBe('published')->consent_withdrawn_at->toBeNull()
        ->and(Activity::query()->whereIn('event', ['consent_withdrawn', 'content_unpublished'])->exists())->toBeFalse();
})->with('consent types')->with(['finance', 'deactivated']);

it('sends a draft in progress back unchanged when consent is withdrawn, and audits each step', function () {
    $item = liveItem(Testimonial::class, $this->author, $this->approver, $this->publisher);
    $openDraft = app(CreateDraft::class)->handle($item, $this->author, consentPayload(Testimonial::class, ['quote' => 'A draft in progress.', 'consent_reference' => 'Draft ref 7']));

    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);

    expect($openDraft->fresh())
        ->state->toBe(RevisionState::Rejected)
        ->payload->toMatchArray(['quote' => 'A draft in progress.', 'consent_on_file' => true, 'consent_reference' => 'Draft ref 7'])
        ->and(Activity::query()->inLog('content')->latest('id')->take(3)->pluck('event')->reverse()->values()->all())
        ->toBe(['revision_returned', 'consent_withdrawn', 'content_unpublished'])
        ->and(Activity::query()->where('event', 'revision_returned')->sole()->properties['previous_state'])->toBe('draft');

    $draft = app(CreateDraft::class)->handle($item->fresh(), $this->author, basedOn: $openDraft->fresh());
    $draft = app(UpdateDraft::class)->handle($draft, $this->author, [...$draft->payload, 'consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true]);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->author), $this->approver), $this->publisher);

    expect(Activity::query()->inLog('content')->latest('id')->take(2)->pluck('event')->reverse()->values()->all())
        ->toBe(['consent_reestablished', 'revision_published'])
        ->and(Activity::query()->where('event', 'consent_withdrawn')->sole()->subject->is($item))->toBeTrue()
        ->and($openDraft->fresh()->payload['consent_reference'])->toBe('Draft ref 7');
});

it('refuses the reference of a draft that was in progress when consent was withdrawn', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    app(CreateDraft::class)->handle($item, $this->author, consentPayload($type, ['consent_reference' => 'Draft ref 7']));
    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);

    $draft = draftWithNewConsent($item, $this->author, ['consent_on_file' => true, 'consent_reference' => 'draft REF 7', 'consent_reconfirmed' => true]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class, 'this one was used before');
})->with('consent types');

it('keeps new consent evidence usable when the version that carried it is rejected for another reason', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    $this->travel(1)->minutes();
    $first = app(SubmitRevision::class)->handle(draftWithNewConsent($item, $this->author, ['consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true]), $this->author);
    app(RejectRevision::class)->handle($first, $this->approver, 'Fix the spelling of the name.');

    $revised = app(CreateDraft::class)->handle($item->fresh(), $this->author, basedOn: $first->fresh());
    $revised = app(UpdateDraft::class)->handle($revised, $this->author, [...$revised->payload, 'consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true]);

    expect($revised->payload['consent_reference'])->toBe('Email 2 Nov 2026')
        ->and(app(SubmitRevision::class)->handle($revised, $this->author)->state)->toBe(RevisionState::Submitted);
})->with('consent types');

it('leaves a draft in progress as it is when the item is taken down for another reason', function () {
    $item = liveItem(Testimonial::class, $this->author, $this->approver, $this->publisher);
    $openDraft = app(CreateDraft::class)->handle($item, $this->author, consentPayload(Testimonial::class, ['quote' => 'A draft in progress.']));

    app(UnpublishContent::class)->handle($item, $this->publisher, 'Typo in the name', consentWithdrawn: false);

    expect($openDraft->fresh()->state)->toBe(RevisionState::Draft)
        ->and(Activity::query()->where('event', 'revision_returned')->exists())->toBeFalse();
});

it('treats existing content without a withdrawal record exactly as before', function (string $type) {
    $item = liveItem($type, $this->author, $this->approver, $this->publisher);

    $draft = app(CreateDraft::class)->handle($item, $this->author);
    $published = app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->author), $this->approver), $this->publisher);

    expect($draft->payload)->toMatchArray(['consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14', 'consent_reconfirmed' => false])
        ->and($published->state)->toBe(RevisionState::Published)
        ->and($item->fresh()->consent_withdrawn_at)->toBeNull()
        ->and(Activity::query()->where('event', 'consent_reestablished')->exists())->toBeFalse();
})->with('consent types');

it('shows a re-established testimonial on the website again', function () {
    $item = liveItem(Testimonial::class, $this->author, $this->approver, $this->publisher);
    Page::factory()->published()->create(['slug' => 'about', 'blocks' => [['type' => 'testimonial', 'data' => ['testimonial_id' => $item->id]]]]);
    app(UnpublishContent::class)->handle($item, $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    get('/about')->assertDontSee('Moving in took a day.');

    $draft = draftWithNewConsent($item, $this->author, ['consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true]);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle(app(SubmitRevision::class)->handle($draft, $this->author), $this->approver), $this->publisher);

    get('/about')->assertSee('Moving in took a day.');
});

it('refuses the live record\'s reference even when no version holds it, as for imported records', function (string $type) {
    $item = $type::factory()->published()->create(['consent_reference' => 'Imported consent 42']);
    $item->forceFill(['consent_withdrawn_at' => now(), 'consent_withdrawn_by' => $this->publisher->id])->save();

    $draft = draftWithNewConsent($item, $this->author, ['consent_on_file' => true, 'consent_reference' => 'Imported consent 42', 'consent_reconfirmed' => true]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class, 'this one was used before');
})->with('consent types');
