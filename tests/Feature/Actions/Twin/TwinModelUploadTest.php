<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Twin\DeleteTwinModel;
use App\Enums\Access\StaffRole;
use App\Enums\Twin\AnchorType;
use App\Enums\Twin\CaptureType;
use App\Models\Property;
use App\Models\TwinAnchor;
use App\Models\TwinModel;
use App\Models\User;
use App\Services\Twin\TwinModelLimits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\Twin\TestGlb;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();

    $this->property = Property::factory()->create();
    $this->marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
});

/**
 * The validation messages an upload fails with.
 *
 * @param  array<string, mixed>  $details
 * @return array<string, list<string>>
 */
function twinUploadErrors(array $details = [], ?string $bytes = null, string $fileName = 'model.glb'): array
{
    try {
        uploadTwinModelForTest(test()->marketing, test()->property, $details, $bytes, $fileName);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('The upload was accepted.');
}

it('stores a model that passes the checks with its anchors, a private file and an audit entry', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property, ['title' => 'Ground floor (test)', 'captured_by' => 'Test supplier', 'captured_on' => '2026-10-01']);

    expect($model->fresh())
        ->property_id->toBe($this->property->id)
        ->title->toBe('Ground floor (test)')
        ->capture_type->toBe(CaptureType::ConceptualModel)
        ->captured_by->toBe('Test supplier')
        ->captured_on->toDateString()->toBe('2026-10-01')
        ->file_bytes->toBe(strlen(TestGlb::model()))
        ->triangle_count->toBe(36)
        ->uploaded_by->toBe($this->marketing->id)
        ->and($model->validation_report)->toMatchArray(['compression' => ['meshopt'], 'nodes' => 5, 'draw_calls' => 3, 'size_metres' => [10.0, 1.2, 8.0], 'budgets' => app(TwinModelLimits::class)->toArray()])
        ->and($model->anchors->map(fn (TwinAnchor $anchor): array => [$anchor->anchor_type, $anchor->reference, $anchor->node_name])->all())->toBe([
            [AnchorType::Amenity, 'test-lounge', 'SG_AMENITY_test-lounge'],
            [AnchorType::Camera, '1', 'SG_CAM_1'],
            [AnchorType::Camera, '2', 'SG_CAM_2'],
            [AnchorType::Space, 'TEST-01', 'SG_SPACE_TEST-01'],
        ]);

    $file = $model->file();

    expect($file->disk)->toBe('local')
        ->and($file->file_name)->toMatch('/^[a-z0-9]{40}\.glb$/')
        ->and(Storage::disk('local')->get($file->getPathRelativeToRoot()))->toBe(TestGlb::model());

    expect(Activity::query()->where('event', 'twin_model_uploaded')->sole())
        ->causer_id->toBe($this->marketing->id)
        ->subject_id->toBe($model->id)
        ->properties->toArray()->toMatchArray(['property' => $this->property->code, 'triangle_count' => 36, 'anchors' => 4, 'capture_type' => 'conceptual_model']);
});

it('refuses a file that fails the checks with every reason, and stores nothing', function () {
    $document = TestGlb::document(['images' => [['uri' => 'textures/wall.png']]]);
    unset($document['bufferViews'][0]['extensions']);

    expect(twinUploadErrors(bytes: TestGlb::withDocument($document)))->toBe(['file' => [
        'The model refers to outside files (“textures/wall.png”). Ask the supplier to export one self-contained .glb with everything embedded.',
        '3 of the model\'s 3 surface parts are not compressed. Ask the supplier to export with Draco or Meshopt compression.',
    ]]);

    expect(TwinModel::query()->count())->toBe(0)
        ->and(TwinAnchor::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('stores a model of exactly 15 MB, the size limit', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property, bytes: TestGlb::modelOfSize(15 * 1024 * 1024));

    expect($model->file_bytes)->toBe(15 * 1024 * 1024)
        ->and(Storage::disk('local')->size($model->file()->getPathRelativeToRoot()))->toBe(15 * 1024 * 1024);
});

it('refuses a file over 15 MB before storing anything', function () {
    expect(twinUploadErrors(bytes: TestGlb::modelOfSize(15 * 1024 * 1024 + 4)))->toBe(['file' => ['The file is 15.1 MB; the limit is 15.0 MB per model. Ask the 3D-modelling supplier to compress it further or reduce the detail.']])
        ->and(TwinModel::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('holds uploads to the limits in the configuration', function () {
    config()->set('twin.limits.triangles', 30);

    expect(twinUploadErrors())->toBe(['file' => ['The model has 36 triangles; the limit is 30. Ask the supplier to reduce the detail.']])
        ->and(TwinModel::query()->count())->toBe(0);
});

it('stops checking a file that takes too long, and stores nothing', function () {
    config()->set('twin.limits.check_seconds', 0);

    expect(twinUploadErrors())->toBe(['file' => ['Checking the model took longer than 0 seconds, so it was stopped. The file is probably far too complex: ask the supplier to simplify it, with fewer parts and textures.']])
        ->and(TwinModel::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('stores a Draco-compressed model', function () {
    expect(uploadTwinModelForTest($this->marketing, $this->property, bytes: TestGlb::dracoModel()))
        ->triangle_count->toBe(36)
        ->validation_report->toMatchArray(['compression' => ['draco']]);
});

it('accepts only .glb files', function () {
    expect(twinUploadErrors(fileName: 'model.gltf'))->toBe(['file' => ['Upload a .glb file (binary glTF).']]);
});

it('validates the details given with the file', function (array $details, string $field) {
    expect(twinUploadErrors($details))->toHaveKey($field);
})->with([
    'no title' => [['title' => ''], 'title'],
    'markup in the title' => [['title' => '<b>Model</b>'], 'title'],
    'unknown capture type' => [['capture_type' => 'photo'], 'capture_type'],
    'unknown property' => [['property_id' => 999999], 'property_id'],
    'capture date in the future' => [['captured_on' => now()->addDay()->toDateString()], 'captured_on'],
    'markup in the maker' => [['captured_by' => 'Studio <script>'], 'captured_by'],
]);

it('lets only staff who manage 3D models upload them', function (StaffRole $role) {
    $user = User::factory()->withRole($role)->create();

    expect(fn () => uploadTwinModelForTest($user, $this->property))->toThrow(AuthorizationException::class);
})->with([StaffRole::OperationsManager, StaffRole::PropertyManager, StaffRole::SalesCrmExecutive, StaffRole::Finance]);

it('refuses a deactivated account', function () {
    $this->marketing->forceFill(['is_active' => false])->save();

    expect(fn () => uploadTwinModelForTest($this->marketing, $this->property))->toThrow(AuthorizationException::class);
});

it('deletes a model with its anchors and file, audited', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property);

    app(DeleteTwinModel::class)->handle($model, $this->marketing);

    expect(TwinModel::query()->count())->toBe(0)
        ->and(TwinAnchor::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(Activity::query()->where('event', 'twin_model_deleted')->sole())->causer_id->toBe($this->marketing->id);
});

it('refuses deletion by staff who only see models', function () {
    $model = uploadTwinModelForTest($this->marketing, $this->property);

    expect(fn () => app(DeleteTwinModel::class)->handle($model, User::factory()->withRole(StaffRole::OperationsManager)->create()))
        ->toThrow(AuthorizationException::class)
        ->and($model->fresh())->not->toBeNull();
});
