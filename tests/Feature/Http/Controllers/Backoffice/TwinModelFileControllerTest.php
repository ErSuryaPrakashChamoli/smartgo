<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Http\Controllers\Backoffice\TwinModelFileController;
use App\Models\Property;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Twin\TestGlb;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();

    $this->model = uploadTwinModelForTest(User::factory()->withRole(StaffRole::MarketingManager)->create(), Property::factory()->create());
    $this->url = route(TwinModelFileController::ROUTE, ['twinModel' => $this->model]);
});

it('sends the file to staff who may see models, as a GLB that is never cached', function (StaffRole $role) {
    $response = actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())->get($this->url);

    $response->assertOk()
        ->assertHeader('Content-Type', 'model/gltf-binary')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe(TestGlb::model());
})->with([StaffRole::SuperAdmin, StaffRole::MarketingManager, StaffRole::OperationsManager]);

it('refuses staff who may not see models', function (StaffRole $role) {
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())->get($this->url)->assertForbidden();
})->with([StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::Finance]);

it('sends visitors who are not signed in to the staff sign-in page', function () {
    get($this->url)->assertRedirect(route('filament.admin.auth.login'));
});

it('requires two-step login first', function () {
    actingAs(User::factory()->withRole(StaffRole::MarketingManager)->create())
        ->get($this->url)
        ->assertRedirect(Filament::getSetUpRequiredMultiFactorAuthenticationUrl());
});

it('answers 404 when the model or its file is gone', function () {
    $staff = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    Storage::disk('local')->delete($this->model->file()->getPathRelativeToRoot());

    actingAs($staff)->get($this->url)->assertNotFound();
    actingAs($staff)->get(route(TwinModelFileController::ROUTE, ['twinModel' => 999999]))->assertNotFound();
});

it('never serves a model file through the storage address without a signature', function () {
    $path = $this->model->file()->getPathRelativeToRoot();

    get('/storage/'.$path)->assertForbidden();
    actingAs(User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create())->get('/storage/'.$path)->assertForbidden();
});
