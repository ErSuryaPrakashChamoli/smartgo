<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\ApproveRevision;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\PublishRevision;
use App\Actions\Content\SubmitRevision;
use App\Actions\Content\UnpublishContent;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Clusters\Website\Resources\Posts\Pages\EditPost;
use App\Filament\Clusters\Website\Resources\Testimonials\Pages\EditTestimonial;
use App\Models\CaseStudy;
use App\Models\Post;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->publisher = User::factory()->withEmailAuthentication()->withRole(StaffRole::SuperAdmin)->create();
});

/**
 * @param  array<string, mixed>  $payload
 */
function publishedThroughWorkflow(Model $content, array $payload, User $author, User $approver, User $publisher): Model
{
    $revision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($content, $author, $payload), $author);
    app(PublishRevision::class)->handle(app(ApproveRevision::class)->handle($revision, $approver), $publisher);

    return $content->fresh();
}

function screenTestimonial(User $author, User $approver, User $publisher): Testimonial
{
    return publishedThroughWorkflow(Testimonial::factory()->create(), [
        'quote' => 'Moving in took a day.', 'person_name' => 'Asha Rao', 'person_title' => null, 'company_name' => null,
        'consent_on_file' => true, 'consent_reference' => 'Signed form 2026-14', 'statement_verified' => true,
    ], $author, $approver, $publisher);
}

it('takes a live testimonial off the website from its editor, only with a reason', function () {
    $testimonial = screenTestimonial($this->author, $this->approver, $this->publisher);
    actingAs($this->publisher);

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
        ->assertActionVisible('takeDown')
        ->assertActionEnabled('takeDown')
        ->callAction('takeDown', data: ['reason' => '', 'consent_withdrawn' => null])
        ->assertHasActionErrors(['reason' => 'required', 'consent_withdrawn' => 'required']);

    expect($testimonial->fresh()->status)->toBe('published');

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
        ->callAction('takeDown', data: ['reason' => 'Consent withdrawn by email', 'consent_withdrawn' => '1'])
        ->assertHasNoActionErrors()
        ->assertNotified('Testimonial taken off the website');

    expect($testimonial->fresh()->status)->toBe('unpublished');

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
        ->assertActionHidden('takeDown')
        ->assertSee('Taken off the website')
        ->assertSee('Consent withdrawn by email')
        ->assertSee('Consent was withdrawn on')
        ->assertSee('A new version can only be submitted with new consent');
});

it('shows the button disabled, with the reason, to someone who may not publish testimonials', function () {
    $testimonial = screenTestimonial($this->author, $this->approver, $this->publisher);
    Role::findByName(StaffRole::MarketingManager->value)->revokePermissionTo(Capability::PublishTestimonials->value);
    actingAs($this->author->fresh());

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
        ->assertActionVisible('takeDown')
        ->assertActionDisabled('takeDown');

    expect($testimonial->fresh()->status)->toBe('published');
});

it('lists a taken-down customer story as taken down', function () {
    $caseStudy = publishedThroughWorkflow(CaseStudy::factory()->create(), [
        'title' => 'Rao Studio grows', 'slug' => 'rao-studio', 'company_name' => 'Rao Studio', 'summary' => 'A growing team.',
        'consent_on_file' => true, 'consent_reference' => 'Email 12 Oct',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Story.</p>']]],
    ], $this->author, $this->approver, $this->publisher);
    app(UnpublishContent::class)->handle($caseStudy, $this->publisher, 'Customer asked us to remove it', consentWithdrawn: false);

    actingAs($this->author)->get(CaseStudyResource::getUrl('index'))->assertOk()->assertSee('Taken down');
});

it('offers no take-down button for other content, such as articles', function () {
    $post = publishedThroughWorkflow(Post::factory()->create(), ['title' => 'Guide', 'slug' => 'guide', 'excerpt' => 'Short.', 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text.</p>']],
    ]], $this->author, $this->approver, $this->publisher);
    actingAs($this->publisher);

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionDoesNotExist('takeDown');
});

it('asks for new consent in the editor once consent is withdrawn, and only then', function () {
    $testimonial = screenTestimonial($this->author, $this->approver, $this->publisher);
    actingAs($this->author);
    app(CreateDraft::class)->handle($testimonial, $this->author);

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])->assertFormFieldHidden('consent_reconfirmed');

    app(UnpublishContent::class)->handle($testimonial->fresh(), $this->publisher, 'Consent withdrawn', consentWithdrawn: true);
    app(CreateDraft::class)->handle($testimonial->fresh(), $this->author);

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
        ->assertFormFieldVisible('consent_reconfirmed')
        ->assertSchemaStateSet(['consent_on_file' => false, 'consent_reference' => null, 'consent_reconfirmed' => false])
        ->fillForm(['consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026'])
        ->callAction('submit')
        ->assertHasErrors(['data.consent_reconfirmed']);

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
        ->fillForm(['consent_on_file' => true, 'consent_reference' => 'Email 2 Nov 2026', 'consent_reconfirmed' => true])
        ->callAction('submit')
        ->assertNotified('Submitted for approval');
});
