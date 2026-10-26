<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\Capability;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Filament\Clusters\Website\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->editor = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->livePage = Page::factory()->published()->create(['title' => 'About SmartGro', 'slug' => 'about']);
    $this->newPage = Page::factory()->create();
    $draft = app(CreateDraft::class)->handle($this->newPage, $this->editor, [
        'title' => 'Enterprise offices', 'slug' => 'enterprise-offices', 'template' => 'standard',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Managed offices.</p>']]],
    ]);
    app(SubmitRevision::class)->handle($draft, $this->editor);
});

it('lists live pages and pages still in progress with their state', function () {
    actingAs($this->editor);

    Livewire::test(ListPages::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$this->livePage, $this->newPage])
        ->assertSee('About SmartGro')
        ->assertSee('Enterprise offices')
        ->assertSee('/enterprise-offices (not published)')
        ->assertSee('v1: Submitted for approval')
        ->assertSee('Not published');
});

it('finds pages by live or draft title and address', function (string $search) {
    actingAs($this->editor);

    Livewire::test(ListPages::class)
        ->searchTable($search)
        ->assertCanSeeTableRecords([$this->newPage])
        ->assertCanNotSeeTableRecords([$this->livePage]);
})->with(['Enterprise', 'enterprise-offices']);

it('filters pages by the state of the version in progress', function () {
    actingAs($this->editor);

    Livewire::test(ListPages::class)
        ->filterTable('workflow', 'submitted')
        ->assertCanSeeTableRecords([$this->newPage])
        ->assertCanNotSeeTableRecords([$this->livePage])
        ->filterTable('workflow', 'approved')
        ->assertCanNotSeeTableRecords([$this->newPage, $this->livePage]);
});

it('refuses staff without permission to view content', function (StaffRole $role) {
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())
        ->get(PageResource::getUrl('index'))
        ->assertForbidden();
})->with([StaffRole::OperationsManager, StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::Finance]);

it('refuses a content role whose role no longer holds the view permission', function () {
    Role::findByName(StaffRole::MarketingManager->value)->revokePermissionTo(Capability::ViewContent);

    actingAs($this->editor)->get(PageResource::getUrl('index'))->assertForbidden();
});
