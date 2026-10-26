<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\PublishScheduledRevisions;
use App\Actions\Content\ScheduleRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Post;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * @return array<string, mixed>
 */
function consentedTestimonial(string $quote = 'Moving in took a day.'): array
{
    return ['quote' => $quote, 'person_name' => 'Asha Rao', 'person_title' => 'Founder', 'company_name' => 'Rao Studio',
        'consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14', 'statement_verified' => true];
}

/**
 * @param  array<string, mixed>  $payload
 */
function approvedVersion(Model $content, array $payload, User $author, User $approver): Revision
{
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($content, $author, $payload), $author);

    return app(ApproveRevision::class)->handle($revision, $approver);
}

/**
 * A testimonial published through the workflow and shown on the published page /about.
 */
function liveTestimonialOnAboutPage(User $author, User $approver, User $publisher): Testimonial
{
    $testimonial = Testimonial::factory()->create();
    app(PublishRevision::class)->handle(approvedVersion($testimonial, consentedTestimonial(), $author, $approver), $publisher);
    Page::factory()->published()->create(['slug' => 'about', 'blocks' => [['type' => 'testimonial', 'data' => ['testimonial_id' => $testimonial->id]]]]);

    return $testimonial->fresh();
}

function liveCustomerStory(User $author, User $approver, User $publisher): CaseStudy
{
    $caseStudy = CaseStudy::factory()->create();
    app(PublishRevision::class)->handle(approvedVersion($caseStudy, [
        'title' => 'Rao Studio grows', 'slug' => 'rao-studio', 'company_name' => 'Rao Studio', 'summary' => 'A growing team.',
        'consent_on_file' => true, 'consent_reference' => 'Email 12 Oct',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Story.</p>']]],
    ], $author, $approver), $publisher);

    return $caseStudy->fresh();
}

it('takes a live testimonial off every page at once, keeping its content and history', function () {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);
    get('/about')->assertSee('Moving in took a day.');

    app(UnpublishContent::class)->handle($testimonial, $this->publisher, 'Consent withdrawn by email on 26 October 2026');

    get('/about')->assertOk()->assertDontSee('Moving in took a day.');
    expect($testimonial->fresh())
        ->status->toBe('unpublished')
        ->quote->toBe('Moving in took a day.')
        ->consent_reference->toBe('Signed form 2026-14')
        ->and($testimonial->revisions()->count())->toBe(1)
        ->and($testimonial->revisions()->sole()->state)->toBe(RevisionState::Superseded);
});

it('takes a customer story off the list and its own address at once', function () {
    $caseStudy = liveCustomerStory($this->author, $this->approver, $this->publisher);
    get('/customers/rao-studio')->assertOk();

    app(UnpublishContent::class)->handle($caseStudy, $this->author, 'Customer asked us to remove it');

    get('/customers/rao-studio')->assertNotFound();
    get('/customers')->assertOk()->assertDontSee('Rao Studio grows');
});

it('lets anyone who may publish testimonials take one down, including the person who approved it', function () {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);

    app(UnpublishContent::class)->handle($testimonial, $this->approver, 'Consent withdrawn');

    expect($testimonial->fresh()->status)->toBe('unpublished');
});

it('refuses people who may not publish testimonials, and the item stays live', function (string $who) {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);

    if ($who === 'super admin without testimonials.publish') {
        Role::findByName(StaffRole::SuperAdmin->value)->revokePermissionTo(Capability::PublishTestimonials->value);
    }

    $user = $who === 'finance' ? User::factory()->withRole(StaffRole::Finance)->create() : $this->publisher->fresh();

    expect(fn () => app(UnpublishContent::class)->handle($testimonial, $user, 'Consent withdrawn'))
        ->toThrow(AuthorizationException::class, 'You cannot take this content down.');
    get('/about')->assertSee('Moving in took a day.');
})->with(['finance', 'super admin without testimonials.publish']);

it('refuses a deactivated account, and the item stays live', function () {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);
    $this->publisher->forceFill(['is_active' => false])->save();

    expect(fn () => app(UnpublishContent::class)->handle($testimonial, $this->publisher->fresh(), 'Consent withdrawn'))
        ->toThrow(AuthorizationException::class, 'This account is deactivated.');
    get('/about')->assertSee('Moving in took a day.');
});

it('needs a reason, and the item stays live without one', function (string $reason, string $message) {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);

    expect(fn () => app(UnpublishContent::class)->handle($testimonial, $this->publisher, $reason))->toThrow(ValidationException::class, $message);
    get('/about')->assertSee('Moving in took a day.');
})->with([
    'empty' => ['', 'Say why it is taken down.'],
    'only spaces' => ['   ', 'Say why it is taken down.'],
    'too long' => [str_repeat('a', 2001), 'Keep the reason under 2,000 characters.'],
]);

