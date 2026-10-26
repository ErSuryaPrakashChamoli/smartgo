<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Filament\Clusters\Website\Resources\Pages\Pages\CreatePage;
use App\Models\Page;
use App\Models\User;
use Filament\Forms\Components\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
});

it('creates an unpublished page with its first draft, and nothing goes live', function () {
    actingAs($this->editor);
    $undoBuilderFake = Builder::fake();

    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Enterprise offices',
            'slug' => 'enterprise-offices',
            'blocks' => [['type' => 'faq', 'data' => ['heading' => null, 'items' => [['question' => 'Can we customise?', 'answer' => null]]]]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $undoBuilderFake();
    $page = Page::query()->sole();

    expect($page)
        ->status->toBe('draft')
        ->title->toBeNull()
        ->slug->toBeNull()
        ->and($page->revisions()->sole())
        ->state->toBe(RevisionState::Draft)
        ->author_id->toBe($this->editor->id)
        ->payload->toMatchArray(['title' => 'Enterprise offices', 'slug' => 'enterprise-offices']);

    get('/enterprise-offices')->assertNotFound();
});

it('shows server-side errors on the fields they concern', function () {
    actingAs($this->editor);
    $undoBuilderFake = Builder::fake();

    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Settings',
            'slug' => 'admin',
            'blocks' => [['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Go', 'text' => null, 'primary_link' => ['label' => 'Click', 'type' => 'url', 'page_id' => null, 'url' => 'javascript:alert(1)'], 'secondary_link' => null]]],
        ])
        ->call('create')
        ->assertHasFormErrors(['slug', 'blocks.0.data.primary_link.url']);

    $undoBuilderFake();

    expect(Page::query()->count())->toBe(0);
});

it('refuses staff who cannot edit content', function (StaffRole $role) {
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())
        ->get(PageResource::getUrl('create'))
        ->assertForbidden();
})->with([StaffRole::OperationsManager, StaffRole::Finance]);

it('refuses to add pages for a content role that may view but not edit', function () {
    Role::findByName(StaffRole::MarketingManager->value)->revokePermissionTo(Capability::EditContent);

    actingAs($this->editor)->get(PageResource::getUrl('create'))->assertForbidden();
    actingAs($this->editor)->get(PageResource::getUrl('index'))->assertOk();
});
