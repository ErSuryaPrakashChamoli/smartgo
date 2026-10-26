<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Filament\Clusters\Website\Resources\SiteSettings\SiteSettingResource;
use App\Filament\Widgets\WaitingForApproval;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->author = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->approver = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->page = Page::factory()->create();
    $this->submitted = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($this->page, $this->author, [
        'title' => 'About SmartGro', 'slug' => 'about', 'template' => 'standard',
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text.</p>']]],
    ]), $this->author);
    $legal = SiteSetting::query()->where('handle', 'legal')->sole();
    $financeAuthor = User::factory()->withRole(StaffRole::Finance)->create();
    $this->legalRevision = app(SubmitRevision::class)->handle(app(CreateDraft::class)->handle($legal, $financeAuthor, ['legal_name' => 'Example Private Limited', 'registered_address' => 'Noida']), $financeAuthor);
});

it('lists the versions the user may approve, linking to their editor', function () {
    actingAs($this->approver);

    Livewire::test(WaitingForApproval::class)
        ->assertCanSeeTableRecords([$this->submitted])
        ->assertCanNotSeeTableRecords([$this->legalRevision])
        ->assertSee('About SmartGro')
        ->assertSee(PageResource::getUrl('edit', ['record' => $this->page]), false);
});

it('marks a version the user worked on, because someone else must approve it', function () {
    actingAs($this->author);

    Livewire::test(WaitingForApproval::class)
        ->assertCanSeeTableRecords([$this->submitted])
        ->assertSee('You worked on this version, so someone else must approve it.');
});

it('shows Finance only the legal details', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create());

    Livewire::test(WaitingForApproval::class)
        ->assertCanSeeTableRecords([$this->legalRevision])
        ->assertCanNotSeeTableRecords([$this->submitted])
        ->assertSee(SiteSettingResource::getUrl('edit', ['record' => $this->legalRevision->revisionable]), false);
});

it('is not shown to staff who approve nothing', function () {
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::SalesCrmExecutive)->create());

    expect(WaitingForApproval::canView())->toBeFalse();
});

it('appears on the dashboard', function () {
    actingAs($this->approver)->get(filament()->getUrl())->assertOk()->assertSeeLivewire(WaitingForApproval::class);
});
