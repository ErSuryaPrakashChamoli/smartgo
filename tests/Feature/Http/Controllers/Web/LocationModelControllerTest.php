<?php

use App\Actions\Access\SyncAccessControl;
use App\Actions\Content\CreateDraft;
use App\Enums\Access\StaffRole;
use App\Enums\Properties\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Twin\TestGlb;

use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    app(SyncAccessControl::class)->handle();
    seed(ReferenceDataSeeder::class);

    $this->marketing = User::factory()->withRole(StaffRole::MarketingManager)->create();
    $this->property = Property::query()->where('code', 'A-70')->sole();
    $this->model = uploadTwinModelForTest($this->marketing, $this->property);
});

it('sends a model\'s GLB file while a published profile of a listed location shows it (OD-181)', function () {
    publishPropertyProfileForTest($this->property, ['twin_model_id' => $this->model->id]);

    $response = get('/3d-models/'.$this->model->id)
        ->assertOk()
        ->assertHeader('Content-Type', 'model/gltf-binary')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->getCacheControlDirective('public'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('max-age'))->toBe('3600')
        ->and(file_get_contents($response->baseResponse->getFile()->getPathname()))->toBe(TestGlb::model());
});

it('answers 404 for a model no published profile of a listed location shows', function (Closure $arrange) {
    $arrange();

    get('/3d-models/'.$this->model->id)->assertNotFound();
})->with([
    'a model no profile uses' => [fn () => publishPropertyProfileForTest(test()->property)],
    'a model only in a version not yet published' => [function (): void {
        publishPropertyProfileForTest(test()->property);
        app(CreateDraft::class)->handle(test()->property->profile, test()->marketing, ['headline' => 'H', 'summary' => 'S', 'twin_model_id' => test()->model->id]);
    }],
    'a model of a profile taken off the website' => [function (): void {
        publishPropertyProfileForTest(test()->property, ['twin_model_id' => test()->model->id]);
        test()->property->profile->forceFill(['status' => 'draft'])->save();
    }],
    'a model of a location that is closed' => [function (): void {
        publishPropertyProfileForTest(test()->property, ['twin_model_id' => test()->model->id]);
        test()->property->forceFill(['operational_status' => PropertyStatus::Closed])->save();
    }],
]);

it('answers 404 for a model that does not exist', function () {
    get('/3d-models/999999')->assertNotFound();
});
