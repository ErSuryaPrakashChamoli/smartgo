<?php

use App\Actions\Access\SyncAccessControl;
use App\Enums\Access\StaffRole;
use App\Enums\Twin\CaptureType;
use App\Filament\Clusters\Website\Resources\TwinModels\Pages\CreateTwinModel;
use App\Filament\Clusters\Website\Resources\TwinModels\Pages\ListTwinModels;
use App\Filament\Clusters\Website\Resources\TwinModels\Pages\ViewTwinModel;
use App\Filament\Clusters\Website\Resources\TwinModels\Schemas\TwinModelForm;
use App\Filament\Clusters\Website\Resources\TwinModels\TwinModelResource;
use App\Http\Controllers\Backoffice\TwinModelFileController;
use App\Models\Property;
use App\Models\TwinModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Livewire\Livewire;
use Tests\Support\Twin\TestGlb;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();

    $this->property = Property::factory()->create();
    $this->marketing = User::factory()->withEmailAuthentication()->withRole(StaffRole::MarketingManager)->create();
    $this->operations = User::factory()->withEmailAuthentication()->withRole(StaffRole::OperationsManager)->create();
});

it('lists models for staff who may see them, and lets only managers upload', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property);

    actingAs($this->marketing);
    Livewire::test(ListTwinModels::class)->assertCanSeeTableRecords([$model])->assertSee('Conceptual model')->assertActionVisible('create');

    actingAs($this->operations);
    Livewire::test(ListTwinModels::class)->assertCanSeeTableRecords([$model])->assertActionHidden('create');
});

it('refuses the screens to staff who may not see models', function (StaffRole $role) {
    actingAs(User::factory()->withEmailAuthentication()->withRole($role)->create())
        ->get(TwinModelResource::getUrl('index'))
        ->assertForbidden();
})->with([StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::Finance]);

it('uploads a model through the form and opens it', function () {
    actingAs($this->marketing);

    Livewire::test(CreateTwinModel::class)
        ->fillForm([
            'file' => UploadedFile::fake()->createWithContent('model.glb', TestGlb::model()),
            'property_id' => $this->property->id,
            'capture_type' => CaptureType::ArchitecturalModel->value,
            'title' => 'Test model',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(TwinModelResource::getUrl('view', ['record' => TwinModel::query()->sole()]));

    expect(TwinModel::query()->sole())->capture_type->toBe(CaptureType::ArchitecturalModel)->anchors->toHaveCount(4);
});

it('shows every reason a file fails under the file field', function () {
    $document = TestGlb::document(['images' => [['uri' => 'textures/wall.png']]]);
    unset($document['bufferViews'][0]['extensions']);
    actingAs($this->marketing);

    Livewire::test(CreateTwinModel::class)
        ->fillForm([
            'file' => UploadedFile::fake()->createWithContent('model.glb', TestGlb::withDocument($document)),
            'property_id' => $this->property->id,
            'capture_type' => CaptureType::MeasuredScan->value,
            'title' => 'Test model',
        ])
        ->call('create')
        ->assertHasFormErrors(['file'])
        ->assertSeeText('The model refers to outside files')
        ->assertSeeText('surface parts are not compressed');

    expect(TwinModel::query()->count())->toBe(0);
});

it('shows a model with its notice, checks and anchors, and loads nothing 3D until asked', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property);
    $html = actingAs($this->operations)->get(TwinModelResource::getUrl('view', ['record' => $model]))->assertOk()->getContent();
    $viewerUrl = Vite::asset('resources/js/twin-viewer.js');
    $fileUrl = route(TwinModelFileController::ROUTE, ['twinModel' => $model]);

    expect($html)
        ->toContain('3D model of SmartGro '.e($this->property->name))
        ->toContain('Conceptual illustration — not a scan of this location')
        ->toContain('Camera position 1')
        ->toContain('Camera position 2')
        ->toContain('SG_SPACE_TEST-01')
        ->toContain('SG_AMENITY_test-lounge')
        ->toContain('36 (limit 500,000)')
        ->toContain('3 (limit 500)')
        ->toContain('10 × 1.2 × 8 m')
        ->not->toContain('<script src="'.$viewerUrl)
        ->not->toContain('modulepreload')
        ->and(substr_count($html, $viewerUrl))->toBe(1)
        ->and(substr_count($html, 'data-module="'.$viewerUrl.'"'))->toBe(1)
        ->and(substr_count($html, $fileUrl))->toBe(1)
        ->and(substr_count($html, 'data-model="'.$fileUrl.'"'))->toBe(1);
});

it('lets uploads in progress be larger than the model size limit, so an oversized file gets the plain-language reason', function () {
    $transferLimitKilobytes = (int) str(collect(config('livewire.temporary_file_upload.rules'))->first(fn (string $rule): bool => str_starts_with($rule, 'max:')))->after('max:')->toString();

    expect($transferLimitKilobytes * 1024)->toBeGreaterThan(config('twin.limits.bytes'))
        ->and(TwinModelForm::UPLOAD_LIMIT_KILOBYTES)->toBeLessThanOrEqual($transferLimitKilobytes)
        ->and(TwinModelForm::UPLOAD_LIMIT_KILOBYTES * 1024)->toBeGreaterThan(config('twin.limits.bytes'));
});

it('calls only a measured scan a scan, without a notice', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property, ['capture_type' => CaptureType::MeasuredScan->value]);

    actingAs($this->marketing)->get(TwinModelResource::getUrl('view', ['record' => $model]))
        ->assertOk()
        ->assertSee('3D scan of SmartGro '.$this->property->name)
        ->assertSee('None: a measured scan of the location')
        ->assertDontSee('data-twin-notice', false);
});

it('lets managers delete a model and hides deletion from staff who only see models', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property);

    actingAs($this->operations);
    Livewire::test(ViewTwinModel::class, ['record' => $model->getRouteKey()])->assertActionHidden('delete');

    actingAs($this->marketing);
    Livewire::test(ViewTwinModel::class, ['record' => $model->getRouteKey()])
        ->callAction('delete')
        ->assertRedirect(TwinModelResource::getUrl('index'));

    expect(TwinModel::query()->count())->toBe(0);
});
