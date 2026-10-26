<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Clusters\Website\Resources\Faqs\FaqResource;
use App\Filament\Clusters\Website\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Clusters\Website\Resources\Pages\Pages\EditPage;
use App\Filament\Clusters\Website\Resources\Posts\Pages\CreatePost;
use App\Filament\Clusters\Website\Resources\Posts\PostResource;
use App\Filament\Clusters\Website\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Clusters\Website\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Clusters\Website\Resources\Testimonials\TestimonialResource;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Testimonial;
use App\Models\User;
use Filament\Forms\Components\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
});

it('lists each content type with what is live and what is in progress', function (string $resource, string $model, string $field) {
    $live = $model::factory()->published()->create();
    $draftOnly = $model::factory()->create();
    app(CreateDraft::class)->handle($draftOnly, $this->editor, [$field => 'Only in a draft']);

    actingAs($this->editor)
        ->get($resource::getUrl('index'))
        ->assertOk()
        ->assertSee($live->getAttribute($field))
        ->assertSee('Only in a draft')
        ->assertSee('Live: v')
        ->assertSee('v1: Draft');
})->with([
    'articles' => [PostResource::class, Post::class, 'title'],
    'customer stories' => [CaseStudyResource::class, CaseStudy::class, 'title'],
    'testimonials' => [TestimonialResource::class, Testimonial::class, 'person_name'],
    'FAQs' => [FaqResource::class, Faq::class, 'question'],
]);

it('refuses staff who cannot view content', function (string $resource) {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create())
        ->get($resource::getUrl('index'))
        ->assertForbidden();
})->with([PostResource::class, CaseStudyResource::class, TestimonialResource::class, FaqResource::class]);

it('creates an article as an unpublished draft', function () {
    actingAs($this->editor);
    $undoBuilderFake = Builder::fake();

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Choosing a workspace', 'slug' => 'choosing-a-workspace', 'excerpt' => null, 'blocks' => []])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoBuilderFake();

    expect(Post::query()->sole())
        ->status->toBe('draft')
        ->title->toBeNull()
        ->and(Post::query()->sole()->revisions()->sole())
        ->state->toBe(RevisionState::Draft)
        ->payload->toMatchArray(['title' => 'Choosing a workspace', 'slug' => 'choosing-a-workspace']);
});

it('saves a testimonial draft before consent is recorded, but will not submit it', function () {
    actingAs($this->editor);

    Livewire::test(CreateTestimonial::class)
        ->fillForm(['quote' => 'Moving in took a day.', 'person_name' => 'Asha Rao', 'consent_on_file' => false, 'statement_verified' => false])
        ->call('create')
        ->assertHasNoFormErrors();

    $testimonial = Testimonial::query()->sole();

    Livewire::test(EditTestimonial::class, ['record' => $testimonial->getRouteKey()])
        ->callAction('submit')
        ->assertNotified('Check the highlighted fields')
        ->assertHasErrors(['data.consent_on_file', 'data.consent_reference', 'data.statement_verified']);

    expect($testimonial->revisions()->sole()->state)->toBe(RevisionState::Draft);
});

it('shows server-side errors on FAQ fields', function () {
    actingAs($this->editor);

    Livewire::test(CreateFaq::class)
        ->fillForm(['question' => 'Parking <b>here</b>?', 'answer' => null, 'sort_order' => 1])
        ->call('create')
        ->assertHasFormErrors(['question']);

    expect(Faq::query()->count())->toBe(0);
});

it('edits the layout and legal review reference of a page', function () {
    actingAs($this->editor);
    $page = Page::factory()->create();
    $draft = app(CreateDraft::class)->handle($page, $this->editor, ['title' => 'Privacy notice', 'slug' => 'legal/privacy', 'template' => 'legal', 'legal_review_reference' => 'Counsel review 2026-03']);
    $undoBuilderFake = Builder::fake();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertSchemaStateSet(['template' => 'legal', 'legal_review_reference' => 'Counsel review 2026-03'])
        ->fillForm(['legal_review_reference' => 'Counsel review 2026-04'])
        ->call('save')
        ->assertHasNoFormErrors();

    $undoBuilderFake();

    expect($draft->fresh()->payload)->toMatchArray(['template' => 'legal', 'legal_review_reference' => 'Counsel review 2026-04']);
});
