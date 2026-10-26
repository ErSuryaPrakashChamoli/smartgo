<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Events\Content\ContentPublished;
use App\Http\Controllers\Backoffice\RevisionPreviewController;
use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Revision;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->page = Page::factory()->published()->create(['title' => 'Live title', 'slug' => 'about']);
    $this->editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->revision = app(CreateDraft::class)->handle($this->page, $this->editor, [
        'title' => 'Draft title',
        'slug' => 'about',
        'template' => 'standard',
        'blocks' => [['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Draft heading', 'intro' => null, 'link' => null]]],
    ]);
});

it('shows staff the draft exactly as it would be published, marked and never indexed', function () {
    actingAs($this->editor)
        ->get(RevisionPreviewController::url($this->revision))
        ->assertOk()
        ->assertSee('Draft heading')
        ->assertSee('Preview of version 1 (Draft). Not published')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('refuses a link without a valid signature', function () {
    $otherRevision = Revision::factory()->create();
    $signedForOther = RevisionPreviewController::url($otherRevision);

    actingAs($this->editor)->get(route(RevisionPreviewController::ROUTE, $this->revision))->assertForbidden();
    actingAs($this->editor)->get(str_replace("/revisions/{$otherRevision->id}/", "/revisions/{$this->revision->id}/", $signedForOther))->assertForbidden();
});

it('refuses an expired link', function () {
    $url = RevisionPreviewController::url($this->revision);

    $this->travel(RevisionPreviewController::LIFETIME_MINUTES + 1)->minutes();

    actingAs($this->editor)->get($url)->assertForbidden();
});

it('sends visitors who are not signed in to the staff sign-in page', function () {
    get(RevisionPreviewController::url($this->revision))->assertRedirect(route('filament.admin.auth.login'));
});

it('refuses staff without permission to view content', function (StaffRole $role) {
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())
        ->get(RevisionPreviewController::url($this->revision))
        ->assertForbidden();
})->with([StaffRole::OperationsManager, StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::Finance]);

it('refuses a member of a content role whose role has lost the view permission', function () {
    Role::findByName(StaffRole::MarketingManager->value)->revokePermissionTo(Capability::ViewContent);

    actingAs($this->editor)->get(RevisionPreviewController::url($this->revision))->assertForbidden();
});

it('refuses a deactivated account', function () {
    $this->editor->update(['is_active' => false]);

    actingAs($this->editor)->get(RevisionPreviewController::url($this->revision))->assertForbidden();
});

it('requires two-step login to be set up first', function () {
    $withoutTwoStep = User::factory()->withRole(StaffRole::MarketingManager)->create();

    actingAs($withoutTwoStep)->get(RevisionPreviewController::url($this->revision))->assertRedirect();
});

it('changes nothing: no write, no publication, and the live page stays as it is', function () {
    Event::fake([ContentPublished::class]);
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^\s*(insert|update|delete|replace)\b.*\b(pages|revisions|redirects|activity_log)\b/is', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    actingAs($this->editor)->get(RevisionPreviewController::url($this->revision))->assertOk();

    expect($writes)->toBe([]);
    Event::assertNotDispatched(ContentPublished::class);
    get('/about')->assertOk()->assertSee('Live title')->assertDontSee('Draft heading');
});

it('previews articles, customer stories, FAQs and testimonials as they would appear', function (string $model, array $payload, string $expected) {
    $revision = app(CreateDraft::class)->handle($model::factory()->create(), $this->editor, $payload);

    actingAs($this->editor)
        ->get(RevisionPreviewController::url($revision))
        ->assertOk()
        ->assertSee($expected)
        ->assertSee('Preview of version 1 (Draft). Not published')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with([
    'article' => [Post::class, ['title' => 'Draft article', 'excerpt' => 'Draft summary', 'blocks' => []], 'Draft summary'],
    'customer story' => [CaseStudy::class, ['title' => 'Draft story', 'company_name' => 'Rao Studio', 'blocks' => []], 'Rao Studio'],
    'FAQ' => [Faq::class, ['question' => 'Draft question?', 'answer' => 'Draft answer.'], 'Draft answer.'],
    'testimonial' => [Testimonial::class, ['quote' => 'Draft statement.', 'person_name' => 'Asha Rao'], 'Draft statement.'],
]);
