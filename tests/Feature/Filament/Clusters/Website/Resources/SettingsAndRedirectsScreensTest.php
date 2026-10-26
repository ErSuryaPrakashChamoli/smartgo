<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Actions\Content\SubmitRevision;
use App\Enums\Access\StaffRole;
use App\Enums\Content\RevisionState;
use App\Filament\Clusters\Website\Resources\RedirectMaps\Pages\EditRedirectMap;
use App\Filament\Clusters\Website\Resources\RedirectMaps\RedirectMapResource;
use App\Filament\Clusters\Website\Resources\Redirects\RedirectResource;
use App\Filament\Clusters\Website\Resources\SiteSettings\Pages\EditSiteSetting;
use App\Filament\Clusters\Website\Resources\SiteSettings\SiteSettingResource;
use App\Models\Redirect;
use App\Models\RedirectMap;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(SyncAccessControl::class)->handle();

    $this->marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->finance = User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create();
});

it('lists only the settings groups the user may open', function () {
    actingAs($this->marketing)->get(SiteSettingResource::getUrl('index'))
        ->assertOk()->assertSee('Contact details')->assertSee('Social links')->assertSee('Tracking IDs')->assertDontSee('Legal entity details');

    actingAs($this->finance)->get(SiteSettingResource::getUrl('index'))
        ->assertOk()->assertSee('Legal entity details')->assertDontSee('Contact details');
});

it('refuses a settings group the user may not open', function () {
    $contact = SiteSetting::query()->where('handle', 'contact')->sole();
    $legal = SiteSetting::query()->where('handle', 'legal')->sole();

    actingAs($this->finance)->get(SiteSettingResource::getUrl('edit', ['record' => $contact]))->assertForbidden();
    actingAs($this->marketing)->get(SiteSettingResource::getUrl('edit', ['record' => $legal]))->assertForbidden();
});

it('saves contact details as a draft, never live, and shows server-side errors on the field', function () {
    actingAs($this->marketing);
    $contact = SiteSetting::query()->where('handle', 'contact')->sole();
    app(CreateDraft::class)->handle($contact, $this->marketing);

    Livewire::test(EditSiteSetting::class, ['record' => $contact->getRouteKey()])
        ->assertFormFieldExists('phone')
        ->assertFormFieldDoesNotExist('legal_name')
        ->fillForm(['phone' => '+91 120 400 0000', 'whatsapp' => '01204000000'])
        ->call('save')
        ->assertHasFormErrors(['whatsapp'])
        ->fillForm(['whatsapp' => '+911204000000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contact->fresh()->values)->toBeNull()
        ->and($contact->workingRevision()->sole()->payload)->toMatchArray(['phone' => '+91 120 400 0000', 'whatsapp' => '+911204000000']);
});

it('offers another Finance user a Verify button for submitted legal details', function () {
    $legal = SiteSetting::query()->where('handle', 'legal')->sole();
    $draft = app(CreateDraft::class)->handle($legal, $this->finance, ['legal_name' => 'Example Private Limited', 'registered_address' => 'Sector 62, Noida']);
    app(SubmitRevision::class)->handle($draft, $this->finance);

    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::Finance)->create());

    Livewire::test(EditSiteSetting::class, ['record' => $legal->getRouteKey()])
        ->assertActionHasLabel('approve', 'Verify')
        ->assertActionEnabled('approve')
        ->assertActionHidden('preview');
});

it('lists live redirects for redirect managers only', function () {
    Redirect::factory()->create(['from_path' => '/old-about', 'to_path' => '/about']);

    actingAs($this->marketing)->get(RedirectResource::getUrl('index'))->assertOk()->assertSee('/old-about')->assertSee('Page address change');
    actingAs($this->finance)->get(RedirectResource::getUrl('index'))->assertForbidden();
    actingAs($this->finance)->get(RedirectMapResource::getUrl('edit', ['record' => RedirectMap::query()->sole()]))->assertForbidden();
});

it('saves the manual list as a draft and shows a refused address on its row', function () {
    actingAs($this->marketing);
    $map = RedirectMap::query()->sole();
    app(CreateDraft::class)->handle($map, $this->marketing);
    $undoRepeaterFake = Repeater::fake();

    $editor = Livewire::test(EditRedirectMap::class, ['record' => $map->getRouteKey()])
        ->fillForm(['redirects' => [['from_path' => '/old', 'match_type' => 'exact', 'status_code' => 301, 'to_path' => 'https://evil.example/']]])
        ->call('save')
        ->assertHasFormErrors(['redirects.0.to_path']);

    $editor->fillForm(['redirects' => [['from_path' => '/old', 'match_type' => 'exact', 'status_code' => 301, 'to_path' => '/about']]])
        ->call('save')
        ->assertHasNoFormErrors();

    $undoRepeaterFake();

    expect(Redirect::query()->count())->toBe(0)
        ->and($map->workingRevision()->sole())
        ->state->toBe(RevisionState::Draft)
        ->payload->toEqual(['redirects' => [['from_path' => '/old', 'match_type' => 'exact', 'status_code' => 301, 'to_path' => '/about']]]);
});