it('refuses content that is not live', function () {
    $draftOnly = Testimonial::factory()->create();
    app(CreateDraft::class)->handle($draftOnly, $this->author, consentedTestimonial());
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($testimonial, $this->publisher, 'Consent withdrawn');

    expect(fn () => app(UnpublishContent::class)->handle($draftOnly, $this->publisher, 'Consent withdrawn'))->toThrow(AuthorizationException::class, 'Only live content can be taken down.')
        ->and(fn () => app(UnpublishContent::class)->handle($testimonial->fresh(), $this->publisher, 'Again'))->toThrow(AuthorizationException::class, 'Only live content can be taken down.')
        ->and(Activity::query()->where('event', 'content_unpublished')->count())->toBe(1);
});

it('offers taking down only for testimonials and customer stories', function () {
    $post = Post::factory()->create();
    $revision = app(PublishRevision::class)->handle(approvedVersion($post, ['title' => 'Guide', 'slug' => 'guide', 'excerpt' => 'Short.', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text.</p>']],
    ]], $this->author, $this->approver), $this->publisher);

    expect(Gate::forUser($this->publisher)->inspect('unpublish', $revision)->message())->toBe('This content cannot be taken down here.');
});

it('sends approved versions back to their authors and cancels their schedule, so they cannot go live', function () {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);
    $waiting = approvedVersion($testimonial, consentedTestimonial('An approved change.'), $this->author, $this->approver);
    app(ScheduleRevision::class)->handle($waiting, $this->publisher, now()->addHour());

    app(UnpublishContent::class)->handle($testimonial, $this->publisher, 'Consent withdrawn');

    expect($waiting->fresh())
        ->state->toBe(RevisionState::Rejected)
        ->scheduled_for->toBeNull()
        ->reviewer_id->toBe($this->publisher->id)
        ->review_note->toBe('Sent back automatically: the testimonial was taken off the website. Reason: Consent withdrawn')
        ->and(fn () => app(PublishRevision::class)->handle($waiting->fresh(), $this->author))->toThrow(AuthorizationException::class, 'Only an approved version can be published.');

    $this->travel(2)->hours();

    expect(app(PublishScheduledRevisions::class)->handle())->toBe(0)
        ->and($testimonial->fresh()->status)->toBe('unpublished');
    get('/about')->assertDontSee('An approved change.')->assertDontSee('Moving in took a day.');
});

it('audits the take-down with the actor, time, item, reason and the version that was live', function () {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);
    $live = $testimonial->publishedRevision;
    $waiting = approvedVersion($testimonial, consentedTestimonial('An approved change.'), $this->author, $this->approver);

    $this->freezeTime(function () use ($testimonial, $live, $waiting): void {
        app(UnpublishContent::class)->handle($testimonial, $this->publisher, 'Consent withdrawn');

        $entry = Activity::query()->where('event', 'content_unpublished')->sole();
        $returned = Activity::query()->where('event', 'revision_returned')->sole();

        expect($entry->causer->is($this->publisher))->toBeTrue()
            ->and($entry->subject->is($live))->toBeTrue()
            ->and($entry->created_at->toDateTimeString())->toBe(now()->toDateTimeString())
            ->and($entry->properties->only(['content', 'reason', 'previous_state', 'live_revision', 'live_published_at', 'published_by', 'returned_revisions'])->all())->toEqual([
                'content' => 'Testimonial #'.$testimonial->id,
                'reason' => 'Consent withdrawn',
                'previous_state' => 'published',
                'live_revision' => 1,
                'live_published_at' => $live->published_at->toIso8601String(),
                'published_by' => $this->publisher->id,
                'returned_revisions' => [2],
            ])
            ->and($returned->subject->is($waiting))->toBeTrue()
            ->and($returned->properties['previous_reviewer_id'])->toBe($this->approver->id);
    });
});

it('puts a taken-down testimonial back only through a new version approved and published by different people', function () {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($testimonial, $this->publisher, 'Wrong job title');

    expect(fn () => app(PublishRevision::class)->handle($testimonial->revisions()->sole(), $this->publisher))->toThrow(AuthorizationException::class);

    $next = approvedVersion($testimonial->fresh(), [...consentedTestimonial(), 'person_title' => 'Co-founder'], $this->author, $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($next, $this->approver))->toThrow(AuthorizationException::class, 'You approved this version, so someone else must publish it.');

    app(PublishRevision::class)->handle($next, $this->author);

    expect($testimonial->fresh()->status)->toBe('published');
    get('/about')->assertSee('Co-founder');
});

it('keeps a testimonial whose consent was withdrawn off the website until a new version records consent and is approved', function () {
    $testimonial = liveTestimonialOnAboutPage($this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($testimonial, $this->publisher, 'Consent withdrawn');

    $draft = app(CreateDraft::class)->handle($testimonial->fresh(), $this->author, [...consentedTestimonial(), 'consent_on_file' => false, 'consent_reference' => null]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class, 'Consent from the person or company must be on file');
    get('/about')->assertDontSee('Moving in took a day.');
});
