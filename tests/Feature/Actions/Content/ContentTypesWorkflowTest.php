<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UpdateDraft;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
 * @param  array<string, mixed>  $payload
 */
function publishContent(Model $content, array $payload, User $author, User $approver, User $publisher): Revision
{
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($content, $author, $payload), $author);

    return app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle($revision, $approver), $publisher);
}

/**
 * @return array<string, mixed>
 */
function articlePayload(string $slug = 'choosing-a-workspace'): array
{
    return ['title' => 'Choosing a workspace', 'slug' => $slug, 'excerpt' => 'What to look for.', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Start with your team.</p>']],
    ]];
}

/**
 * @return array<string, mixed>
 */
function testimonialPayload(): array
{
    return ['quote' => 'Moving in took a day.', 'person_name' => 'Asha Rao', 'person_title' => 'Founder', 'company_name' => 'Rao Studio',
        'consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14', 'statement_verified' => true];
}

it('keeps an article off the website until a version is approved and published', function () {
    $post = Post::factory()->create();
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($post, $this->author, articlePayload()), $this->author);
    app(ApproveRevision::class)->handle($revision, $this->approver);

    get('/insights/choosing-a-workspace')->assertNotFound();
    get('/insights')->assertOk()->assertDontSee('Choosing a workspace');
});

it('puts an approved article live with its fields', function () {
    $post = Post::factory()->create();

    publishContent($post, articlePayload(), $this->author, $this->approver, $this->publisher);

    expect($post->fresh())
        ->status->toBe('published')
        ->title->toBe('Choosing a workspace')
        ->path()->toBe('/insights/choosing-a-workspace');
    get('/insights/choosing-a-workspace')->assertOk()->assertSee('Start with your team.');
});

it('redirects the old address when a published article changes address', function () {
    $post = Post::factory()->create();
    publishContent($post, articlePayload('old-guide'), $this->author, $this->approver, $this->publisher);

    publishContent($post->fresh(), articlePayload('new-guide'), $this->author, $this->approver, $this->publisher);

    get('/insights/old-guide')->assertMovedPermanently()->assertRedirect('/insights/new-guide');
    get('/insights/new-guide')->assertOk();
});

it('refuses an address another article already uses', function () {
    Post::factory()->published()->create(['slug' => 'taken']);

    app(CreateDraft::class)->handle(Post::factory()->create(), $this->author, articlePayload('taken'));
})->throws(ValidationException::class, 'Another item already uses this address.');

it('offers articles and customer stories no introduction section', function (string $model) {
    app(CreateDraft::class)->handle($model::factory()->create(), $this->author, [
        'blocks' => [['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Hello', 'intro' => null, 'link' => null]]],
    ]);
})->with([Post::class, CaseStudy::class])->throws(ValidationException::class, 'This kind of section is not available here.');

it('needs consent on file, its reference and a verified statement before a testimonial is submitted', function (string $field, mixed $value, string $message) {
    $draft = app(CreateDraft::class)->handle(Testimonial::factory()->create(), $this->author, [...testimonialPayload(), $field => $value]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class, $message);
})->with([
    'no consent' => ['consent_on_file', false, 'Consent from the person or company must be on file'],
    'no consent reference' => ['consent_reference', null, 'The consent reference field is required.'],
    'unverified statement' => ['statement_verified', false, 'Confirm that this is a real, unedited statement.'],
]);

it('needs the customer\'s consent on file before a customer story is submitted', function () {
    $draft = app(CreateDraft::class)->handle(CaseStudy::factory()->create(), $this->author, [
        'title' => 'Rao Studio', 'slug' => 'rao-studio', 'company_name' => 'Rao Studio', 'summary' => 'A growing team.',
        'consent_on_file' => false, 'consent_reference' => 'Email 12 Oct',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Story.</p>']]],
    ]);

    app(SubmitRevision::class)->handle($draft, $this->author);
})->throws(ValidationException::class, 'Consent from the person or company must be on file');

it('publishes testimonials and customer stories only with testimonials.publish', function (string $model, array $payload) {
    Role::findByName(StaffRole::SuperAdmin->value)->revokePermissionTo(Capability::PublishTestimonials->value);
    $content = $model::factory()->create();
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($content, $this->author, $payload), $this->author);
    app(ApproveRevision::class)->handle($revision, $this->approver);

    expect(fn () => app(PublishRevision::class)->handle($revision->fresh(), $this->publisher->fresh()))->toThrow(AuthorizationException::class)
        ->and($content->fresh()->status)->toBe('draft');
})->with([
    'testimonial' => [Testimonial::class, fn () => testimonialPayload()],
    'customer story' => [CaseStudy::class, fn () => [
        'title' => 'Rao Studio', 'slug' => 'rao-studio', 'company_name' => 'Rao Studio', 'summary' => 'A growing team.',
        'consent_on_file' => true, 'consent_reference' => 'Email 12 Oct',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Story.</p>']]],
    ]],
]);

it('stores FAQs as plain text', function () {
    app(CreateDraft::class)->handle(Faq::factory()->create(), $this->author, ['question' => 'Parking?', 'answer' => '<b>Yes</b>', 'sort_order' => 1]);
})->throws(ValidationException::class, 'Use plain text: < and > are not allowed.');

it('needs a legal review reference before a legal page is submitted, and holds text sections only', function () {
    $page = Page::factory()->create();
    $draft = app(CreateDraft::class)->handle($page, $this->author, [
        'title' => 'Privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text approved by counsel.</p>']]],
    ]);

    expect(fn () => app(SubmitRevision::class)->handle($draft, $this->author))->toThrow(ValidationException::class, 'Legal pages need the reference of their review by counsel')
        ->and(fn () => app(UpdateDraft::class)->handle($draft, $this->author, [...$draft->payload, 'blocks' => [
            ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Go', 'text' => null, 'primary_link' => null, 'secondary_link' => null]],
        ]]))->toThrow(ValidationException::class, 'This kind of section is not available.');
});

it('starts each new version of a legal page without the previous review reference', function () {
    $page = Page::factory()->create();
    publishContent($page, [
        'title' => 'Privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal', 'legal_review_reference' => 'Counsel review 2026-03',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text approved by counsel.</p>']]],
    ], $this->author, $this->approver, $this->publisher);

    $next = app(CreateDraft::class)->handle($page->fresh(), $this->author);

    expect($page->fresh()->publishedRevision->payload['legal_review_reference'])->toBe('Counsel review 2026-03')
        ->and($next->payload['legal_review_reference'])->toBeNull();
});
